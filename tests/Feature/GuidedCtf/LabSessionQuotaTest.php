<?php

use App\Enums\LabSessionStatus;
use App\Models\Course;
use App\Models\LabSession;
use App\Models\User;
use App\Services\GuidedCtf\LabSessionManager;
use App\Services\GuidedCtf\PairAddressAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    expect(config('database.default'))->toBe('sqlite');
    expect(config('infrastructure.environment_connection'))->toBe('sqlite');

    $this->user = User::factory()->create();

    $this->course = Course::query()->create([
        'slug' => 'quota-test-course',
        'title' => 'Quota Test Course',
    ]);
});

function quotaTestAllocator(int $octet = 150): void
{
    app()->instance(
        PairAddressAllocator::class,
        Mockery::mock(
            PairAddressAllocator::class,
            function (MockInterface $mock) use ($octet): void {
                $mock->shouldReceive('allocate')
                    ->once()
                    ->andReturn([
                        'octet' => $octet,
                        'victim_ip' => "10.20.0.$octet",
                        'attacker_ip' => "10.30.0.$octet",
                    ]);
            }
        )
    );
}

it('reuses an existing session without allocating another pair', function (): void {
    quotaTestAllocator();

    $manager = app(LabSessionManager::class);

    $first = $manager->start($this->user, $this->course);
    $second = $manager->start($this->user, $this->course);

    expect($first->getKey())->toBe($second->getKey())
        ->and($first->wasRecentlyCreated)->toBeTrue()
        ->and($second->wasRecentlyCreated)->toBeFalse()
        ->and(LabSession::query()->count())->toBe(1);
});

it('rejects another course while a lab occupies the quota', function (): void {
    quotaTestAllocator();

    $otherCourse = Course::query()->create([
        'slug' => 'quota-other-course',
        'title' => 'Other Course',
    ]);

    $manager = app(LabSessionManager::class);
    $manager->start($this->user, $this->course);

    expect(fn () => $manager->start($this->user, $otherCourse))
        ->toThrow(RuntimeException::class, 'already has a lab');

    expect(LabSession::query()->count())->toBe(1);
});

it('keeps resetting and deleting sessions in the occupied quota', function (): void {
    quotaTestAllocator();

    $manager = app(LabSessionManager::class);
    $session = $manager->start($this->user, $this->course);

    foreach ([LabSessionStatus::Resetting, LabSessionStatus::Deleting] as $status) {
        $session->forceFill(['status' => $status])->save();

        $active = $manager->activeSessionFor($this->user, $this->course);
        $reused = $manager->start($this->user, $this->course);

        expect($active)->not->toBeNull()
            ->and($active->getKey())->toBe($session->getKey())
            ->and($reused->getKey())->toBe($session->getKey())
            ->and($reused->wasRecentlyCreated)->toBeFalse();
    }

    expect(LabSession::query()->count())->toBe(1);
});

it('allocates a new pair only after the previous session is deleted', function (): void {
    $allocator = Mockery::mock(PairAddressAllocator::class);
    $allocator->shouldReceive('allocate')
        ->twice()
        ->andReturn(
            [
                'octet' => 150,
                'victim_ip' => '10.20.0.150',
                'attacker_ip' => '10.30.0.150',
            ],
            [
                'octet' => 151,
                'victim_ip' => '10.20.0.151',
                'attacker_ip' => '10.30.0.151',
            ],
        );

    app()->instance(PairAddressAllocator::class, $allocator);

    $manager = app(LabSessionManager::class);

    $first = $manager->start($this->user, $this->course);

    // Simulates confirmed teardown at the persistence layer only.
    // This test does not claim to verify actual container destruction.
    DB::connection('sqlite')->table('objective_progress')
        ->where('lab_session_id', $first->getKey())->delete();

    DB::connection('sqlite')->table('lab_sessions')
        ->where('id', $first->getKey())->delete();

    $second = $manager->start($this->user, $this->course);

    expect($second->getKey())->not->toBe($first->getKey())
        ->and($second->victim_ip)->toBe('10.20.0.151')
        ->and(LabSession::query()->count())->toBe(1);
});


it('does not reuse a database-reserved pair before provisioning', function (): void {
    quotaTestAllocator(100);

    $manager = app(LabSessionManager::class);
    $session = $manager->start($this->user, $this->course);

    expect($session->victim_ip)->toBe('10.20.0.100');

    // Simulate empty Incus remotes and an empty firewall set.
    // No infrastructure commands are executed.
    $commands = Mockery::mock(
        \App\Infrastructure\Health\CommandRunner::class
    );

    $commands->shouldReceive('run')
        ->times(3)
        ->andReturnUsing(function (array $command): array {
            $isIncusList = in_array('--format=json', $command, true);

            return [
                'successful' => true,
                'stdout' => $isIncusList ? '[]' : '',
                'stderr' => '',
                'exit_code' => 0,
            ];
        });

    $allocator = new PairAddressAllocator($commands);
    $next = $allocator->allocate();

    expect($next['octet'])->toBe(101)
        ->and($next['victim_ip'])->toBe('10.20.0.101')
        ->and($next['attacker_ip'])->toBe('10.30.0.101');
});


it('releases a deleted session pair only after environments are gone', function (): void {
    quotaTestAllocator(100);

    $session = app(LabSessionManager::class)
        ->start($this->user, $this->course);

    $session->update(['status' => \App\Enums\LabSessionStatus::Deleted]);

    $commands = Mockery::mock(
        \App\Infrastructure\Health\CommandRunner::class
    );

    $commands->shouldReceive('run')
        ->times(6)
        ->andReturnUsing(function (array $command): array {
            return [
                'successful' => true,
                'stdout' => in_array('--format=json', $command, true)
                    ? '[]' : '',
                'stderr' => '',
                'exit_code' => 0,
            ];
        });

    $allocator = new PairAddressAllocator($commands);

    // A deleted session alone is insufficient: environment rows
    // must still reserve the addresses.
    expect($allocator->allocate()['octet'])->toBe(101);

    \App\Models\LabEnvironment::query()
        ->whereIn('id', [
            $session->victim_environment_id,
            $session->attacker_environment_id,
        ])
        ->delete();

    // Historical session remains, but the pair is now reusable.
    expect($allocator->allocate()['octet'])->toBe(100)
        ->and(\App\Models\LabSession::query()->find($session->id))
        ->not->toBeNull();
});
