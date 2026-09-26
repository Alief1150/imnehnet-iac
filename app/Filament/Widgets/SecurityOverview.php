<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\MapsStatusColor;
use App\Services\SecurityMonitoringService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SecurityOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    use MapsStatusColor;

    protected ?string $heading = 'Security Monitoring';

    protected ?string $description = 'SIEM reachability and student isolation policy state. Values are read from Wazuh and the portal database; nothing is simulated.';

    protected int|array|null $columns = ['default' => 1, 'sm' => 2];

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        try {
            $summary = app(SecurityMonitoringService::class)->summary();
        } catch (Throwable $exception) {
            Log::error('Security overview widget failed.', ['error' => $exception->getMessage()]);

            return [
                Stat::make('Security Monitoring', 'Unavailable')
                    ->description('The security layer could not be queried.')
                    ->color('danger'),
            ];
        }

        $wazuh = $summary['wazuh'] ?? null;
        $isolation = $summary['isolation'] ?? null;

        $stats = [];

        if (is_array($wazuh)) {
            $stats[] = Stat::make('Wazuh SIEM', $this->statusLabel($wazuh['status']))
                ->description($wazuh['summary'])
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($this->statusColor($wazuh['status']))
                ->icon('heroicon-m-shield-check');
        }

        if (is_array($isolation)) {
            $stats[] = Stat::make('Student Isolation', $this->statusLabel($isolation['status']))
                ->description($isolation['summary'])
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color($this->statusColor($isolation['status']))
                ->icon('heroicon-m-rectangle-group');
        }

        if ($stats === []) {
            return [
                Stat::make('Security Monitoring', 'Not Connected')
                    ->description('No security backend has been integrated with the control plane.')
                    ->color('gray'),
            ];
        }

        return $stats;
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }
}
