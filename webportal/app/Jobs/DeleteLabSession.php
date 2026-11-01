<?php

namespace App\Jobs;

use App\Enums\LabSessionStatus;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Services\GuidedCtf\PairedLabTeardownService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DeleteLabSession implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 2400;

    public function __construct(public readonly int $sessionId) {}

    public function handle(PairedLabTeardownService $teardown): void
    {
        Cache::lock(
            'imnehnet:ctf:session-lifecycle:'.$this->sessionId,
            2700
        )->block(60, function () use ($teardown): void {
            $session = LabSession::query()->find($this->sessionId);

            if (
                $session === null
                || $session->status === LabSessionStatus::Deleted
                || $session->status !== LabSessionStatus::Deleting
            ) {
                return;
            }

            try {
                $session->load(['victimEnvironment', 'attackerEnvironment']);

                // Firewall, both OpenTofu states, and Incus absence must
                // be verified before changing ownership or quota.
                $teardown->teardown($session);

                DB::connection(
                    (string) config('infrastructure.environment_connection')
                )->transaction(function () use ($session): void {
                    $session->refresh();

                    if ($session->status !== LabSessionStatus::Deleting) {
                        throw new \RuntimeException(
                            'Lab status changed during teardown.'
                        );
                    }

                    $victimId = $session->victim_environment_id;
                    $attackerId = $session->attacker_environment_id;

                    // Preserve the session and objective history.
                    // Release environment/IP reservations only after
                    // both infrastructure halves are verified absent.
                    $session->forceFill([
                        'victim_environment_id' => null,
                        'attacker_environment_id' => null,
                        'status' => LabSessionStatus::Deleted,
                        'last_error' => null,
                    ])->save();

                    foreach (array_unique(array_filter([
                        $victimId,
                        $attackerId,
                    ])) as $environmentId) {
                        LabEnvironment::query()
                            ->whereKey($environmentId)
                            ->where('user_id', $session->user_id)
                            ->delete();
                    }
                });
            } catch (Throwable $exception) {
                // Fail closed: DELETING still consumes the 1/1 quota.
                // The user may explicitly retry the idempotent teardown.
                $session->refresh();

                if ($session->status === LabSessionStatus::Deleting) {
                    $session->forceFill([
                        'last_error' => 'Lab cleanup failed. Retry Delete or contact administrator.',
                    ])->save();
                }

                report($exception);
            }
        });
    }
}
