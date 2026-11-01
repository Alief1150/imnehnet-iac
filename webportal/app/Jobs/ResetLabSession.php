<?php

namespace App\Jobs;

use App\Enums\LabSessionStatus;
use App\Enums\ObjectiveStatus;
use App\Models\LabSession;
use App\Services\GuidedCtf\CtfEvidence;
use App\Services\GuidedCtf\LabFirewall;
use App\Services\GuidedCtf\LabScenario;
use App\Services\GuidedCtf\LifecycleFailure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ResetLabSession implements ShouldQueue
{
    use Queueable;

    public bool $failOnTimeout = true;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly int $sessionId) {}

    public function failed(?Throwable $error): void
    {
        LifecycleFailure::record($this->sessionId, 'Reset Lab', $error ?? new \RuntimeException('Worker timed out.'));
    }

    public function handle(LabScenario $scenario, LabFirewall $firewall): void
    {
        Cache::lock(
            'imnehnet:ctf:session-lifecycle:'.$this->sessionId,
            2100
        )->block(60, function () use ($scenario, $firewall): void {
            $session = LabSession::query()->find($this->sessionId);

            // A queued reset must never resurrect a deleted or deleting lab.
            if (
                $session === null ||
                $session->status !== LabSessionStatus::Resetting
            ) {
                return;
            }

            try {
                if (! $firewall->permits($session)) {
                    throw new \RuntimeException(
                        'The lab pair is not permitted by the firewall.'
                    );
                }

                app(CtfEvidence::class)->reset($session);
                // Restore the victim before clearing earned progress.
                $scenario->apply($session);

                DB::connection(
                    (string) config('infrastructure.environment_connection')
                )->transaction(function () use ($session): void {
                    $session->refresh();

                    if ($session->status !== LabSessionStatus::Resetting) {
                        throw new \RuntimeException(
                            'Lab status changed during reset.'
                        );
                    }

                    $progress = $session->progress()
                        ->orderBy('objective_id')
                        ->get();

                    // Do not report a successful reset unless every course
                    // objective has exactly one progress record to reset.
                    $objectiveIds = $session->course
                        ?->objectives()
                        ->orderBy('sequence')
                        ->pluck('id');

                    if (
                        $objectiveIds === null
                        || $objectiveIds->isEmpty()
                        || $objectiveIds->count() !== $progress->count()
                        || $objectiveIds->contains(
                            fn ($objectiveId): bool => ! $progress->contains(
                                'objective_id',
                                $objectiveId,
                            )
                        )
                    ) {
                        throw new \RuntimeException(
                            'Lab progress records do not match the course objectives.'
                        );
                    }

                    // Use the course sequence, not objective IDs.
                    $firstObjectiveId = $objectiveIds->first();

                    foreach ($progress as $objective) {
                        $objective->forceFill([
                            'status' => (int) $objective->objective_id === (int) $firstObjectiveId
                                ? ObjectiveStatus::Active
                                : ObjectiveStatus::Locked,
                            'completed_at' => null,
                            'evidence' => null,
                        ])->save();
                    }

                    // IMNEHNET_CTF_ATTEMPT_DURATION_V1
                    // Reset starts a new attempt, not the original lab timer.
                    $session->forceFill([
                        'current_objective' => 1,
                        'started_at' => now(),
                        'completed_at' => null,
                        'status' => LabSessionStatus::Ready,
                        'last_error' => null,
                    ])->save();
                });
            } catch (Throwable $exception) {
                $session->refresh();

                if ($session->status === LabSessionStatus::Resetting) {
                    $session->forceFill([
                        'status' => LabSessionStatus::Failed,
                        'last_error' => LifecycleFailure::record($this->sessionId, 'Reset Lab', $exception),
                    ])->save();
                }

                report($exception);
            }
        });
    }
}
