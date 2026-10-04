<?php

namespace App\Services\GuidedCtf;

use App\Enums\EnvironmentStatus;
use App\Enums\LabSessionStatus;
use App\Enums\ObjectiveStatus;
use App\Enums\RuntimeStatus;
use App\Enums\ValidationType;
use App\Models\Course;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\Objective;
use App\Models\ObjectiveProgress;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Everything that happens to one student's lab, in one place.
 *
 * The specification asks for a state machine and an objective progression, and both are
 * easy to get subtly wrong if they are spread across a job, a Livewire component and a
 * resource. They live here instead, so there is one answer to "what may happen next"
 * and one place where a transition is refused.
 *
 * Two rules shape most of the decisions:
 *
 * The progression is strictly sequential. An objective is completed by evidence, and
 * evidence for objective five is only admissible once objectives one to four are done,
 * because a student who skipped ahead has not done the work the evidence implies. So
 * verification always looks at the current objective and nothing else.
 *
 * A submitted flag is never believed. It is compared, server side, against the copy
 * inside the victim's container, and the correct value is never held anywhere the
 * browser can reach it. See FlagSubmission.
 */
class LabSessionManager
{
    public function __construct(
        private readonly PairAddressAllocator $addresses,
        private readonly LabFirewall $firewall,
        private readonly ObjectiveValidator $validator,
        private readonly VictimEvidenceReader $evidence,
        private readonly ActivityLogService $activity,
    ) {}

    /**
     * Begin a lab: allocate the pair, record the session, queue the work.
     *
     * The record exists before anything is created, and says PROVISIONING rather than
     * pretending to be ready. A student who watches this run sees a lab being built
     * rather than a button that stopped working.
     */
    public function start(User $user, Course $course): LabSession
    {
        if ($existing = $this->activeSessionFor($user, $course)) {
            // Rejoining is not an error. A student who refreshes, or clicks Start Lab
            // twice, should get their lab rather than a second one.
            return $existing;
        }

        $pair = $this->addresses->allocate();

        return DB::connection((string) config('infrastructure.environment_connection'))
            ->transaction(function () use ($user, $course, $pair): LabSession {
                $victim = LabEnvironment::query()->create([
                    'user_id' => $user->getKey(),
                    'container_name' => $this->victimName($course, $pair['octet']),
                    // Stored as a remote name. The configuration carries the trailing
                    // colon because that is how the Incus command line spells it, and
                    // adding another colon when a target is built produced
                    // "lab-env::victim-web-104" and a provisioning failure on the very
                    // first command.
                    'incus_remote' => rtrim((string) config('infrastructure.incus_remote', 'lab-env'), ':'),
                    'incus_project' => 'default',
                    'image' => (string) config('infrastructure.lab_image', 'images:ubuntu/24.04'),
                    'internal_ip' => $pair['victim_ip'],
                    'status' => EnvironmentStatus::Provisioning,
                    'runtime_status' => RuntimeStatus::Unknown,
                    'cpu_limit' => (int) config('infrastructure.default_cpu_limit', 2),
                    'memory_limit_mb' => (int) config('infrastructure.default_memory_limit_mb', 2048),
                    'disk_limit_gb' => (int) config('infrastructure.default_disk_limit_gb', 8),
                    'tofu_workspace' => 'ctf-victim-'.$pair['octet'],
                    'terminal_enabled' => true,
                ]);

                /*
                 * The attacker is recorded as an environment as well.
                 *
                 * Not tidiness: the terminal is built around LabEnvironment, and the
                 * specification puts the shell on the attacker. Recording it the same
                 * way means the existing terminal machinery opens a shell in the right
                 * container without being forked, and without a second code path that
                 * could behave differently from the first.
                 *
                 * terminal_enabled is false here and switched on once the container
                 * exists, because the terminal resolves a uid inside the container and
                 * a container that is not there yet would fail in a way that reads as a
                 * broken terminal rather than as a lab that is still being built.
                 */
                $attacker = LabEnvironment::query()->create([
                    'user_id' => $user->getKey(),
                    'container_name' => $this->attackerName($pair['octet']),
                    'incus_remote' => rtrim((string) config('infrastructure.incus_attacker_remote', 'lab-atk'), ':'),
                    'incus_project' => 'default',
                    'image' => (string) config('infrastructure.attacker_image', 'images:kali'),
                    'internal_ip' => $pair['attacker_ip'],
                    'status' => EnvironmentStatus::Provisioning,
                    'runtime_status' => RuntimeStatus::Unknown,
                    'cpu_limit' => (int) config('infrastructure.default_cpu_limit', 2),
                    'memory_limit_mb' => (int) config('infrastructure.default_memory_limit_mb', 2048),
                    'disk_limit_gb' => (int) config('infrastructure.default_disk_limit_gb', 8),
                    'tofu_workspace' => 'ctf-attacker-'.$pair['octet'],
                    'terminal_enabled' => false,
                ]);

                $session = LabSession::query()->create([
                    'user_id' => $user->getKey(),
                    'course_id' => $course->getKey(),
                    'victim_environment_id' => $victim->getKey(),
                    'attacker_environment_id' => $attacker->getKey(),
                    'attacker_container' => $attacker->container_name,
                    'attacker_remote' => $attacker->incus_remote,
                    'attacker_ip' => $pair['attacker_ip'],
                    'victim_container' => $victim->container_name,
                    'victim_remote' => $victim->incus_remote,
                    'victim_ip' => $pair['victim_ip'],
                    'status' => LabSessionStatus::Provisioning,
                    'current_objective' => 1,
                    'started_at' => now(),
                ]);

                // One row per objective up front, so progress is a question about
                // existing rows rather than a reconstruction from events. It also means
                // a locked objective has something to say "you cannot see this yet"
                // about, instead of being absent and looking like it does not exist.
                foreach ($course->objectives as $index => $objective) {
                    ObjectiveProgress::query()->create([
                        'lab_session_id' => $session->getKey(),
                        'objective_id' => $objective->getKey(),
                        'status' => $index === 0 ? ObjectiveStatus::Active : ObjectiveStatus::Locked,
                    ]);
                }

                return $session;
            });
    }

