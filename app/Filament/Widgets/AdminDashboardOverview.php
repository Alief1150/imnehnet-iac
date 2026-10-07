<?php

namespace App\Filament\Widgets;

use App\Enums\LabSessionStatus;
use App\Models\ActivityLog;
use App\Models\Course;
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

        return [
            'activeLabs' => $activeLabs,
            'totalUsers' => $totalUsers,
            'systemHealth' => $systemHealth,
            'labActivity' => $labActivity,
            'courseProgress' => $this->courseProgress(),
            'labActivityTrend' => $this->labActivityTrend(),
            'userGrowthTrend' => $this->userGrowthTrend(),
            'recentActivity' => ActivityLog::query()
                ->with('actor')
                ->latest('created_at')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * Who is on the platform right now.
     *
     * Replaces a progress donut that had nothing to divide up. The guided course exists
     * in the database but is not yet published to students, so a card showing how many
     * students had completed it, were working on it, or had not started was answering a
     * question about a course nobody can open — and the seeded session in it made it look
     * as though the cohort were already progressing.
     *
     * What an administrator can actually act on before a course opens is who has an
     * account and what role it carries. That is real, it is what the next step of setting
     * the platform up needs, and it does not imply a course is running.
     *
     * @return array<string, int|bool>
     */
    /**
     * Student progress through the MVP guided course.
     *
     * Count students, not lab attempts. A student is completed when at least one
     * session for the course is completed. Any student with a course session but
     * no completed attempt is in progress. Students with no session are not started.
     *
     * @return array{completed: int, inProgress: int, notStarted: int, total: int}
     */
    private function courseProgress(): array
    {
        $course = Course::webServerBreach();

        $studentIds = User::query()
            ->where('role', 'student')
            ->pluck('id');

        $total = $studentIds->count();

        if ($course === null || $total === 0) {
            return [
                'completed' => 0,
                'inProgress' => 0,
                'notStarted' => $total,
                'total' => $total,
            ];
        }

        $sessionsByStudent = LabSession::query()
            ->where('course_id', $course->getKey())
            ->whereIn('user_id', $studentIds)
            ->get(['user_id', 'status'])
            ->groupBy('user_id');

        $completed = 0;
        $inProgress = 0;

        foreach ($studentIds as $studentId) {
            $sessions = $sessionsByStudent->get($studentId, collect());

            if ($sessions->contains(
                fn (LabSession $session): bool =>
                    $session->status === LabSessionStatus::Completed
            )) {
                $completed++;

                continue;
            }

            if ($sessions->isNotEmpty()) {
                $inProgress++;
            }
        }

        return [
            'completed' => $completed,
            'inProgress' => $inProgress,
            'notStarted' => max(0, $total - $completed - $inProgress),
            'total' => $total,
        ];
    }

    private function accounts(): array
    {
        $since = now()->subDays(30);

        return [
            'total' => User::query()->count(),
            'students' => User::query()->where('role', 'student')->count(),
            'administrators' => User::query()->where('role', 'admin')->count(),
            'lecturers' => User::query()->where('role', 'lecturer')->count(),
            'newInPeriod' => User::query()->where('created_at', '>=', $since)->count(),
            'active' => User::query()->where('status', 'active')->count(),

            // Said plainly on the card so a reader is not left wondering why a guided
            // CTF platform has nothing to guide anyone through yet.
            'coursePublished' => false,
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
     * @return array{value: ?float, direction: string, label: string}
     */
    private function labActivityTrend(): array
    {
        $days = in_array($this->activityDays, [7, 30, 90], true)
            ? $this->activityDays
            : 30;

        $currentStart = now()->startOfDay()->subDays($days - 1);
        $previousEnd = $currentStart->copy()->subSecond();
        $previousStart = $currentStart->copy()->subDays($days);

        $current = LabSession::query()
            ->whereNotNull('started_at')
            ->where('started_at', '>=', $currentStart)
            ->count();

        $previous = LabSession::query()
            ->whereNotNull('started_at')
            ->whereBetween('started_at', [$previousStart, $previousEnd])
            ->count();

        return $this->percentageTrend(
            $current,
            $previous,
            'lab starts vs previous period',
        );
    }

    /**
     * @return array{value: ?float, direction: string, label: string}
     */
    private function userGrowthTrend(): array
    {
        $days = in_array($this->activityDays, [7, 30, 90], true)
            ? $this->activityDays
            : 30;

        $currentStart = now()->startOfDay()->subDays($days - 1);
        $previousEnd = $currentStart->copy()->subSecond();
        $previousStart = $currentStart->copy()->subDays($days);

        $current = User::query()
            ->where('created_at', '>=', $currentStart)
            ->count();

        $previous = User::query()
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();

        return $this->percentageTrend(
            $current,
            $previous,
            'new users vs previous period',
        );
    }

    /**
     * @return array{value: ?float, direction: string, label: string}
     */
    private function percentageTrend(int $current, int $previous, string $label): array
    {
        if ($previous === 0) {
            return [
                'value' => $current > 0 ? null : 0.0,
                'direction' => $current > 0 ? 'new' : 'flat',
                'label' => $label,
            ];
        }

        $value = round((($current - $previous) / $previous) * 100, 1);

        return [
            'value' => abs($value),
            'direction' => $value > 0 ? 'up' : ($value < 0 ? 'down' : 'flat'),
            'label' => $label,
        ];
    }

    }
