<?php

namespace App\Filament\Widgets;

use App\Enums\LabSessionStatus;
use App\Models\ActivityLog;
use App\Models\LabSession;
use App\Models\User;
use App\Services\InfrastructureStatusService;
use Filament\Widgets\Widget;
use Throwable;

final class AdminDashboardOverview extends Widget
{
    protected string $view = 'filament.widgets.admin-dashboard-overview';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public int $activityDays = 30;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $activeLabs = LabSession::query()
            ->whereIn('status', [
                LabSessionStatus::Ready->value,
                LabSessionStatus::Running->value,
            ])
            ->count();

        $totalUsers = User::query()->count();

        $systemHealth = $this->systemHealth();
        $labActivity = $this->labActivity();
        $courseStatus = $this->courseStatus();

        return [
            'activeLabs' => $activeLabs,
            'totalUsers' => $totalUsers,
            'systemHealth' => $systemHealth,
            'labActivity' => $labActivity,
            'courseStatus' => $courseStatus,
            'recentActivity' => ActivityLog::query()
                ->with('actor')
                ->latest('created_at')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * @return array{label: string, percentage: ?int}
     */
    private function systemHealth(): array
    {
        try {
            $summary = app(InfrastructureStatusService::class)->summary();

            if ($summary === []) {
                return [
                    'label' => 'Not verified',
                    'percentage' => null,
                ];
            }

            $healthyStates = [
                'healthy',
                'up',
                'online',
                'ok',
                'operational',
                'ready',
                'running',
                'available',
            ];

            $verified = collect($summary)
                ->filter(fn (array $service): bool => filled($service['status'] ?? null));

            if ($verified->isEmpty()) {
                return [
                    'label' => 'Not verified',
                    'percentage' => null,
                ];
            }

            $healthy = $verified->filter(function (array $service) use ($healthyStates): bool {
                $status = strtolower((string) ($service['status'] ?? ''));

                return in_array($status, $healthyStates, true);
            })->count();

            $percentage = (int) round(($healthy / $verified->count()) * 100);

            return [
                'label' => $percentage === 100 ? 'Operational' : 'Attention needed',
                'percentage' => $percentage,
            ];
        } catch (Throwable) {
            return [
                'label' => 'Not verified',
                'percentage' => null,
            ];
        }
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function labActivity(): array
    {
        $days = in_array($this->activityDays, [7, 30, 90], true)
            ? $this->activityDays
            : 30;

        $start = now()->startOfDay()->subDays($days - 1);

        $counts = LabSession::query()
            ->whereNotNull('started_at')
            ->where('started_at', '>=', $start)
            ->get(['started_at'])
            ->groupBy(fn (LabSession $session): string => $session->started_at->format('Y-m-d'))
            ->map->count();

        $labels = [];
        $values = [];

        for ($day = 0; $day < $days; $day++) {
            $date = $start->copy()->addDays($day);
            $labels[] = $date->format('M j');
            $values[] = (int) ($counts[$date->format('Y-m-d')] ?? 0);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * @return array{completed: int, inProgress: int, notStarted: int, total: int}
     */
    private function courseStatus(): array
    {
        $students = User::query()
            ->where('role', 'student')
            ->pluck('id');

        $sessions = LabSession::query()
            ->whereIn('user_id', $students)
            ->latest('created_at')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($sessions) => $sessions->first());

        $completed = $sessions
            ->filter(fn (LabSession $session): bool => $session->status === LabSessionStatus::Completed)
            ->count();

        $inProgress = $sessions
            ->reject(fn (LabSession $session): bool => $session->status === LabSessionStatus::Completed)
            ->count();

        $notStarted = max(0, $students->count() - $sessions->count());

        return [
            'completed' => $completed,
            'inProgress' => $inProgress,
            'notStarted' => $notStarted,
            'total' => $students->count(),
        ];
    }
}