    /**
     * Mark a session ready once both containers exist and the pair is permitted.
     */
    public function markReady(LabSession $session): LabSession
    {
        $session->forceFill(['status' => LabSessionStatus::Ready])->save();

        $this->activity->record($session->owner(), 'LAB_READY', $session, 'completed', 'Guided CTF lab is ready.');

        return $session;
    }

    /**
     * Mark a session failed, saying why.
     */
    public function markFailed(LabSession $session, string $reason): LabSession
    {
        $session->forceFill(['status' => LabSessionStatus::Failed])->save();

        $this->activity->record($session->owner(), 'LAB_FAILED', $session, 'failed', $reason);

        return $session;
    }

    /**
     * Check the current objective and advance if it is proven.
     *
     * Returns the verdict whether or not it advanced, because the student is told why
     * an objective did not complete and an unexplained failure is indistinguishable
     * from the validator being broken.
     */
    public function advance(LabSession $session): ObjectiveVerdict
    {
        $objective = $this->currentObjective($session);

        if ($objective === null) {
            return ObjectiveVerdict::unproven('Every objective in this course is complete.');
        }

        $verdict = $this->validator->verify($session, $objective);

        if (! $verdict->proven) {
            return $verdict;
        }

        $this->complete($session, $objective, $verdict);

        return $verdict;
    }

    /**
     * Compare a submitted flag against the copy inside the victim.
     *
     * The comparison is constant time so that a student cannot learn the flag by
     * measuring how long the answer takes, and the correct value never enters the
     * portal database or the response. hash_equals is used rather than === for the
     * same reason.
     */
    public function submitFlag(LabSession $session, string $submission): ObjectiveVerdict
    {
        $objective = $this->currentObjective($session);

        if ($objective === null) {
            return ObjectiveVerdict::unproven('This course is already complete.');
        }

        if ($objective->validation_type !== ValidationType::Flag) {
            return ObjectiveVerdict::unproven(
                'This objective is completed by what you do on the target, not by submitting an answer.',
            );
        }

        $expected = $this->evidence->flag($session);

        if ($expected === null) {
            // Said plainly rather than as "incorrect". The student did nothing wrong
            // and cannot do anything about it, and telling them their answer was wrong
            // would send them looking in the wrong place.
            return ObjectiveVerdict::unproven(
                'The flag could not be read from your target, so this cannot be checked. '
                .'Tell an administrator.',
            );
        }

        if (! hash_equals($expected, trim($submission))) {
            return ObjectiveVerdict::unproven('That is not the flag.');
        }

        $this->complete($session, $objective, ObjectiveVerdict::proven([
            'source' => 'server-side comparison',
            'detail' => 'the submitted value matched the flag held inside the victim',
        ]));

        $session->refresh();

        // Finished when nothing is still outstanding.
        //
        // Asked of the progress rows rather than of currentObjective(). Completing the
        // last objective leaves current_objective pointing at the last sequence number,
        // because there is no objective after it to advance to, so a lookup there still
        // returns that objective and never says "nothing left". A student who had done
        // every objective was shown a lab that was merely ready, with a results page
        // that never appeared.
        $outstanding = ObjectiveProgress::query()
            ->where('lab_session_id', $session->getKey())
            ->whereIn('status', [ObjectiveStatus::Locked->value, ObjectiveStatus::Active->value])
            ->count();

        if ($outstanding === 0) {
            // Idempotent, and completed_at is written only once.
            //
            // Guarding on completed_at being null meant a session that had been
            // re-provisioned after finishing — which does happen, and is legitimate, since
            // a lab can be reset and worked through again — could never show itself as
            // complete a second time. It kept READY with 6 of 6 objectives done and no
            // results page, which reads as a lab the portal lost track of.
            //
            // The status is therefore set every time, while the timestamp is kept from
            // the first completion. A student who finishes a course, resets, and finishes
            // it again should see the time they actually took the first time, not a
            // duration inflated by the practice in between.
            $session->forceFill([
                'status' => LabSessionStatus::Completed,
                'completed_at' => $session->completed_at ?? now(),
            ])->save();
        }

        return ObjectiveVerdict::proven([
            'source' => 'server-side comparison',
            'detail' => 'the submitted value matched the flag held inside the victim',
        ]);
    }

