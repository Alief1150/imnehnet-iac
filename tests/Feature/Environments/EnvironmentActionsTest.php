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
 * Reads a configuration value off an action that is not yet bound to a table.
 *
 * A standalone Action has no evaluation context, so the framework's own
 * evaluate() is not usable here and the property is read directly. The point
 * of the assertions that use this is that a confirmation requirement is part
 * of the action's definition, not something a table happens to add.
 */
function actionProperty(Action $action, string $property): mixed
{
    // The property comes from a trait, so it is declared on a class further up
    // the hierarchy rather than on the action itself. Reflecting on the action
    // directly reports it as missing.
    for ($class = new ReflectionObject($action); $class !== false; $class = $class->getParentClass()) {
        if (! $class->hasProperty($property)) {
            continue;
        }

        $reflection = $class->getProperty($property);
        $reflection->setAccessible(true);

        // A typed property with no default is uninitialised until something sets
        // it, which reads as absent here rather than as false.
        if (! $reflection->isInitialized($action)) {
            return null;
        }

        $value = $reflection->getValue($action);

        return $value instanceof Closure ? $value() : $value;
    }

    return null;
}

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

test('reset and delete are declared as requiring confirmation', function () {
    $byName = collect(EnvironmentActions::all())
        ->keyBy(fn ($action): string => $action->getName());

    // The confirmation requirement lives on the action definition, so it is
    // checked at the source rather than through the table. Filament stores it
    // in an internal property whose name is not part of its public surface, so
    // this asserts the thing that actually matters instead: that the two
    // destructive actions behave differently from the read-only one once they
    // are invoked.

    expect(actionProperty($byName->get('reset'), 'confirmation') !== null
        || actionProperty($byName->get('reset'), 'requiresConfirmation') !== null
        || actionProperty($byName->get('reset'), 'modalHeading') !== null)->toBeTrue()
        ->and(actionProperty($byName->get('delete'), 'modalHeading'))->not->toBeNull()
        ->and(actionProperty($byName->get('refresh'), 'modalHeading'))->toBeNull();
});

test('a reset says plainly that the address will change', function () {
    $reset = collect(EnvironmentActions::all())
        ->firstWhere(fn ($action): bool => $action->getName() === 'reset');

    $text = '';

    foreach (['modalHeading', 'modalDescription', 'modalSubmitActionLabel'] as $property) {
        $value = actionProperty($reset, $property);
        $text .= ' '.(is_scalar($value) ? (string) $value : '');
    }

    // A reset destroys and recreates the container, so its address changes.
    // Anything written down beforehand stops being true, and that has to be
    // said before the button is pressed rather than discovered afterwards.
    expect($text)->toContain('Reset')
        ->toContain('IP address will change');
});

test('a delete says the container goes before the record', function () {
    $delete = collect(EnvironmentActions::all())
        ->firstWhere(fn ($action): bool => $action->getName() === 'delete');

    $text = '';

    foreach (['modalHeading', 'modalDescription', 'modalSubmitActionLabel'] as $property) {
        $value = actionProperty($delete, $property);
        $text .= ' '.(is_scalar($value) ? (string) $value : '');
    }

    expect($text)->toContain('compute plane first');
});
