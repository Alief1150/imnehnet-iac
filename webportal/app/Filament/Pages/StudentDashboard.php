<?php

namespace App\Filament\Pages;

use App\Models\LabSession;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Panel;

class StudentDashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.student-dashboard';

    protected static ?int $navigationSort = 1;

    public static function getSlug(?Panel $panel = null): string
    {
        return '';
    }

    /**
     * The home route belongs to one panel at a time.
     *
     * Filament's base Dashboard sets routePath to "/", which means every
     * dashboard is the home of whatever panel registers it. The student panel
     * wants this page at its own home, and the admin panel wants its own
     * Dashboard there instead - but the admin panel discovers every page in
     * app/Filament/Pages, so both classes claimed the same route and the
     * admin's Dashboard lost. Its route was never registered, so
     * Dashboard::getUrl() threw, and the global search - which asks every
     * page for its URL - took down every admin page with it.
     *
     * The path is therefore decided by the panel: the student panel gets
     * the home, the admin panel gets a page of its own that does not
     * compete for it.
     */
    public static function getRoutePath(Panel $panel): string
    {
        return $panel->getId() === 'student' ? '/' : '/student-dashboard';
    }

    public function getHeading(): string
    {
        return 'Dashboard';
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    public function dashboardData(): array
    {
        $uid = auth()->id();
        $days = collect(range(6, 0))->map(fn ($i) => now()->startOfDay()->subDays($i));
        $empty = [
            'total' => 0, 'active' => 0, 'completed' => 0, 'objectives' => 0,
            'hours' => 0, 'activity' => array_fill(0, 7, 0),
            'usage' => array_fill(0, 7, 0), 'history' => collect(),
            'days' => $days->map(fn ($day) => $day->format('D'))->all(),
            'course_name' => 'Web Server Breach',
        ];
        if (! $uid) {
            return $empty;
        }

        $sessions = LabSession::query()->where('user_id', $uid)
            ->with('course')->orderByDesc('id')->get();
        $active = $sessions->filter(fn ($s) => in_array(
            $s->status?->value ?? $s->status, ['READY', 'RUNNING'], true
        ))->count();

        // The course is identified by its persisted slug, not by session count.
        // Prefer the student's most recent course, otherwise use the MVP course.
        $course = $sessions->first()?->course
            ?? \App\Models\Course::webServerBreach();
        $courseSessions = $course
            ? $sessions->where('course_id', $course->id)
            : collect();
        $latestAttempt = $courseSessions->first();
        $objectives = $course?->objectives()->count() ?? 0;
        $completed = $latestAttempt
            ? min($objectives, $latestAttempt->progress()
                ->where('status', \App\Enums\ObjectiveStatus::Completed->value)
                ->count())
            : 0;

        // Daily session activity counts only actual starts on that date.
        $activity = array_fill(0, 7, 0);
        foreach ($sessions as $session) {
            if (! $session->started_at) {
                continue;
            }
            foreach ($days as $i => $day) {
                if ($session->started_at->isSameDay($day)) {
                    $activity[$i]++;
                    break;
                }
            }
        }

        // Terminal activity is the closest existing evidence of active pentesting.
        // Do not treat a provisioned but idle VM as hours of usage.
        $usageSeconds = array_fill(0, 7, 0);
        $totalSeconds = 0;
        $environmentIds = $sessions->pluck('attacker_environment_id')
            ->filter()->unique()->values()->all();
        if ($environmentIds) {
            $terminals = \App\Models\TerminalSession::query()
                ->where('user_id', $uid)
                ->whereIn('lab_environment_id', $environmentIds)
                ->get(['created_at', 'closed_at', 'expires_at']);
            foreach ($terminals as $terminal) {
                $start = $terminal->created_at;
                if (! $start) {
                    continue;
                }
                $end = $terminal->closed_at ?? $terminal->expires_at;
                if (! $end) {
                    continue;
                }
                if ($end->isFuture()) {
                    $end = now();
                }
                if ($end->lessThanOrEqualTo($start)) {
                    continue;
                }
                $totalSeconds += $start->diffInSeconds($end);
                foreach ($days as $i => $day) {
                    $dayEnd = $day->copy()->addDay();
                    $from = $start->greaterThan($day) ? $start : $day;
                    $until = $end->lessThan($dayEnd) ? $end : $dayEnd;
                    if ($until->greaterThan($from)) {
                        $usageSeconds[$i] += $from->diffInSeconds($until);
                    }
                }
            }
        }

                // IMNEHNET_LAB_TIME_V3: authoritative aggregate for dashboard display.
        // CLOSED sessions without completed_at are excluded (no reliable end time).
        $labTimeSeconds = 0;
        $labTimeActiveCount = 0;
        foreach ($sessions as $timeSession) {
            if (! $timeSession->started_at) {
                continue;
            }
            $timeStatus = $timeSession->status?->value ?? (string) $timeSession->status;
            if ($timeSession->completed_at) {
                $labTimeSeconds += max(0, (int) $timeSession->started_at->diffInSeconds($timeSession->completed_at));
            } elseif (in_array($timeStatus, ['READY', 'RUNNING'], true)) {
                $labTimeSeconds += max(0, (int) $timeSession->started_at->diffInSeconds(now()));
                $labTimeActiveCount++;
            }
        }

return [
            'total' => $sessions->count(),
            'lab_time_seconds' => $labTimeSeconds,
            'lab_time_active_count' => $labTimeActiveCount, 'active' => $active,
            'completed' => $completed, 'objectives' => $objectives,
            'hours' => round($totalSeconds / 3600, 1),
            'activity' => $activity,
            'usage' => array_map(fn ($seconds) => round($seconds / 3600, 2), $usageSeconds),
            'history' => $sessions->take(10),
            'days' => $empty['days'],
            'course_name' => $course?->title ?? 'Web Server Breach',
        ];
    }


    public static function canAccess(): bool

    {

        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'student'

            && auth()->user()?->isActive() === true;

    }


}
