<?php

namespace App\Services\GuidedCtf;

use App\Enums\LabSessionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\ProvisionLabSession;
use App\Models\Course;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Throwable;

/**
 * Admin-initiated provisioning of a regular, student-owned Guided CTF session.
 *
 * Deliberately does not use AdminEnvironmentPairCreator: those pairs belong
 * to the administrator and have a separate lifecycle and ownership model.
 */
final class AdminStudentLabProvisioner
{
    /** @return array<int, string> */
    public function eligibleStudents(): array
    {
        // A failed/deleting lab still occupies the student's quota. A deleted
        // session is not selectable either if its environment rows remain.
        $occupiedSessionIds = LabSession::query()
            ->where('status', '!=', LabSessionStatus::Deleted->value)
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->all();

        $occupiedEnvironmentIds = LabEnvironment::query()
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->all();

        $excludedIds = array_values(array_unique(array_map(
            'intval',
            array_merge($occupiedSessionIds, $occupiedEnvironmentIds),
        )));

        return User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::Active->value)
            ->when($excludedIds !== [], fn ($query) => $query->whereNotIn('id', $excludedIds))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(fn (User $user): array => [
                (int) $user->getKey() => $user->name.' ('.$user->email.')',
            ])
            ->all();
    }

    public function create(User $actor, int $studentId): LabSession
    {
        if (! $actor->isAdmin() || ! $actor->isActive()) {
            throw new RuntimeException('Only an active administrator can assign student labs.');
        }

        $student = User::query()->find($studentId);
        if (! $student || $student->role !== UserRole::Student || ! $student->isActive()) {
            throw new RuntimeException('Select an active student account.');
        }

        if (! array_key_exists($studentId, $this->eligibleStudents())) {
            throw new RuntimeException('Student already has a lab or an environment pending cleanup.');
        }

        $course = Course::webServerBreach();
        if ($course === null) {
            throw new RuntimeException('The Guided CTF course is not configured.');
        }

        // This method owns the per-user lock, pair allocation and database
        // transaction. It is also used by Student > My Labs.
        $session = app(LabSessionManager::class)->start($student, $course);

        // A concurrent student/admin click cannot queue a duplicate job.
        if (! $session->wasRecentlyCreated) {
            throw new RuntimeException('Student lab already exists; no new job was queued.');
        }

        try {
            ProvisioningTimeline::record(
                (int) $session->getKey(),
                'queued',
                'Lab provisioning was requested by an administrator for the student.',
            );

            Queue::push(new ProvisionLabSession((int) $session->getKey()));
        } catch (Throwable $exception) {
            // Do not advertise a successful queue submission. The reserved
            // environments remain visible for operator-controlled cleanup.
            $session->forceFill([
                'status' => LabSessionStatus::Failed,
                'last_error' => 'Provisioning could not be queued. Contact an administrator.',
            ])->save();

            throw $exception;
        }

        // The queue request succeeded. An audit-storage outage must not
        // misreport it as a provisioning failure or tempt an admin to retry.
        try {
            app(ActivityLogService::class)->record(
                $actor,
                'ADMIN_ASSIGNED_STUDENT_LAB',
                $session,
                'completed',
                'Administrator queued a Guided CTF lab for student #'.$studentId.'.',
                ['requested_action' => 'admin_assign_student_lab'],
            );
        } catch (Throwable $exception) {
            report($exception);
        }

        return $session;
    }
}
