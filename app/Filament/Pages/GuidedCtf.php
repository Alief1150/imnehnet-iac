<?php

namespace App\Filament\Pages;

use App\Enums\LabSessionStatus;
use App\Models\Course;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\Objective;
use App\Models\ObjectiveProgress;
use App\Services\GuidedCtf\LabSessionManager;
use App\Services\GuidedCtf\ObjectiveVerdict;
use App\Jobs\ProvisionLabSession;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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
    /**
     * Property types must match the parent's exactly.
     *
     * Narrowing any of these to string is a fatal declaration error, not a warning,
     * and it is raised while the class is loaded — so the panel fails to boot and
     * every page on the site returns 500, including the health check. php -l passes
     * the file because nothing about the syntax is wrong, and a test suite cannot
     * report it either, because the class is loaded before the first test runs.
     *
     * Two rounds of this, one property at a time. scripts/verify.sh caught both, and
     * the whole site was down for each.
     *
     * No navigationIcon is declared. Filament's own glyph is used, because this page's
     * icon goes through blade-icons by name while the icons this project ships are
     * inline SVG rendered by App\Support\Lucide. Naming one that blade-icons does not
     * hold produced an SvgNotFound on every page that renders the sidebar — which is
     * most of them, and not only this page.
     */
    protected static ?string $navigationLabel = 'Guided CTF';

    protected static ?string $title = 'Guided CTF';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.guided-ctf';

    /** What the student typed into the flag box. Never rendered back. */
    public string $flag = '';

    /**
     * The session being worked on, or null before the lab exists.
     *
     * Resolved on every render from the signed-in user rather than held in a public
     * property. A public property would be settable from a request, which is exactly
     * the shape of the bug the specification warns about: a student naming somebody
     * else's lab session.
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
            ->body('Two containers and a network rule. This page will show the lab when it is ready.')
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

        Notification::make()
            ->title('Not yet')
            ->body($verdict->reason ?? 'The validator found no evidence for this objective yet.')
            ->warning()
            ->send();
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

        // Cleared either way. Leaving it in the field after a wrong answer puts the
        // student's guess on screen, and leaving it after a right one puts the answer
        // there — which is the one thing the flag must never do.
        $this->flag = '';

        if ($verdict->proven) {
            Notification::make()
                ->title('Correct')
                ->body('The course is complete.')
                ->success()
                ->persistent()
                ->send();

            return;
        }

        $this->failure($verdict->reason ?? 'That is not the flag.');
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
     * The victim, as an environment the terminal can open.
     */
    public function attackerEnvironment(): ?LabEnvironment
    {
        $session = $this->session();

        return $session?->attackerEnvironment;
    }

    /**
     * The objectives with the state each is in for this session.
     *
     * @return \Illuminate\Support\Collection<int, array{objective: Objective, status: string, payload: array<string, mixed>}>
     */
    public function objectiveList()
    {
        $session = $this->session();

        if ($session === null) {
            return collect();
        }

        $progress = $session->progress()->get()->keyBy('objective_id');

        return $session->course
            ->objectives
            ->map(function (Objective $objective) use ($progress): array {
                $status = $progress->get($objective->getKey())?->status
                    ?? \App\Enums\ObjectiveStatus::Locked;

                return [
                    'objective' => $objective,
                    'status' => $status,
                    // Goes through the model's own gate, which withholds the evidence
                    // value for a locked objective. That is the disclosure rule from
                    // the specification, and it is enforced in one place rather than by
                    // remembering to hide things in the view.
                    'payload' => $objective->toPublicArray($status),
                ];
            });
    }

    /**
     * What the page says about the lab, in the student's terms.
     */
    public function headline(LabSession $session): string
    {
        return match ($session->status) {
            LabSessionStatus::Creating, LabSessionStatus::Provisioning,
            LabSessionStatus::Configuring => 'Building your lab',
            LabSessionStatus::Ready => 'Lab ready',
            LabSessionStatus::Running => 'Lab ready',
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

    private function failure(string $message): void
    {
        Notification::make()
            ->title('Not accepted')
            ->body($message)
            ->danger()
            ->persistent()
            ->send();
    }
}