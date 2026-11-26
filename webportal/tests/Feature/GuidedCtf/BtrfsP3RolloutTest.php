<?php

use App\Models\Course;
use App\Models\LabEnvironment;
use App\Models\User;
use App\Services\GuidedCtf\LabSessionManager;
use App\Services\GuidedCtf\PairAddressAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    expect(config('database.default'))->toBe('sqlite');
    expect(config('infrastructure.environment_connection'))->toBe('sqlite');

    $this->student = User::factory()->create();
    $this->course = Course::query()->create([
        'slug' => 'btrfs-p3-rollout-check',
        'title' => 'Btrfs P3 Rollout Test',
    ]);
});

function rolloutPairAllocator(int $octet): void
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

it('records legacy DIR pool and existing images when rollout is off', function (): void {
    config()->set('infrastructure.ctf_btrfs_rollout_enabled', false);
    config()->set('infrastructure.lab_image', 'imnehnet-gi-victim-v1');
    config()->set('infrastructure.attacker_image', 'imnehnet-gi-attacker-v1');
    rolloutPairAllocator(151);

    $session = app(LabSessionManager::class)->start($this->student, $this->course);

    expect($session->victimEnvironment->storage_pool)->toBe('default')
        ->and($session->victimEnvironment->image)->toBe('imnehnet-gi-victim-v1')
        ->and($session->attackerEnvironment->storage_pool)->toBe('default')
        ->and($session->attackerEnvironment->image)->toBe('imnehnet-gi-attacker-v1');
});

it('persists Btrfs pool and P3 image for a new pair even after rollout flag changes', function (): void {
    config()->set('infrastructure.ctf_btrfs_rollout_enabled', true);
    config()->set('infrastructure.ctf_btrfs_pool', 'imnehnet-btrfs-prod');
    config()->set('infrastructure.ctf_attacker_image', 'imnehnet-gi-attacker-p3');
    config()->set('infrastructure.ctf_victim_image', 'imnehnet-gi-victim-v1');
    rolloutPairAllocator(152);

    $session = app(LabSessionManager::class)->start($this->student, $this->course);

    expect($session->victimEnvironment->storage_pool)->toBe('imnehnet-btrfs-prod')
        ->and($session->victimEnvironment->image)->toBe('imnehnet-gi-victim-v1')
        ->and($session->attackerEnvironment->storage_pool)->toBe('imnehnet-btrfs-prod')
        ->and($session->attackerEnvironment->image)->toBe('imnehnet-gi-attacker-p3');

    // Disabling rollout must affect future creations only, not this lab.
    config()->set('infrastructure.ctf_btrfs_rollout_enabled', false);

    $victim = LabEnvironment::query()->findOrFail($session->victim_environment_id);
    $attacker = LabEnvironment::query()->findOrFail($session->attacker_environment_id);
    expect($victim->storage_pool)->toBe('imnehnet-btrfs-prod')
        ->and($victim->image)->toBe('imnehnet-gi-victim-v1')
        ->and($attacker->storage_pool)->toBe('imnehnet-btrfs-prod')
        ->and($attacker->image)->toBe('imnehnet-gi-attacker-p3');

    $reused = app(LabSessionManager::class)->start($this->student, $this->course);
    expect($reused->getKey())->toBe($session->getKey());
});
