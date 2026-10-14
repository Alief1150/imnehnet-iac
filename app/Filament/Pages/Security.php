<?php

namespace App\Filament\Pages;

use App\Services\MonitoringService;
use App\Services\SecurityDashboard;
use App\Services\SecurityTelemetryCollector;
use App\Support\Lucide;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

/**
 * Security posture, reported by the SIEM and by portal state.
 *
 * Nothing on this page is simulated: alert counts come from the Wazuh alert
 * log the collector reads every minute, failed sign-ins from the portal log
 * and the host auth logs, and isolation from live labs against the live packet
 * filter. A backend with nothing behind it renders as unknown with the reason
 * beside it.
 */
final class Security extends Page
{
    protected string $view = 'filament.pages.security';

    /**
     * How far back the trend looks. Capped at what the retained alert hours
     * cover, so a range never outruns its data.
     */
    #[Url(as: 'range')]
    public string $range = '24h';

    public function mount(): void
    {
        if (! array_key_exists($this->range, SecurityDashboard::RANGES)) {
            $this->range = '24h';
        }
    }

    public function selectedRange(): string
    {
        return array_key_exists($this->range, SecurityDashboard::RANGES)
            ? $this->range
            : '24h';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function kpis(): array
    {
        return app(SecurityDashboard::class)->kpis(
            $this->memoTelemetry(),
            app(SecurityTelemetryCollector::class)->isolationHistory(),
        );
    }

    /**
     * @return array{series: list<array{0: float, 1: float}>, from_label: string|null, to_label: string|null, coverage: string}
     */
    public function alertTrend(): array
    {
        $telemetry = $this->memoTelemetry();

        return app(SecurityDashboard::class)->trend(
            $telemetry['alerts']['hourly'] ?? [],
            $this->selectedRange(),
        );
    }

    /**
     * @return array{total: int, slices: list<array{label: string, count: int, share: float, color: string}>}
     */
    public function severityDonut(): array
    {
        $telemetry = $this->memoTelemetry();

        return app(SecurityDashboard::class)->donut($telemetry['alerts']['day'] ?? null);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentAlerts(): array
    {
        return $this->memoTelemetry()['alerts']['recent'] ?? [];
    }

    /**
     * Consoles and APIs worth opening, with whether they answer.
     *
     * Reuses the monitoring backends rather than probing twice: the same
     * Grafana and Wazuh endpoints, one verdict everywhere.
     *
     * @return list<array<string, mixed>>
     */
    public function integrations(): array
    {
        $wanted = ['grafana', 'wazuh_dashboard', 'wazuh_api'];

        return array_values(array_filter(
            app(MonitoringService::class)->backends(),
            fn (array $backend): bool => in_array($backend['key'] ?? '', $wanted, true),
        ));
    }

    /**
     * @return array{at: int|null, stale: bool, errors: list<string>}
     */
    public function freshness(): array
    {
        $telemetry = $this->memoTelemetry();
        $errors = [];
        $stale = false;
        $any = false;

        foreach (['alerts', 'failed_logins', 'isolation'] as $section) {
            $part = $telemetry[$section] ?? null;

            if (! is_array($part) || ! ($part['ok'] ?? false)) {
                continue;
            }

            $any = true;

            if ($part['stale'] ?? false) {
                $stale = true;

                if (is_string($part['error'] ?? null) && $part['error'] !== '') {
                    $errors[] = $part['error'];
                }
            }
        }

        $at = $telemetry['collected_at'] ?? null;

        return [
            'at' => is_int($at) ? $at : null,
            'stale' => $stale || ! $any,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * @return array{expected: int|null, reporting: int|null, missing: list<string>, state: string}
     */
    public function visibility(): array
    {
        return app(SecurityDashboard::class)->visibility(
            $this->memoTelemetry(),
        );
    }

    /** @var array<string, mixed>|null */
    private ?array $memoTelemetry = null;

    /**
     * @return array<string, mixed>
     */
    private function memoTelemetry(): array
    {
        return $this->memoTelemetry ??= app(SecurityTelemetryCollector::class)->telemetry();
    }

    /**
     * @return array<string, mixed>
     */
    public function chart(): array
    {
        return app(SecurityDashboard::class)->chart(
            $this->memoTelemetry()['alerts']['hourly'] ?? [],
            $this->selectedRange(),
        );
    }

    /**
     * @return array{ok: bool, rows: list<array<string, mixed>>}
     */
    public function coverage(): array
    {
        return app(SecurityDashboard::class)->coverage(
            $this->memoTelemetry(),
        );
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function getNavigationIcon(): Htmlable|string|null
    {
        return Lucide::icon('shield-check');
    }

    protected static string|\UnitEnum|null $navigationGroup = 'INFRASTRUCTURE';

    protected static ?int $navigationSort = 30;
}
