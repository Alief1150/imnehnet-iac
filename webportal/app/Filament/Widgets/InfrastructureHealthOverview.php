<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\MapsStatusColor;
use App\Services\InfrastructureStatusService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class InfrastructureHealthOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    use MapsStatusColor;

    protected ?string $heading = 'Infrastructure Health';

    protected ?string $description = 'Each card is a live probe. A card that cannot be verified reports its real state rather than an optimistic one.';

    protected int|array|null $columns = ['default' => 1, 'sm' => 2, 'lg' => 3];

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        try {
            $summary = app(InfrastructureStatusService::class)->summary();
        } catch (Throwable $exception) {
            Log::error('Infrastructure health widget failed.', ['error' => $exception->getMessage()]);

            return [
                Stat::make('Infrastructure', 'Unavailable')
                    ->description('The infrastructure probe could not be completed.')
                    ->color('danger'),
            ];
        }

        $stats = [];

        foreach ($summary as $key => $service) {
            $stats[] = Stat::make(
                $service['label'] ?? ucfirst($key),
                $this->statusLabel($service['status'] ?? null),
            )
                ->description($this->describe($service))
                ->descriptionIcon('heroicon-m-signal')
                ->color($this->statusColor($service['status'] ?? null))
                ->icon($this->iconFor($key));
        }

        return $stats === []
            ? [Stat::make('Infrastructure', 'Unknown')->description('No services were probed.')->color('gray')]
            : $stats;
    }

    /** @param array<string, mixed> $service */
    private function describe(array $service): string
    {
        $parts = [(string) ($service['summary'] ?? '')];

        if ($service['latency_ms'] !== null) {
            $parts[] = $service['latency_ms'].' ms';
        }

        $parts[] = $this->relativeTime($service['checked_at'] ?? null);

        return implode(' · ', array_filter($parts));
    }

    private function iconFor(string $key): string
    {
        return match ($key) {
            'iaas_mgmt' => 'heroicon-m-computer-desktop',
            'lab_env' => 'heroicon-m-server',
            'incus' => 'heroicon-m-cube-transparent',
            'database' => 'heroicon-m-circle-stack',
            'prometheus' => 'heroicon-m-chart-bar-square',
            'grafana' => 'heroicon-m-chart-pie',
            'blackbox' => 'heroicon-m-signal',
            'automation' => 'heroicon-m-cog-6-tooth',
            'wazuh' => 'heroicon-m-shield-exclamation',
            default => 'heroicon-m-server-stack',
        };
    }

    private function relativeTime(mixed $checkedAt): string
    {
        if (! is_string($checkedAt)) {
            return 'never checked';
        }

        try {
            return Carbon::parse($checkedAt)->diffForHumans();
        } catch (Throwable) {
            return 'checked recently';
        }
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }
}
