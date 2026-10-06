<?php

namespace App\Filament\Pages;

use App\Enums\LabSessionStatus;
use App\Enums\ObjectiveStatus;
use App\Jobs\ProvisionLabSession;
use App\Jobs\ResetLabSession;
use App\Models\Course;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\Objective;
use App\Services\GuidedCtf\LabSessionManager;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;

/**
 * The guided lab: one course, one lab at a time.
 *
 * The specification asks for a single page that is a course card before the lab exists
 * and a workspace afterwards, and that is what this is. Two screens would mean two
 * places for a student to be, and the transition between them is the part most likely
 * to strand somebody mid-provisioning.
 *
 * Nothing here names a container. Every address shown is read from the session record,
 * and the session is found from the signed-in user. A student cannot ask for a
 * different target by editing a request, because no request says which target.
 */
class GuidedCtf extends Page
{
    protected static ?string $navigationLabel = 'CTF Management';

    protected static ?string $title = 'Guided CTF';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'GUIDED CTF';

    protected string $view = 'filament.pages.guided-ctf';

    /** What the student typed into the flag box. Never rendered back. */
    public string $flag = '';

    /**
     * The session being worked on, or null before the lab exists.
     *
     * Resolved on every render from the signed-in user rather than held in a public
     * property. A public property would be settable from a request, which is exactly the
     * shape of the bug the specification warns about: a student naming somebody else's
     * lab session.
     */
    public function session(): ?LabSession
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        $course = $this->course();

