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

    public function __construct(public readonly int $sessionId, public readonly bool $initiatedByAdmin = false) {}

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

            // IMNEHNET_ADMIN_DELETE_PAIR_V8
            // Cleanup only terminals belonging to this exact lab.
            // No authentication context is required in the queue worker.
            $environmentIds = array_values(array_unique(
                array_filter([$attackerId, $victimId])
            ));

            $studentTerminalService = app(\App\Services\TerminalService::class);

            $studentTerminals = \App\Models\TerminalSession::query()
                ->where('user_id', $session->user_id)
                ->whereIn('purpose', ['sandbox', 'guided_ctf'])
                ->whereNull('closed_at')
                ->where(function ($query) use ($session, $environmentIds): void {
                    $query->where('lab_session_id', $session->getKey())
                        ->orWhereIn('lab_environment_id', $environmentIds);
                })
                ->get();

            foreach ($studentTerminals as $terminal) {
                $studentTerminalService->close($terminal);
            }

            app(\App\Services\Terminal\AdminEnvironmentPairTerminalService::class)
                ->closeLabSessions((int) $session->getKey());

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

        // IMNEHNET_ADMIN_DELETE_NOTIFICATION_V11
        // Notify the student only after successful paired teardown.
        $this->notifySuccessfulAdminDeletion();
    }

    /**
     * Do not fail a successfully completed infrastructure deletion
     * merely because a notification could not be delivered.
     */
    private function notifySuccessfulAdminDeletion(): void
    {
        if (! $this->initiatedByAdmin) {
            return;
        }

        try {
            $session = LabSession::query()->find($this->sessionId);

            if (
                ! $session
                || $session->status !== LabSessionStatus::Deleted
            ) {
                return;
            }

            $student = \App\Models\User::query()
                ->find($session->user_id);

            if (! $student) {
                \Illuminate\Support\Facades\Log::warning(
                    'ADMIN_LAB_DELETE_NOTIFICATION_OWNER_MISSING',
                    ['lab_session_id' => $this->sessionId]
                );

                return;
            }

            \Filament\Notifications\Notification::make()
                ->title(
                    'Your Lab Has Been Deleted by an Administrator'
                )
                ->body(
                    'Your attacker and victim environments have been '
                    .'removed by an administrator. Your previous lab is '
                    .'no longer available. You can create a new lab '
                    .'from the My Labs page.'
                )
                ->info()
                ->sendToDatabase($student);

            \Illuminate\Support\Facades\Log::notice(
                'ADMIN_LAB_DELETE_NOTIFICATION_SENT',
                [
                    'lab_session_id' => $this->sessionId,
                    'student_id' => $student->getKey(),
                ]
            );

        } catch (Throwable $exception) {
            report($exception);
        }
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

        // IMNEHNET_DELETE_STATUS_V13
        // A failed teardown must not leave environment rows DELETING.
        // Do not claim READY: infrastructure may be partially destroyed.
        foreach ([
            $session->attacker_environment_id,
            $session->victim_environment_id,
        ] as $environmentId) {
            if (! $environmentId) {
                continue;
            }

            try {
                \App\Models\LabEnvironment::query()
                    ->whereKey($environmentId)
                    ->where('user_id', $session->user_id)
                    ->where(
                        'status',
                        \App\Enums\EnvironmentStatus::Deleting->value
                    )
                    ->update([
                        'status' => \App\Enums\EnvironmentStatus::Error->value,
                        'last_error' => 'Paired lab deletion failed. Administrator review required.',
                    ]);
            } catch (Throwable $environmentError) {
                report($environmentError);
            }
        }

        try {
            ProvisioningTimeline::record($this->sessionId, 'delete-failed', 'Delete gagal. Kuota tetap terpakai; detail referensi tercantum di bawah.', 'error');
        } catch (Throwable $logError) {
            report($logError);
        }
    }
}
