<?php

namespace App\Filament\Widgets;

use App\Enums\EnvironmentStatus;
use App\Filament\Resources\Environments\EnvironmentResource;
use App\Models\LabEnvironment;
use App\Services\ComputePlaneInventory;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

/**
 * The things an operator has to do something about, in one place.
 *
 * This replaces a Recent Activity table that repeated the Activity Logs page
 * exactly. That page answers "what happened", which is a question about history
 * and belongs on a page of its own. This answers "what needs me now", which the
 * dashboard was not answering anywhere.
 *
 * The third tile is the one the portal could not previously ask at all: a
 * container can exist on the compute plane with no record behind it. There is one
 * right now, and nothing reported it, because every other view asks the database
 * what exists and the database does not know about that container.
 *
 * A student sees their own failures and their own stuck operations, not the
 * cohort's. The compute plane question is an operator's, so it is administrators
 * only.
 */
final class NeedsAttention extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Needs attention';

    /**
     * How long an operation may sit in flight before it counts as stuck.
     *
     * Provisioning legitimately takes minutes: it applies, then bootstraps a
     * shell over Ansible. A line that flagged a healthy six-minute create would
     * train everyone to ignore this tile, which is worse than not having it.
     */
    private const STUCK_MINUTES = 15;

    protected function getColumns(): int
    {
        return 3;
    }

    public static function canView(): bool
    {
        return auth()->user()?->isActive() === true;
    }

    protected function getStats(): array
    {
        // Through the resource query, so a student counts their own failures and
        // nobody else's. The resource is where "who may see what" is decided.
        $visible = EnvironmentResource::getEloquentQuery();

        $failed = (clone $visible)
            ->where('status', EnvironmentStatus::Error->value)
            ->get();

        $stuck = (clone $visible)
            ->whereIn('status', self::inFlightValues())
            ->where('updated_at', '<=', now()->subMinutes(self::STUCK_MINUTES))
            ->get();

        $stats = [
            Stat::make('Failed', $failed->count())
                ->description($this->describe($failed))
                ->descriptionIcon($failed->isNotEmpty() ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->color($failed->isNotEmpty() ? 'danger' : 'gray'),

            Stat::make('Stuck in flight', $stuck->count())
                ->description($stuck->isEmpty()
                    ? 'Every operation finished'
                    : $this->describe($stuck).' — over '.self::STUCK_MINUTES.' minutes old')
                ->descriptionIcon($stuck->isNotEmpty() ? 'heroicon-o-clock' : 'heroicon-o-check-circle')
                ->color($stuck->isNotEmpty() ? 'warning' : 'gray'),
        ];

        if (auth()->user()?->isAdmin() === true) {
            $stats[] = $this->unmanagedTile();
        }

        return $stats;
    }

    /**
     * @return list<string>
     */
    private static function inFlightValues(): array
    {
        $values = [];

        foreach (EnvironmentStatus::cases() as $status) {
            if ($status->isInFlight()) {
                $values[] = $status->value;
            }
        }

        return $values;
    }

    /**
     * Containers on the compute plane that no record accounts for.
     *
     * The protected reference environment is excluded upstream: it has no record
     * on purpose, so counting it here would report the same false alarm for ever.
     */
    private function unmanagedTile(): Stat
    {
        $inventory = app(ComputePlaneInventory::class)->unmanaged();

        if (! $inventory['reachable']) {
            // Not zero, and not a count. Zero during an outage is
            // indistinguishable from a clean plane, and the entire point of this
            // tile is that it cannot be read as good news when it is not known.
            return Stat::make('Unmanaged containers', 'NOT VERIFIED')
                ->description('The compute plane could not be reached')
                ->descriptionIcon('heroicon-o-question-mark-circle')
                ->color('gray');
        }

        $count = count($inventory['names']);

        return Stat::make('Unmanaged containers', $count)
            ->description($count > 0
                ? implode(', ', array_slice($inventory['names'], 0, 3)).' — running with no record'
                : 'Every container has a record')
            ->descriptionIcon($count > 0 ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle')
            ->color($count > 0 ? 'warning' : 'gray');
    }

    /**
     * Name the records, up to three, so the tile is actionable rather than a bare
     * number.
     *
     * @param  Collection<int, LabEnvironment>  $records
     */
    private function describe(Collection $records): string
    {
        if ($records->isEmpty()) {
            return 'None';
        }

        $names = $records->take(3)
            ->map(fn (LabEnvironment $record): string => (string) $record->container_name)
            ->all();

        $remaining = $records->count() - count($names);

        return implode(', ', $names)
            .($remaining > 0 ? sprintf(' and %d more', $remaining) : '');
    }
}