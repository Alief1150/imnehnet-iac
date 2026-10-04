<?php

use App\Enums\EnvironmentStatus;
use App\Filament\Widgets\NeedsAttention;
use App\Filament\Widgets\PeopleOverview;
use App\Infrastructure\Health\CommandRunner;
use App\Models\LabEnvironment;
use App\Models\User;
use App\Services\ComputePlaneInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The dashboard tiles that answer "what needs me now".
 *
 * Recent Activity used to sit here and repeated the Activity Logs page exactly.
 * Needs attention replaced it, and the compute plane tile inside it is the only
 * place in the portal that asks Incus what exists rather than asking the
 * database, which is the whole reason a container running with no record can be
 * seen at all.
 *
 * Every one of these reaches for a fake command runner, because a tile that
 * silently stopped probing would still render, and a test that passed against
 * whatever happened to be running would not notice.
 */

/**
 * The value a widget renders for a given tile label.
 */
function widgetStat(string $widgetClass, string $label): mixed
{
    $widget = app($widgetClass);
    $method = new ReflectionMethod($widget, 'getStats');
    $method->setAccessible(true);

    foreach ($method->invoke($widget) as $stat) {
        if ($stat->getLabel() === $label) {
            return $stat->getValue();
        }
    }

    return null;
}

/**
 * Put a fixed container list on the compute plane.
 */
function pretendComputePlane(array $names): void
{
    $commands = Mockery::mock(CommandRunner::class);
    $commands->shouldReceive('run')->andReturn([
        'successful' => true,
        'stdout' => json_encode(array_map(
            fn (string $name): array => ['name' => $name, 'status' => 'Running'],
            $names,
        )),
        'stderr' => '',
    ]);

    app()->instance(ComputePlaneInventory::class, new ComputePlaneInventory($commands));
}

/**
 * Make the compute plane unreachable.
 */
function pretendComputePlaneIsDown(): void
{
    $commands = Mockery::mock(CommandRunner::class);
    $commands->shouldReceive('run')->andReturn([
        'successful' => false,
        'stdout' => '',
        'stderr' => 'Command unavailable or failed.',
    ]);

    app()->instance(ComputePlaneInventory::class, new ComputePlaneInventory($commands));
}

test('a student is shown their own failed environment by name', function () {
    $student = User::factory()->create(['role' => \App\Enums\UserRole::Student]);

    LabEnvironment::factory()->error('The apply failed.')->create([
        'container_name' => 'lab-gagal-saya',
        'user_id' => $student->getKey(),
    ]);

    $this->actingAs($student);

    expect(widgetStat(NeedsAttention::class, 'Failed'))->toBe(1)
        ->and(NeedsAttention::canView())->toBeTrue();
});

test("a student is not shown another student's failed environment", function () {
    $student = User::factory()->create(['role' => \App\Enums\UserRole::Student]);

    LabEnvironment::factory()->error('The apply failed.')->create([
        'container_name' => 'lab-gagal-mereka',
        'user_id' => User::factory()->create()->getKey(),
    ]);

    $this->actingAs($student);

    expect(widgetStat(NeedsAttention::class, 'Failed'))->toBe(0);
});

test('an operation is called stuck only once it is old enough', function () {
    $student = User::factory()->create(['role' => \App\Enums\UserRole::Student]);

    // Freshly in flight. Provisioning legitimately takes minutes, and a line
    // that flagged a healthy apply would train everyone to ignore the tile.
    LabEnvironment::factory()->create([
        'container_name' => 'lab-sedang-jalan',
        'user_id' => $student->getKey(),
        'status' => EnvironmentStatus::Provisioning,
        'updated_at' => now()->subMinutes(2),
    ]);

    $this->actingAs($student);
    expect(widgetStat(NeedsAttention::class, 'Stuck in flight'))->toBe(0);

    // The same record, old enough.
    LabEnvironment::query()->where('container_name', 'lab-sedang-jalan')
        ->update(['updated_at' => now()->subMinutes(40)]);

    expect(widgetStat(NeedsAttention::class, 'Stuck in flight'))->toBe(1);
});

test('the people figures are for administrators only', function () {
    $student = User::factory()->create(['role' => \App\Enums\UserRole::Student]);
    $admin = User::factory()->create(['role' => \App\Enums\UserRole::Admin]);
    User::factory()->count(2)->create(['role' => \App\Enums\UserRole::Student]);

    $this->actingAs($student);
    // The size of the cohort is not a student's business, and the account list
    // is not something a student should be able to enumerate.
    expect(PeopleOverview::canView())->toBeFalse();

    $this->actingAs($admin);
    expect(PeopleOverview::canView())->toBeTrue()
        ->and(widgetStat(PeopleOverview::class, 'Accounts'))->toBe(4)
        ->and(widgetStat(PeopleOverview::class, 'Students'))->toBe(3)
        ->and(widgetStat(PeopleOverview::class, 'Administrators'))->toBe(1);
});

test('a container with no record is reported, and the protected one is not', function () {
    $admin = User::factory()->create(['role' => \App\Enums\UserRole::Admin]);

    LabEnvironment::factory()->create(['container_name' => 'lab-tercatat']);

    pretendComputePlane([
        'lab-tercatat',        // has a record
        'lab-tofu-test',       // protected: no record on purpose
        'lab-tinggal-alone',   // running with nothing behind it
    ]);

    $this->actingAs($admin);

    // This is the question the portal could not previously ask at all.
    expect(widgetStat(NeedsAttention::class, 'Unmanaged containers'))->toBe(1);
});

test('an unreachable compute plane is reported as unverified, not as zero', function () {
    $admin = User::factory()->create(['role' => \App\Enums\UserRole::Admin]);

    pretendComputePlaneIsDown();

    $this->actingAs($admin);

    // Zero during an outage is indistinguishable from a clean plane, and the
    // entire point of this tile is that it cannot be read as good news.
    expect(widgetStat(NeedsAttention::class, 'Unmanaged containers'))->toBe('NOT VERIFIED');
});

test('a student is not shown the compute plane tile at all', function () {
    $student = User::factory()->create(['role' => \App\Enums\UserRole::Student]);
    pretendComputePlane(['lab-asing']);

    $this->actingAs($student);

    expect(widgetStat(NeedsAttention::class, 'Unmanaged containers'))->toBeNull();
});