<?php

namespace App\Filament\Pages;

use App\Enums\ObjectiveStatus;
use App\Models\Course;
use App\Models\LabSession;
use App\Models\Objective;
use App\Models\ObjectiveProgress;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class CtfControlCenter extends Page
{
    protected static ?string $navigationLabel = 'CTF Management';
    protected static ?string $title = 'CTF Management';
    protected static ?string $slug = 'ctf-management';
    protected static ?int $navigationSort = 1;
    protected static string|\UnitEnum|null $navigationGroup = 'GUIDED CTF';
    protected string $view = 'filament.pages.ctf-control-center';

    public string $search = '';
    public string $statusFilter = 'all';
    public string $courseFilter = 'all';
    public ?int $selectedSession = null;
    public string $viewMode = 'current';
    public int $historyPage = 1;
    public string $inspectorTab = 'overview';
    public ?int $editingCourse = null;
    public string $courseTitle = '';
    public string $courseDifficulty = '';
    public string $courseSummary = '';
    public ?int $editingObjective = null;
    public string $objectiveTitle = '';
    public string $objectiveDescription = '';
    public string $objectiveHint = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin() === true, 403);
    }

    public function inspect(int $sessionId): void
    {
        $this->ensureAdmin();
        $this->selectedSession = LabSession::query()->whereKey($sessionId)->value('id');
        $this->inspectorTab = 'overview';
    }

    public function editCourse(int $id): void
    {
        $this->ensureAdmin();
        $course = Course::query()->findOrFail($id);
        $this->editingCourse = $course->id;
        $this->courseTitle = $course->title;
        $this->courseDifficulty = $course->difficulty;
        $this->courseSummary = (string) $course->summary;
        $this->editingObjective = null;
    }

    public function saveCourse(): void
    {
        $this->ensureAdmin();
        $data = $this->validate([
            'courseTitle' => 'required|string|max:128',
            'courseDifficulty' => 'required|string|max:32',
            'courseSummary' => 'nullable|string|max:5000',
        ]);
        abort_if($this->editingCourse === null, 404);
        $course = Course::query()->findOrFail($this->editingCourse);
        $course->update([
            'title' => $data['courseTitle'],
            'difficulty' => $data['courseDifficulty'],
            'summary' => $data['courseSummary'],
        ]);
        $this->editingCourse = null;
        \Filament\Notifications\Notification::make()->title('Course updated')->success()->send();
    }

    public function editObjective(int $id): void
    {
        $this->ensureAdmin();
        $objective = Objective::query()->findOrFail($id);
        abort_if(LabSession::query()->where('course_id', $objective->course_id)->exists(), 409, 'This course already has session history; editing objectives is locked.');
        $this->editingObjective = $objective->id;
        $this->objectiveTitle = $objective->title;
        $this->objectiveDescription = $objective->description;
        $this->objectiveHint = (string) $objective->hint;
        $this->editingCourse = null;
    }

    public function saveObjective(): void
    {
        $this->ensureAdmin();
        $data = $this->validate([
            'objectiveTitle' => 'required|string|max:128',
            'objectiveDescription' => 'required|string|max:10000',
            'objectiveHint' => 'nullable|string|max:5000',
        ]);
        abort_if($this->editingObjective === null, 404);
        $objective = Objective::query()->findOrFail($this->editingObjective);
        abort_if(LabSession::query()->where('course_id', $objective->course_id)->exists(), 409, 'This course already has session history; editing objectives is locked.');
        $objective->update([
            'title' => $data['objectiveTitle'],
            'description' => $data['objectiveDescription'],
            'hint' => $data['objectiveHint'],
        ]);
        $this->editingObjective = null;
        \Filament\Notifications\Notification::make()->title('Challenge updated')->success()->send();
    }

    public function cancelEdit(): void
    {
        $this->editingCourse = null;
        $this->editingObjective = null;
        $this->resetValidation();
    }

    // IMNEHNET_CTF_V3 — Read-only student monitoring; editing actions above remain unchanged.
    public function switchView(string $mode): void
    {
        $this->ensureAdmin();
        abort_unless(in_array($mode, ['current', 'history'], true), 422);
        $this->viewMode = $mode;
        $this->historyPage = 1;
        $this->statusFilter = 'all';
        $this->selectedSession = null;
    }

    public function selectInspectorTab(string $tab): void
    {
        $this->ensureAdmin();
        abort_unless(in_array($tab, ['overview', 'assessment', 'diagnostics'], true), 422);
        $this->inspectorTab = $tab;
    }

    public function closeInspector(): void
    {
        $this->selectedSession = null;
    }

    public function refreshDashboard(): void
    {
        $this->ensureAdmin();
        // Livewire polling requests a fresh render; data is read in dashboard().
    }


    // IMNEHNET_CTF_HISTORY_V35
    private function historyStatuses(): array
    {
        return ['COMPLETED', 'FAILED', 'DELETING', 'DELETED'];
    }


    // IMNEHNET_CTF_DELETE_FIX_V361
    public function deleteHistorySession(int $id): void
    {
        $this->ensureAdmin();

        $connection = (new LabSession())->getConnection();

        $connection->transaction(function () use ($id): void {
            $session = LabSession::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $status = $session->status?->value;

            abort_unless(
                in_array($status, ['DELETED', 'FAILED'], true),
                409,
                'Only FAILED or DELETED sessions can be removed.'
            );

            abort_unless(
                User::query()
                    ->whereKey($session->user_id)
                    ->where('role', 'student')
                    ->exists(),
                403,
                'Session does not belong to a student.'
            );

            abort_if(
                $session->victim_environment_id !== null ||
                $session->attacker_environment_id !== null,
                409,
                'Session still references lab environments.'
            );

            // IMNEHNET_CTF_DELETE_FIX_V362
            //
            // This action removes historical DB records only.
            // Historical container names are not reliable ownership
            // indicators because names are reused across sessions.
            //
            // Environment-ID references are checked above.
            // No Incus, OpenTofu or environment cleanup is invoked.

            $ownerId = $session->user_id;
            $courseId = $session->course_id;

            // objective_progress uses ON DELETE CASCADE.
            $session->delete();

            \Illuminate\Support\Facades\Log::notice(
                'CTF history permanently deleted',
                [
                    'session_id' => $id,
                    'student_id' => $ownerId,
                    'course_id' => $courseId,
                    'admin_id' => auth()->id(),
                ]
            );
        });

        // Archive metadata resides in the portal DB.
        try {
            \Illuminate\Support\Facades\DB::table(
                'ctf_history_archives_v35'
            )
                ->where('lab_session_id', $id)
                ->delete();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'CTF archive metadata cleanup failed',
                [
                    'session_id' => $id,
                    'error' => $e->getMessage(),
                ]
            );
        }

        if ($this->selectedSession === $id) {
            $this->selectedSession = null;
        }

        $this->historyPage = 1;

        \Filament\Notifications\Notification::make()
            ->title('CTF session permanently deleted')
            ->success()
            ->send();
    }

    public function nextHistoryPage(): void
    {
        $this->ensureAdmin();
        $this->historyPage = min(1000000, $this->historyPage + 1);
    }

    public function previousHistoryPage(): void
    {
        $this->ensureAdmin();
        $this->historyPage = max(1, $this->historyPage - 1);
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $this->ensureAdmin();
        $courses = Course::query()->withCount('objectives')->with('objectives')->orderBy('title')->get();
        $courseMap = $courses->keyBy('id');
        $studentIds = User::query()->where('role', \App\Enums\UserRole::Student->value)->pluck('id')->all();
        $users = User::query()->whereIn('id', $studentIds)->get(['id', 'name', 'email'])->keyBy('id');
        $currentStatuses = ['CREATING', 'PROVISIONING', 'CONFIGURING', 'READY', 'RUNNING', 'STOPPED', 'RESETTING'];
        $historyStatuses = ['COMPLETED', 'FAILED', 'DELETING', 'DELETED'];
        $base = LabSession::query()->whereIn('user_id', $studentIds);
        $current = (clone $base)->whereIn('status', $currentStatuses)->get();
        $activeIds = $current->pluck('id')->all();
        $counts = ObjectiveProgress::query()
            ->whereIn('lab_session_id', $activeIds)
            ->where('status', ObjectiveStatus::Completed->value)
            ->selectRaw('lab_session_id, COUNT(*) AS qty')
            ->groupBy('lab_session_id')->pluck('qty', 'lab_session_id');
        $ready = 0;
        $working = 0;
        foreach ($current as $session) {
            $done = (int) ($counts->get($session->id) ?? 0);
            if ($done > 0) {
                $working++;
            } elseif (in_array($session->status?->value, ['READY', 'RUNNING', 'STOPPED'], true)) {
                $ready++;
            }
        }
        $summary = [
            'current_students' => $current->pluck('user_id')->unique()->count(),
            'ready' => $ready,
            'in_progress' => $working,
            'completed' => (clone $base)->where('status', 'COMPLETED')->count(),
            'courses' => $courses->count(),
            'history' => (clone $base)->whereIn('status', $historyStatuses)->count(),
        ];

        $allowed = array_merge($currentStatuses, $historyStatuses);
        $query = (clone $base)->whereIn('status', $this->viewMode === 'history' ? $historyStatuses : $currentStatuses);
        if ($this->statusFilter !== 'all' && in_array($this->statusFilter, $allowed, true)) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->courseFilter !== 'all' && ctype_digit($this->courseFilter)) {
            $query->where('course_id', (int) $this->courseFilter);
        }
        $search = trim($this->search);
        if ($search !== '') {
            $matchedUsers = $users->filter(fn ($u) => Str::contains(Str::lower($u->name.' '.$u->email), Str::lower($search)))->keys()->all();
            $matchedCourses = $courses->filter(fn ($c) => Str::contains(Str::lower($c->title), Str::lower($search)))->pluck('id')->all();
            $query->where(fn ($q) => $q->whereIn('user_id', $matchedUsers)->orWhereIn('course_id', $matchedCourses));
        }
        $matched = (clone $query)->count();

        $pageCount = max(1, (int) ceil($matched / 15));
        $currentPage = min(max(1, $this->historyPage), $pageCount);

        $sessions = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->skip(($currentPage - 1) * 15)
            ->take(15)
            ->get();
        $progressCounts = ObjectiveProgress::query()
            ->whereIn('lab_session_id', $sessions->pluck('id')->all())
            ->where('status', ObjectiveStatus::Completed->value)
            ->selectRaw('lab_session_id, COUNT(*) AS qty')
            ->groupBy('lab_session_id')->pluck('qty', 'lab_session_id');
        $rows = $sessions->map(function ($session) use ($users, $courseMap, $progressCounts) {
            $total = (int) ($courseMap->get($session->course_id)?->objectives_count ?? 0);
            $done = (int) ($progressCounts->get($session->id) ?? 0);
            $status = $session->status?->value ?? 'UNKNOWN';
            $displayStatus = $status;
            if (in_array($status, ['READY', 'RUNNING', 'STOPPED'], true)) {
                $displayStatus = $done > 0 ? 'IN PROGRESS' : 'READY';
            }
            return [
                'id' => $session->id,
                'student' => $users->get($session->user_id)?->name ?? 'Student unavailable',
                'email' => $users->get($session->user_id)?->email ?? '—',
                'course' => $courseMap->get($session->course_id)?->title ?? 'Course unavailable',
                'done' => $done,
                'total' => $total,
                'percent' => $total ? min(100, (int) round($done * 100 / $total)) : 0,
                'status' => $status,
                'display_status' => $displayStatus,
                'duration' => $session->durationSeconds(),
                'updated' => $session->updated_at?->diffForHumans() ?? '—',
            ];
        });

        $selected = $this->selectedSession ? (clone $base)->whereKey($this->selectedSession)->first() : null;
        $selectedUser = $selected ? $users->get($selected->user_id) : null;
        $selectedCourse = $selected ? $courseMap->get($selected->course_id) : null;
        $steps = collect();
        $events = collect();
        if ($selected && $selectedCourse) {
            $progress = ObjectiveProgress::query()->where('lab_session_id', $selected->id)->get()->keyBy('objective_id');
            $steps = $selectedCourse->objectives->map(function ($objective) use ($progress) {
                $record = $progress->get($objective->id);
                return [
                    'sequence' => $objective->sequence,
                    'title' => $objective->title,
                    'description' => $objective->description,
                    'hint' => $objective->hint,
                    'status' => $record?->status?->value ?? 'LOCKED',
                    'completed_at' => $record?->completed_at?->format('d M Y H:i') ?? null,
                ];
            });
            $events = $steps->filter(fn ($step) => $step['completed_at'] !== null)->values();
        }
        return compact('summary', 'courses', 'rows', 'matched', 'selected', 'selectedUser', 'selectedCourse', 'steps', 'events', 'currentPage', 'pageCount');
    }
}
