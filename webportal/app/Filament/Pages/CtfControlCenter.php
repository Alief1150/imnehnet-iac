<?php

namespace App\Filament\Pages;

use App\Enums\ObjectiveStatus;
use App\Models\Course;
use App\Models\LabSession;
use App\Models\Objective;
use App\Models\ObjectiveProgress;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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
        Notification::make()->title('Course updated')->success()->send();
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
        Notification::make()->title('Challenge updated')->success()->send();
    }

    public function cancelEdit(): void
    {
        $this->editingCourse = null;
        $this->editingObjective = null;
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $this->ensureAdmin();
        $courses = Course::query()->withCount('objectives')->orderBy('title')->get();
        $sessionQuery = LabSession::query();
        $totalSessions = (clone $sessionQuery)->count();
        $summary = [
            'participants' => (clone $sessionQuery)->distinct()->count('user_id'),
            'courses' => $courses->count(),
            'in_progress' => (clone $sessionQuery)->whereIn('status', ['CREATING', 'PROVISIONING', 'CONFIGURING', 'READY', 'RUNNING', 'RESETTING', 'STOPPED'])->count(),
            'completed' => (clone $sessionQuery)->where('status', 'COMPLETED')->count(),
            'failed' => (clone $sessionQuery)->where('status', 'FAILED')->count(),
            'sessions' => $totalSessions,
        ];

        $query = LabSession::query();
        $allowed = ['CREATING', 'PROVISIONING', 'CONFIGURING', 'READY', 'RUNNING', 'COMPLETED', 'FAILED', 'STOPPED', 'RESETTING', 'DELETING', 'DELETED'];
        if ($this->statusFilter !== 'all' && in_array($this->statusFilter, $allowed, true)) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->courseFilter !== 'all' && ctype_digit($this->courseFilter)) {
            $query->where('course_id', (int) $this->courseFilter);
        }
        $search = trim($this->search);
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $userIds = User::query()->where('name', 'like', $like)->orWhere('email', 'like', $like)->limit(1000)->pluck('id')->all();
            $courseIds = $courses->filter(fn ($c) => Str::contains(Str::lower($c->title), Str::lower($search)))->pluck('id')->all();
            $query->where(function ($builder) use ($userIds, $courseIds): void {
                $builder->whereIn('user_id', $userIds)->orWhereIn('course_id', $courseIds);
            });
        }
        $matched = (clone $query)->count();
        $sessions = $query->orderByDesc('updated_at')->limit(75)->get();
        $users = User::query()->whereIn('id', $sessions->pluck('user_id')->unique())->get(['id', 'name', 'email'])->keyBy('id');
        $courseMap = $courses->keyBy('id');
        $progressCounts = ObjectiveProgress::query()->whereIn('lab_session_id', $sessions->pluck('id')->all())
            ->where('status', ObjectiveStatus::Completed->value)
            ->selectRaw('lab_session_id, COUNT(*) as qty')->groupBy('lab_session_id')->pluck('qty', 'lab_session_id');
        $rows = $sessions->map(function ($session) use ($users, $courseMap, $progressCounts) {
            $course = $courseMap->get($session->course_id);
            $total = (int) ($course?->objectives_count ?? 0);
            $done = (int) ($progressCounts->get($session->id) ?? 0);

            return [
                'id' => $session->id,
                'user_id' => $session->user_id,
                'student' => $users->get($session->user_id)?->name ?? 'User #'.$session->user_id,
                'email' => $users->get($session->user_id)?->email ?? 'User unavailable',
                'course' => $course?->title ?? 'Course unavailable',
                'done' => $done,
                'total' => $total,
                'percent' => $total > 0 ? min(100, (int) round($done * 100 / $total)) : 0,
                'status' => $session->status?->value ?? 'UNKNOWN',
                'current' => $session->current_objective,
                'duration' => $session->durationSeconds(),
                'updated' => $session->updated_at?->diffForHumans() ?? '—',
            ];
        });

        $selectedId = $this->selectedSession ?? $sessions->first()?->id;
        $selected = $selectedId ? LabSession::query()->find($selectedId) : null;
        $selectedUser = $selected ? User::query()->find($selected->user_id) : null;
        $selectedCourse = $selected ? $courseMap->get($selected->course_id) : null;
        $steps = collect();
        if ($selectedCourse && $selected) {
            $progress = ObjectiveProgress::query()->where('lab_session_id', $selected->id)->get()->keyBy('objective_id');
            $steps = $selectedCourse->objectives()->get()->map(fn ($objective) => [
                'title' => $objective->title,
                'sequence' => $objective->sequence,
                'status' => $progress->get($objective->id)?->status?->value ?? 'LOCKED',
                'completed_at' => $progress->get($objective->id)?->completed_at?->format('Y-m-d H:i') ?? null,
            ]);
        }

        return compact('summary', 'courses', 'rows', 'matched', 'selected', 'selectedUser', 'selectedCourse', 'steps');
    }
}
