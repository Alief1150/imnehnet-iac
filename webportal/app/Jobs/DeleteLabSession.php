<?php

namespace App\Jobs;

use App\Enums\LabSessionStatus;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Services\GuidedCtf\LifecycleFailure;
use App\Services\GuidedCtf\PairedLabTeardownService;
use App\Services\GuidedCtf\ProvisioningTimeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class DeleteLabSession implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 2400;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $sessionId) {}

    public function handle(PairedLabTeardownService $teardown): void
    {
        Cache::lock('imnehnet:ctf:session-lifecycle:'.$this->sessionId, 2700)->block(60, function () use ($teardown): void {
            $session = LabSession::query()->find($this->sessionId);
            if (! $session || $session->status !== LabSessionStatus::Deleting) {
                return;
            }
            ProvisioningTimeline::record($this->sessionId, 'delete-start', 'Worker mulai memproses Delete.');
            $session->load(['victimEnvironment', 'attackerEnvironment']);
            $victimId = $session->victim_environment_id;
            $attackerId = $session->attacker_environment_id;
            $teardown->teardown($session);
            DB::connection((string) config('infrastructure.environment_connection'))->transaction(function () use ($session, $victimId, $attackerId): void {
                $locked = LabSession::query()->lockForUpdate()->findOrFail($session->id);
                if ($locked->status !== LabSessionStatus::Deleting
                    || (int) $locked->victim_environment_id !== (int) $victimId
                    || (int) $locked->attacker_environment_id !== (int) $attackerId
                    || (int) $locked->user_id !== (int) $session->user_id) {
                    throw new RuntimeException('Session identity changed during deletion.');
                }
                $locked->forceFill(['victim_environment_id' => null, 'attacker_environment_id' => null,
                    'status' => LabSessionStatus::Deleted, 'last_error' => null])->save();
                foreach (array_unique(array_filter([$victimId, $attackerId])) as $id) {
                    LabEnvironment::query()->whereKey($id)->where('user_id', $session->user_id)->delete();
                }
            });
            ProvisioningTimeline::record($this->sessionId, 'delete-done',
                'Delete selesai. Victim environment #'.$victimId.' dan attacker environment #'.$attackerId.
                ' telah dihapus; kuota dilepas. Riwayat ID tersimpan.', 'success');
        });
    }

    public function failed(?Throwable $error): void
    {
        $session = LabSession::query()->find($this->sessionId);
        if (! $session || $session->status === LabSessionStatus::Deleted) {
            return;
        }
        LifecycleFailure::record($this->sessionId, 'Delete Lab', $error ?? new RuntimeException('Worker timed out.'));
        LabSession::query()->whereKey($this->sessionId)->where('status', LabSessionStatus::Deleting->value)
            ->update(['status' => LabSessionStatus::Failed->value]);
        try {
            ProvisioningTimeline::record($this->sessionId, 'delete-failed', 'Delete gagal. Kuota tetap terpakai; detail referensi tercantum di bawah.', 'error');
        } catch (Throwable $logError) {
            report($logError);
        }
    }
}
