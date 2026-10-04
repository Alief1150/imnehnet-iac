<?php

namespace App\Jobs;

use App\Enums\LabSessionStatus;
use App\Models\LabSession;
use App\Services\GuidedCtf\LabFirewall;
use App\Services\GuidedCtf\LabScenario;
use App\Services\OpenTofuService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Return a lab to its starting state, keeping the progress.
 *
 * The specification settles the MVP decision directly: resetting the environment does not
 * reset progress. A student who damages the victim does not lose the objectives they
 * already earned, and the flag stays the one they already submitted — a flag that changed
 * on reset would silently invalidate work already submitted against it.
 *
 * So what is rebuilt is the scenario, not the course: the service files, the unit and the
 * flag are put back, and nothing touches objective_progress.
 *
 * The pair stays permitted throughout. Withdrawing it and re-adding it would open a window
 * in which the lab exists but does not work, which is the same window a rebuild already
 * opens, and there is nothing to gain from a second one.
 */
final class ResetLabSession implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** The web service and its dependencies. No container is created or destroyed. */
    public int $timeout = 1800;

    public function __construct(public readonly int $sessionId) {}

    public function handle(LabScenario $scenario, LabFirewall $firewall): void
    {
        $session = LabSession::query()->find($this->sessionId);

        if ($session === null) {
            // Deleted while the job waited. Nothing to reset.
            return;
        }

        $session->forceFill(['status' => LabSessionStatus::Resetting])->save();

        // Guarded rather than trusted: a lab whose route was never permitted cannot be
        // repaired by rebuilding the target, and saying so beats a reset that appears to
        // succeed and leaves the lab unreachable.
        if (! $firewall->permits($session)) {
            $session->forceFill(['status' => LabSessionStatus::Failed])->save();

            return;
        }

        $scenario->apply($session->refresh());

        // Ready rather than Completed: the lab works again, and the course it belongs to
        // is finished if it was. The results page reads from progress, not from here.
        $session->forceFill(['status' => LabSessionStatus::Ready])->save();
    }
}