        return $course === null
            ? null
            : app(LabSessionManager::class)->activeSessionFor($user, $course);
    }

    /**
     * Every session this student has run, newest first.
     *
     * Shown rather than assumed away, because a lab that failed is something a student
     * needs to be able to see and understand rather than find replaced by a fresh one.
     */
    public function history(): Collection
    {
        $user = auth()->user();

        if ($user === null) {
            return collect();
        }

        return LabSession::query()
            ->where('user_id', $user->getKey())
            ->latest('id')
            ->limit(20)
            ->get();
    }

    public function course(): ?Course
    {
        return Course::webServerBreach();
    }

    /**
     * Begin the lab.
     */
    public function startLab(): void
    {
        $user = auth()->user();
        $course = $this->course();

        if ($user === null || $course === null) {
            $this->failure('The course is not available.');

            return;
        }

        $session = app(LabSessionManager::class)->start($user, $course);

        Queue::push(new ProvisionLabSession($session->getKey()));

        Notification::make()
            ->title('Building your lab')
            ->body('Two containers and a network rule. This page will show the objectives and the terminal when it is ready.')
            ->info()
            ->persistent()
            ->send();
    }

    /**
     * Ask the validator whether the current objective is done.
     *
     * Called by the student rather than on a timer. Polling would mean reading the
     * victim's logs from a request that has no other purpose, every few seconds, for
     * every open page; this reads them once, when there is a reason to.
     */
    public function checkProgress(): void
    {
        $session = $this->session();

        if ($session === null || ! $session->status->allowsTerminal()) {
            return;
        }

        $verdict = app(LabSessionManager::class)->advance($session->refresh());

        if ($verdict->proven) {
            Notification::make()
                ->title('Objective complete')
                ->body($verdict->evidence['detail'] ?? 'Proven.')
                ->success()
                ->send();

            return;
        }

        $this->notice($verdict->reason ?? 'The validator found no evidence for this objective yet.');
    }

    /**
     * Compare a submitted flag against the copy inside the victim.
     */
    public function submitFlag(): void
    {
        $session = $this->session();

        if ($session === null) {
            return;
        }

        if (trim($this->flag) === '') {
            $this->failure('Type the flag you retrieved first.');

            return;
        }

        $verdict = app(LabSessionManager::class)->submitFlag($session, $this->flag);

        // Cleared either way. Leaving it after a wrong answer puts the student's guess on
        // screen, and leaving it after a right one puts the answer there — which is the
        // one thing the flag must never do.
        $this->flag = '';

        if ($verdict->proven) {
            Notification::make()
                ->title('Course completed')
                ->body('All six objectives are complete.')
                ->success()
                ->persistent()
                ->send();

            return;
        }

        $this->failure($verdict->reason ?? 'That is not the flag.');
    }

    /**
     * Return the lab to its starting state.
     *
     * Progress is kept, as the specification decides for the MVP: a student who damages
     * the victim does not lose the objectives they already earned, and the flag stays
     * the same one they already submitted.
     */
    public function resetLab(): void
    {
        $session = $this->session();

        if ($session === null || ! $session->status->acceptsOperation()) {
            return;
        }

        Queue::push(new ResetLabSession($session->getKey()));

        Notification::make()
            ->title('Resetting your lab')
            ->body('Your objective progress is kept. This will show the lab being reset.')
            ->info()
            ->persistent()
            ->send();
    }

    /**
     * Withdraw the pair and close the lab, keeping the record.
     */
    public function finishLab(): void
    {
        $session = $this->session();

        if ($session === null) {
            return;
        }

        app(LabSessionManager::class)->finish($session);

        Notification::make()
            ->title('Lab finished')
            ->body('Your progress has been kept.')
            ->success()
            ->send();
    }

    /**
     * The attacker, as an environment the terminal can open.
     */
    public function attackerEnvironment(): ?LabEnvironment
    {
        return $this->session()?->attackerEnvironment;
    }

    /**
     * The victim, for the read-only pane.
     *
     * The specification shows the student's own shell and a view of the target side by
     * side. The victim's terminal is opened as its own account rather than through the
     * attacker, so it is a genuine second shell and not a transcript of the first.
     */
    public function victimEnvironment(): ?LabEnvironment
    {
        return $this->session()?->victimEnvironment;
    }

    /**
     * The objectives with the state each is in, for this session.
     *
     * @return Collection<int, array{objective: Objective, status: ObjectiveStatus, payload: array<string, mixed>}>
     */
    public function objectiveList(): Collection
    {
        $session = $this->session();

        if ($session === null) {
            return collect();
        }

        $progress = $session->progress()->get()->keyBy('objective_id');

        return $session->course->objectives->map(
            fn (Objective $objective): array => [
                'objective' => $objective,
                'status' => $progress->get($objective->getKey())?->status ?? ObjectiveStatus::Locked,
                // Goes through the model's own gate, which withholds the evidence value
                // for a locked objective. That is the specification's disclosure rule,
                // enforced in one place rather than by remembering to hide things in
                // the view.
                'payload' => $objective->toPublicArray($progress->get($objective->getKey())?->status ?? ObjectiveStatus::Locked),
            ],
        );
    }

    /**
     * A hint with this lab's own target address in it.
     *
     * The course is shared and every student has a different target, so a command that
     * needs the address is stored with a placeholder. It is substituted here rather than
     * in the view so that there is one place that knows a lab's address is disclosable —
     * and this one checks that first.
     */
    public function hintFor(?string $hint): ?string
    {
        if ($hint === null || $hint === '') {
            return null;
        }

        if (! str_contains($hint, '{target}')) {
            return $hint;
        }

        $session = $this->session();

        if ($session === null || ! $session->targetIsDisclosable()) {
            // The command is withheld rather than shown with a blank address. A student
            // who cannot read the target yet is not yet at the objective that needs it,
            // and a command with nothing in it is a thing to copy and be confused by.
            return null;
        }

        return str_replace('{target}', (string) $session->victim_ip, $hint);
    }

    /**
     * What the page says about the lab, in the student's terms.
     */
    public function headline(LabSession $session): string
    {
        return match ($session->status) {
            LabSessionStatus::Creating,
            LabSessionStatus::Provisioning,
            LabSessionStatus::Configuring => 'Provisioning your lab',
            LabSessionStatus::Ready, LabSessionStatus::Running => 'Lab ready',
            LabSessionStatus::Completed => 'Course completed',
            LabSessionStatus::Failed => 'Lab failed',
            LabSessionStatus::Stopped => 'Lab finished',
            LabSessionStatus::Resetting => 'Resetting your lab',
            LabSessionStatus::Deleting => 'Removing your lab',
        };
    }

    public function canWork(LabSession $session): bool
    {
        return $session->status->allowsTerminal();
    }

    /**
     * The stages a lab goes through, so provisioning shows what is happening rather
     * than only that something is.
     *
     * Derived from the session's own state rather than from the job, because the job
     * runs on another process and the page has no way to watch it. The stages it cannot
     * yet confirm are said to be pending rather than shown as done.
     *
     * @return list<array{label: string, state: string}>
     */
    public function stages(LabSession $session): array
    {
        $order = [
            LabSessionStatus::Creating,
            LabSessionStatus::Provisioning,
            LabSessionStatus::Configuring,
            LabSessionStatus::Ready,
            LabSessionStatus::Running,
            LabSessionStatus::Completed,
        ];

        $current = array_search($session->status, $order, true);

        $labels = [
            'Lab session created',
            'Provisioning the attacker container',
            'Provisioning the victim container',
            'Permitting the route between them',
            'Preparing the target application',
            'Lab ready',
        ];

        $stages = [];

        foreach ($order as $index => $state) {
            $stages[] = [
                'label' => $labels[$index] ?? $state->value,
                'state' => match (true) {
                    $session->status === $state => 'current',
                    $current !== false && $index < $current => 'done',
                    default => 'pending',
                },
            ];
        }

        return $stages;
    }

    /**
     * When the course was finished, in a form a student can read.
     */
    public function duration(LabSession $session): ?string
    {
        $seconds = $session->durationSeconds();

        if ($seconds === null) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    private function failure(string $message): void
    {
        Notification::make()
            ->title('Not accepted')
            ->body($message)
            ->danger()
            ->persistent()
            ->send();
    }

    private function notice(string $message): void
    {
        Notification::make()
            ->title('Not yet')
            ->body($message)
            ->warning()
            ->persistent()
            ->send();
    }
}
