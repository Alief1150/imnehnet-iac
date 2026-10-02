<?php

namespace App\Filament\Concerns;

use App\Services\InfrastructureStatusService;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Shared rendering for the per-plane health pages.
 *
 * IAAS-MGMT and LAB-ENV are two hosts that fail independently, and the summary
 * already reports them separately for exactly that reason. Splitting them into
 * their own pages is a view over that same probe data, not a second probe: there
 * is one probe per component and both pages read the same cached result, so
 * opening one cannot disagree with the other or cost an extra round trip.
 */
trait ReportsHealthFor
{
    /**
     * The summary groups this page shows.
     *
     * @return list<string>
     */
    abstract protected function healthGroups(): array;

    abstract protected function healthHeading(): string;

    abstract protected function healthDescription(): string;

    /**
     * A probe that throws must not take the page down with it.
     *
     * The failure is shown as a page rather than swallowed: an operator who
     * cannot see the page needs to know the reason, and an empty grid would look
     * the same as a host with nothing on it.
     *
     * @return array<string, array<string, mixed>>
     */
    public function healthSummary(): array
    {
        try {
            return app(InfrastructureStatusService::class)->summary();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @return array<string, Collection<int, array<string, mixed>>>
     */
    public function healthSections(): array
    {
        $summary = $this->healthSummary();

        $sections = [];

        foreach ($this->healthGroups() as $group) {
            $entries = collect($summary)->where('group', $group);

            if ($entries->isNotEmpty()) {
                $sections[$group] = $entries;
            }
        }

        return $sections;
    }

    /**
     * What to show when the probe itself failed, rather than a host with nothing
     * wrong with it. Distinguishing the two matters: an empty grid is a healthy
     * answer to "what did you find", and a failed probe is not an answer at all.
     */
    public function healthProbeFailed(): bool
    {
        return $this->healthSummary() === [];
    }
}
