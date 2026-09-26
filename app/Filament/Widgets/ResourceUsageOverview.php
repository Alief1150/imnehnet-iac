<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\MapsStatusColor;
use App\Services\MonitoringService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ResourceUsageOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    use MapsStatusColor;

    protected ?string $heading = 'Resource Usage';

    protected ?string $description = 'Live host capacity as reported by the Prometheus node exporter.';

    protected int|array|null $columns = 4;

    protected function getStats(): array
    {
        try {
            $usage = app(MonitoringService::class)->summary();
            $trend = app(MonitoringService::class)->trend();
        } catch (Throwable $exception) {
            Log::error('Resource usage widget failed.', ['error' => $exception->getMessage()]);

            return [
                Stat::make('Resource Usage', 'Unavailable')
                    ->description('The metrics backend could not be queried.')
                    ->color('danger'),
            ];
        }

        if ($usage === []) {
            return [
                Stat::make('Resource Usage', 'Not Configured')
                    ->description('No metrics backend is configured on the control plane.')
                    ->color('gray'),
            ];
        }

        $stats = [];

        foreach (['cpu', 'memory', 'storage'] as $key) {
            $entry = $usage[$key] ?? null;

            if (! is_array($entry)) {
                continue;
            }

            $samples = $trend[$key]['samples'] ?? [];
            $checkedAt = $this->relativeTime($entry['checked_at'] ?? null);

            $stat = Stat::make(
                $entry['label'],
                $entry['used_percent'] === null ? $this->statusLabel($entry['status']) : $entry['used_percent'].'%',
            )
                ->description($this->describe($entry))
                ->descriptionIcon('heroicon-m-clock')
                ->color($this->statusColor($entry['status']))
                ->icon($this->iconFor($key));

            if (is_array($samples) && $samples !== []) {
                $stat = $stat->chart($samples)->chartColor($this->statusColor($entry['status']));
            }

            $stats[] = $stat;
        }

        $containers = $usage['containers'] ?? null;

        if (is_array($containers)) {
            $stats[] = Stat::make(
                $containers['label'],
                $containers['count'] === null ? $this->statusLabel($containers['status']) : (string) $containers['count'],
            )
                ->description($containers['summary'])
                ->descriptionIcon('heroicon-m-cube')
                ->color($this->statusColor($containers['status']))
                ->icon('heroicon-m-server-stack');
        }

        return $stats;
    }

    /** @param array<string, mixed> $entry */
    private function describe(array $entry): string
    {
        $parts = [];

        if ($entry['used_bytes'] !== null && $entry['total_bytes'] !== null) {
            $parts[] = sprintf(
                '%s of %s used',
                $this->humanBytes((int) $entry['used_bytes']),
                $this->humanBytes((int) $entry['total_bytes']),
            );
        } else {
            $parts[] = (string) $entry['summary'];
        }

        $parts[] = $this->relativeTime($entry['checked_at'] ?? null);

        return implode(' · ', array_filter($parts));
    }

    private function iconFor(string $key): string
    {
        return match ($key) {
            'cpu' => 'heroicon-m-cpu-chip',
            'memory' => 'heroicon-m-circle-stack',
            'storage' => 'heroicon-m-hard-drive-stack',
            default => 'heroicon-m-chart-bar',
        };
    }

    private function relativeTime(mixed $checkedAt): string
    {
        if (! is_string($checkedAt)) {
            return 'never checked';
        }

        try {
            return 'checked '.Carbon::parse($checkedAt)->diffForHumans();
        } catch (Throwable) {
            return 'checked recently';
        }
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return sprintf('%.1f %s', $value, $units[$unit]);
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }
}
