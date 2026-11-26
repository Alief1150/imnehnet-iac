<?php

use App\Enums\LabSessionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\ProvisionLabSession;
use App\Models\AdminEnvironmentPair;
use App\Models\Course;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\User;
use App\Services\GuidedCtf\AdminStudentLabProvisioner;
use App\Services\GuidedCtf\PairAddressAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();

    $this->admin = User::factory()->create([
        'role' => UserRole::Admin,
        'status' => UserStatus::Active,
    ]);
    $this->student = User::factory()->create([
        'role' => UserRole::Student,
        'status' => UserStatus::Active,
    ]);

    $this->course = Course::query()->create([
        'slug' => 'imnehnet-web-server-breach',
        'title' => 'IMNEHNET Web Server Breach',
    ]);
});

function assignTestAllocator(int $octet = 181): void
{
    app()->instance(PairAddressAllocator::class, Mockery::mock(
        PairAddressAllocator::class,
        function (MockInterface $mock) use ($octet): void {
            $mock->shouldReceive('allocate')->once()->andReturn([
                'octet' => $octet,
                'victim_ip' => "10.20.0.$octet",
                'attacker_ip' => "10.30.0.$octet",
            ]);
        }
    ));
}

it('lists only eligible active students without lab ownership', function (): void {
    $inactive = User::factory()->create([
        'role' => UserRole::Student,
        'status' => UserStatus::Disabled,
    ]);
    $existingOwner = User::factory()->create([
        'role' => UserRole::Student,
        'status' => UserStatus::Active,
    ]);
    $orphanOwner = User::factory()->create([
        'role' => UserRole::Student,
        'status' => UserStatus::Active,
    ]);

    LabSession::query()->create([
        'user_id' => $existingOwner->id,
        'course_id' => $this->course->id,
        'status' => LabSessionStatus::Failed,
        'current_objective' => 1,
    ]);
    LabEnvironment::factory()->create(['user_id' => $orphanOwner->id]);

    $available = app(AdminStudentLabProvisioner::class)->eligibleStudents();

    expect(array_key_exists($this->student->id, $available))->toBeTrue()
        ->and(array_key_exists($this->admin->id, $available))->toBeFalse()
        ->and(array_key_exists($inactive->id, $available))->toBeFalse()
        ->and(array_key_exists($existingOwner->id, $available))->toBeFalse()
        ->and(array_key_exists($orphanOwner->id, $available))->toBeFalse();
});

it('queues one student-owned guided lab without creating an admin pair', function (): void {
    assignTestAllocator();

    $session = app(AdminStudentLabProvisioner::class)->create(
        $this->admin,
        $this->student->id,
    );

    expect($session->user_id)->toBe($this->student->id)
        ->and($session->course_id)->toBe($this->course->id)
        ->and($session->status)->toBe(LabSessionStatus::Provisioning)
        ->and($session->attackerEnvironment->user_id)->toBe($this->student->id)
        ->and($session->victimEnvironment->user_id)->toBe($this->student->id)
        ->and(AdminEnvironmentPair::query()->count())->toBe(0)
        ->and(LabSession::query()->count())->toBe(1);

    Queue::assertPushed(ProvisionLabSession::class, 1);
    Queue::assertPushed(
        ProvisionLabSession::class,
        fn (ProvisionLabSession $job): bool => $job->sessionId === $session->id,
    );

    $this->actingAs($this->student);
    expect(app(\App\Services\GuidedCtf\LabSessionManager::class)
        ->activeSessionFor($this->student, $this->course)?->id)
        ->toBe($session->id);
});

it('never queues a second job for the same student', function (): void {
    assignTestAllocator();

    $creator = app(AdminStudentLabProvisioner::class);
    $creator->create($this->admin, $this->student->id);

    expect(fn () => $creator->create($this->admin, $this->student->id))
        ->toThrow(RuntimeException::class, 'already has a lab');

    Queue::assertPushed(ProvisionLabSession::class, 1);
    expect(LabSession::query()->count())->toBe(1);
});

it('denies student and lecturer actors and admin targets', function (): void {
    $lecturer = User::factory()->create([
        'role' => UserRole::Lecturer,
        'status' => UserStatus::Active,
    ]);

    $service = app(AdminStudentLabProvisioner::class);

    expect(fn () => $service->create($this->student, $this->student->id))
        ->toThrow(RuntimeException::class, 'administrator');

    expect(fn () => $service->create($lecturer, $this->student->id))
        ->toThrow(RuntimeException::class, 'administrator');

    expect(fn () => $service->create($this->admin, $this->admin->id))
        ->toThrow(RuntimeException::class, 'active student');

    Queue::assertNothingPushed();
});
