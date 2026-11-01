<?php

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Filament\Resources\Environments\Pages\ListEnvironments;
use App\Filament\Resources\Environments\Tables\EnvironmentActions;
use App\Models\LabEnvironment;
use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/**
 * The specification defines which actions appear from which state. Getting that
 * table wrong means offering an operator a button that either races an
 * operation already running, or, worse, a button on the protected reference
 * environment.
 *
 * Visibility is asserted through Filament's own helpers rather than by
 * evaluating the closures directly, because Filament resolves a record action
 * through its own container and a bare closure check would not prove what the
 * operator actually sees.
 */
uses(RefreshDatabase::class);

/**
 * @param  list<string>  $visible
 * @param  list<string>  $hidden
 */
function assertActions(LabEnvironment $record, array $visible, array $hidden): void
{
    $admin = User::factory()->create(['role' => 'admin']);

    $component = Livewire::actingAs($admin)->test(ListEnvironments::class);

    foreach ($visible as $action) {
        $component->assertTableActionVisible($action, $record);
    }

    foreach ($hidden as $action) {
        $component->assertTableActionHidden($action, $record);
    }
}

test('a ready environment offers stop, reset, refresh and delete but not start', function () {
    $record = LabEnvironment::factory()->create([
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Running,
    ]);

    assertActions($record, ['stop', 'reset', 'refresh', 'delete'], ['start']);
});

test('a stopped environment offers start, reset and delete but not stop', function () {
    $record = LabEnvironment::factory()->create([
        'status' => EnvironmentStatus::Stopped,
        'runtime_status' => RuntimeStatus::Stopped,
    ]);

    assertActions($record, ['start', 'reset', 'refresh', 'delete'], ['stop']);
});

test('an environment mid-operation offers nothing but a read-only refresh', function () {
    foreach ([EnvironmentStatus::Provisioning, EnvironmentStatus::Configuring, EnvironmentStatus::Resetting, EnvironmentStatus::Deleting] as $status) {
        $record = LabEnvironment::factory()->create(['status' => $status]);

        assertActions($record, ['refresh'], ['start', 'stop', 'reset', 'delete']);
    }
});

test('a failed environment offers a refresh and a delete, never a blind retry', function () {
    $record = LabEnvironment::factory()->error()->create();

    assertActions($record, ['refresh', 'delete'], ['start', 'stop', 'reset']);
});

test('the protected reference environment offers only a refresh', function () {
    $record = LabEnvironment::factory()->create([
        'container_name' => 'lab-tofu-test',
        'tofu_workspace' => 'default',
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Running,
    ]);

    assertActions($record, ['refresh'], ['start', 'stop', 'reset', 'delete']);
});

test('a destructive action requires confirmation and a read-only one does not', function () {
    $byName = collect(EnvironmentActions::all())
        ->keyBy(fn ($action): string => $action->getName());

    // Read through the framework's own accessors. An earlier version of this
    // test reached into the action's private properties and had to be rewritten
    // four times as the internals moved; the public accessors say the same thing
    // and will not break when a trait is reorganised.
    expect($byName->get('reset')->isConfirmationRequired())->toBeTrue()
        ->and($byName->get('delete')->isConfirmationRequired())->toBeTrue()
        ->and($byName->get('start')->isConfirmationRequired())->toBeFalse()
        ->and($byName->get('stop')->isConfirmationRequired())->toBeFalse()
        ->and($byName->get('refresh')->isConfirmationRequired())->toBeFalse();
});

test('a reset says plainly that the address will change', function () {
    $reset = collect(EnvironmentActions::all())
        ->firstWhere(fn ($action): bool => $action->getName() === 'reset');

    $text = implode(' ', [
        (string) $reset->getModalHeading(),
        (string) $reset->getModalDescription(),
        (string) $reset->getModalSubmitActionLabel(),
    ]);

    // A reset destroys and recreates the container, so its address changes.
    // Anything written down beforehand stops being true, and that has to be
    // said before the button is pressed rather than discovered afterwards.
    expect($text)->toContain('Reset')
        ->toContain('IP address will change');
});

test('a delete says the container goes before the record', function () {
    $delete = collect(EnvironmentActions::all())
        ->firstWhere(fn ($action): bool => $action->getName() === 'delete');

    $text = implode(' ', [
        (string) $delete->getModalHeading(),
        (string) $delete->getModalDescription(),
        (string) $delete->getModalSubmitActionLabel(),
    ]);

    // Deleting the record first would leave a running container with nothing
    // tracking it, so the wording has to put the order straight.
    expect($text)->toContain('compute plane first');
});
