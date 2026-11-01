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
        if (! $uid) {
            return ['total' => 0, 'active' => 0, 'completed' => 0, 'objectives' => 0, 'hours' => 0, 'activity' => array_fill(0, 7, 0), 'usage' => array_fill(0, 7, 0), 'history' => collect()];
        }

        $sessions = LabSession::query()
            ->where('user_id', $uid)
            ->with(['course'])
            ->orderByDesc('id')
            ->get();

        $active = $sessions->filter(fn ($s) => in_array($s->status?->value ?? $s->status, ['READY', 'RUNNING'], true))->count();
        $totalCompleted = 0;
        $totalObjectives = 0;
        $totalSeconds = 0;
        $activity = array_fill(0, 7, 0);
        $usage = array_fill(0, 7, 0);
        $days = collect(range(6, 0))->map(fn ($i) => now()->startOfDay()->subDays($i));

        foreach ($sessions as $session) {
            $counts = $session->progressCount();
            $totalCompleted += $counts['completed'];
            $totalObjectives += $counts['total'];
            $duration = $session->durationSeconds();
            $totalSeconds += $duration ?? 0;
            if ($session->started_at) {
                foreach ($days as $index => $day) {
                    $activity[$index]++;
                    $start = $session->started_at;
                    $end = $session->completed_at ?? now();
                    $from = $start->greaterThan($day) ? $start : $day;
                    $until = $end->lessThan($day->copy()->addDay()) ? $end : $day->copy()->addDay();
                    if ($until->greaterThan($from)) {
                        $usage[$index] += $from->diffInSeconds($until) / 3600;
                    }
                }
            }
        }

        return [
            'total' => $sessions->count(),
            'active' => $active,
            'completed' => $totalCompleted,
            'objectives' => $totalObjectives,
            'hours' => round($totalSeconds / 3600, 1),
            'activity' => $activity,
            'usage' => array_map(fn ($v) => round($v, 2), $usage),
            'history' => $sessions->take(10),
            'days' => $days->map(fn ($d) => $d->format('D'))->all(),
        ];
    }


    public static function canAccess(): bool

    {

        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'student'

            && auth()->user()?->isActive() === true;

    }


}
