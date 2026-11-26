# Admin → Create Lab for Student

## Purpose

The existing **Create Pair** action provisions a pair owned by the administrator through `AdminEnvironmentPairCreator`. It remains unchanged.

The new **Create Lab for Student** action appears alongside it in **Admin → Environments**. It uses `AdminStudentLabProvisioner` to create an ordinary Guided CTF `LabSession` owned by the selected Student, then queues the existing `ProvisionLabSession` job. No second provisioning implementation, schema migration, or admin-role reassignment is introduced.

## Flow

1. An active administrator opens **Environments → Create Lab for Student**.
2. The dropdown lists only active Student accounts with no non-deleted `lab_sessions` records and no remaining `lab_environments` records. This deliberately excludes failed, deleting, and orphaned environments until cleaned up.
3. Admin confirms the selected Student. Backend **rechecks** role and eligibility; client input is never trusted.
4. `LabSessionManager::start($student, $course)` reuses its per-user lock, atomic address allocation and database transaction. It records two environments belonging to the Student plus one Guided CTF session and objective progress.
5. The existing provisioning job runs via the queue. The Student sees the session under **My Labs**, including its provisioning status; once `READY`, they can open the assigned lab without creating a second one.
6. Admin Pair lifecycle is independent and its `admin_environment_pairs` records are unaffected.

## Limits and safety

- Provisioning is **not performed by opening the dialog**; it runs only after confirmation and the queue worker accepts the job.
- An existing session (including FAILED/DELETING) or leftover environment prevents a second pair. Clean up via the supported lifecycle rather than deleting database rows manually.
- A Student cannot select users or call this action as Admin. The backend verifies Admin status again.
- The **Start Lab** button in Student My Labs remains for students who have not received a lab. For an assigned Student, the existing session is shown and a duplicate request is blocked.
- Timing metadata such as `started_at` currently follows the established `LabSessionManager` behavior (allocated at creation). This change does not redefine practical-session duration.
- Runtime infrastructure was **not modified** during this code change. A staging test must confirm queue worker status, Incus provisioning, firewall policies, Student visibility, and the retained Admin Create Pair workflow.

## Suggested staging verification

1. Use an active test Student with zero environments.
2. Confirm the dropdown shows them, and excludes Admin/Lecturer, disabled accounts and users with existing/pending/failed labs.
3. Create the Student lab once; verify ownership on `lab_sessions.user_id` and both `lab_environments.user_id`, and that exactly one `ProvisionLabSession` is queued.
4. Refresh My Labs as the Student. The provisioning session should be visible without selecting Start Lab.
5. After worker completion, verify both containers, network restrictions, access to Guided CTF and notification.
6. Confirm **Create Pair** still creates an admin-owned pair independently, and a second Student assignment is refused.

## Code and tests

- `app/Services/GuidedCtf/AdminStudentLabProvisioner.php`
- `app/Filament/Resources/Environments/Pages/ListEnvironments.php`
- `tests/Feature/GuidedCtf/AdminStudentLabProvisionerTest.php`

Run the targeted tests in a non-production Laravel environment:

```bash
php artisan test --filter=AdminStudentLabProvisioner
```

This document describes code proposed in the feature branch. Do **not** mark runtime provisioning PASS before actual staging evidence.