    /**
     * Record an objective as completed and activate the next one.
     */
    private function complete(LabSession $session, Objective $objective, ObjectiveVerdict $verdict): void
    {
        DB::connection((string) config('infrastructure.environment_connection'))->transaction(
            function () use ($session, $objective, $verdict): void {
                $progress = ObjectiveProgress::query()
                    ->where('lab_session_id', $session->getKey())
                    ->where('objective_id', $objective->getKey())
                    ->first();

                if ($progress === null) {
                    return;
                }

                $progress->forceFill([
                    'status' => ObjectiveStatus::Completed,
                    'completed_at' => now(),
                    'evidence' => $verdict->evidencePayload(),
                ])->save();

                $next = Objective::query()
                    ->where('course_id', $session->course_id)
                    ->where('sequence', $objective->sequence + 1)
                    ->first();

                if ($next === null) {
                    $session->forceFill(['current_objective' => $objective->sequence])->save();

                    return;
                }

                $session->forceFill(['current_objective' => $next->sequence])->save();

                ObjectiveProgress::query()
                    ->where('lab_session_id', $session->getKey())
                    ->where('objective_id', $next->getKey())
                    ->update(['status' => ObjectiveStatus::Active->value]);
            },
        );

        $this->activity->record(
            $session->owner(),
            'OBJECTIVE_COMPLETED',
            $session,
            'completed',
            sprintf(
                '%s — %s',
                $objective->numberedTitle(),
                $verdict->evidence['detail'] ?? 'proven',
            ),
        );
    }

    /**
     * The objective in play, or null when the course is finished.
     */
    public function currentObjective(LabSession $session): ?Objective
    {
        return Objective::query()
            ->where('course_id', $session->course_id)
            ->where('sequence', $session->current_objective)
            ->first();
    }

    /**
     * The student's session for a course, if there is one worth showing.
     *
     * Teaches the state machine rather than hard-coding a list, so a new state is
     * included by describing what it is instead of by remembering to add it.
     */
    public function activeSessionFor(User $user, Course $course): ?LabSession
    {
        return LabSession::query()
            ->where('user_id', $user->getKey())
            ->where('course_id', $course->getKey())
            ->whereIn('status', [
                LabSessionStatus::Creating->value,
                LabSessionStatus::Provisioning->value,
                LabSessionStatus::Configuring->value,
                LabSessionStatus::Ready->value,
                LabSessionStatus::Running->value,
                LabSessionStatus::Completed->value,
                LabSessionStatus::Failed->value,
                LabSessionStatus::Stopped->value,
            ])
            ->latest('id')
            ->first();
    }

    /**
     * Finish a lab: stop using it, withdraw the pair, keep the record.
     *
     * The record is kept rather than deleted, because the results are the student's
     * evidence of having done the work and an operator may need to answer a question
     * about it later. The pair is withdrawn, because leaving it permitted would stop
     * that address octet ever being allocated again.
     */
    public function finish(LabSession $session): LabSession
    {
        $this->firewall->revoke($session);

        $session->forceFill([
            'status' => LabSessionStatus::Stopped,
            'completed_at' => $session->completed_at ?? now(),
        ])->save();

        $this->activity->record($session->owner(), 'LAB_FINISHED', $session, 'completed', 'Guided CTF lab finished.');

        return $session;
    }

    /**
     * @return array{completed: int, total: int}
     */
    public function progress(LabSession $session): array
    {
        return $session->progressCount();
    }

    private function victimName(Course $course, int $octet): string
    {
        return sprintf('victim-%s-%03d', $course->slug === '' ? 'lab' : 'web', $octet);
    }

    private function attackerName(int $octet): string
    {
        return sprintf('attacker-%03d', $octet);
    }
}