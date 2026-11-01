<?php

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Filament\Pages\Terminal;
use App\Models\LabEnvironment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The terminal page must render whatever the record happens to contain.
 *
 * A fatal on this page took out /student/terminal with a 500 and a stack trace: the
 * code called ->isRunning() on runtime_status without checking whether the column
 * held anything, and in production the column allowed it to hold nothing.
 *
 * Two things made this worth writing carefully.
 *
 * The route sweep did not catch it. That sweep renders every page, and it passed,
 * because the environment it rendered had a runtime status. A page that renders for
 * one shape of row and fatals for another is not a page that works, so this varies
 * the data, which is the axis the sweep does not move.
 *
 * The first attempt at this test could not have caught it either. runtime_status was
 * NOT NULL in the test database while nullable in production, so every attempt to
 * build the failing row raised a constraint violation instead of exercising the
 * page. The column is constrained now, so the null case is tested by nulling the
 * attribute on an in-memory model rather than by trying to store one, which is the
 * only honest way to test a guard against a value the schema now forbids.
 */

/**
 * Build an environment, with overrides merged on top.
 *
 * Merged rather than unioned: the union operator keeps the left operand's values,
 * so with the defaults on the left every override was silently discarded and all six
 * of these tests were building the same Running environment. It showed as a verdict
 * of no_session where a verdict about the runtime state was expected.
 *
 * @param  array<string, mixed>  $overrides
 */
function environmentWith(array $overrides = [], ?User $owner = null): LabEnvironment
{
    $owner ??= User::factory()->create();

    return LabEnvironment::factory()->create(array_merge([
        'user_id' => $owner->getKey(),
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Running,
        'terminal_enabled' => true,
    ], $overrides));
}

test('the terminal page renders when the runtime state has never been read', function () {
    // The state that caused the fatal. The attribute is nulled in memory rather than
    // stored, because the column is NOT NULL now and storing one is not possible.
    $environment = environmentWith();

    $environment->setAttribute('runtime_status', null);

    expect($environment->runtime_status)->toBeNull();

    $this->actingAs(User::factory()->create(['role' => 'admin']));

    $this->get(Terminal::getUrl(panel: 'admin'))->assertSuccessful();
});

test('an unread runtime state is reported as unknown, not as not running', function () {
    // The distinction matters. Nothing has said the container is stopped, only that
    // nobody has looked, and "NOT RUNNING" would be actionable and wrong.
    $environment = environmentWith();

    $environment->setAttribute('runtime_status', null);

    $state = (new Terminal())->statusFor($environment);

    expect($state['code'])->toBe('runtime_unknown')
        ->and($state['label'])->toBe('UNKNOWN');
});

test('a stopped container is still reported as not running', function () {
    // The guard must not swallow the real case it sits in front of.
    $state = (new Terminal())->statusFor(environmentWith([
        'runtime_status' => RuntimeStatus::Stopped,
    ]));

    expect($state['code'])->toBe('not_running')
        ->and($state['label'])->toBe('NOT RUNNING');
});

test('an unread runtime state stored as Unknown reads as not running', function () {
    // Unknown is how the value is stored now, and it must reach the page rather than
    // being treated as something to be tidied away.
    $state = (new Terminal())->statusFor(environmentWith([
        'runtime_status' => RuntimeStatus::Unknown,
    ]));

    expect($state['label'])->toBe('NOT RUNNING')
        ->and($state['detail'])->toContain('Unknown');
});

test('the terminal page renders for every runtime state the enum defines', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    foreach (RuntimeStatus::cases() as $runtime) {
        environmentWith(['runtime_status' => $runtime]);

        $this->actingAs($admin)
            ->get(Terminal::getUrl(panel: 'admin'))
            ->assertSuccessful();
    }
});

test('the terminal page renders when the terminal is switched off', function () {
    environmentWith(['terminal_enabled' => false]);

    $this->actingAs(User::factory()->create(['role' => 'admin']));

    $this->get(Terminal::getUrl(panel: 'admin'))->assertSuccessful();
});

test('a student with several environments in every state still reaches the page', function () {
    // The page lists a student's environments and reads each one's status. One odd
    // row among several must not take the list down, which is what the original
    // report looked like from the outside.
    $student = User::factory()->create(['role' => 'student']);

    environmentWith(['runtime_status' => RuntimeStatus::Unknown], $student);
    environmentWith(['runtime_status' => RuntimeStatus::Stopped], $student);
    environmentWith(['runtime_status' => RuntimeStatus::Running], $student);
    environmentWith(['runtime_status' => RuntimeStatus::Frozen], $student);
    environmentWith(['terminal_enabled' => false], $student);

    $this->actingAs($student);

    $this->get(Terminal::getUrl(panel: 'student'))->assertSuccessful();
});

test('no view of an environment dereferences a null runtime status', function () {
    // Structural rather than behavioural: names the specific mistake, so a
    // reintroduced one is obvious in the diff rather than in a stack trace.
    $source = file_get_contents((new ReflectionClass(Terminal::class))->getFileName());

    expect($source)->not->toContain('runtime_status->isRunning()')
        ->and($source)->not->toContain('runtime_status->label()');
});

test('runtime_status cannot be null again, which is why the guard is defence in depth', function () {
    // The two halves of the fix. The migration removed the way the value arrives;
    // this says so out loud, so a future change that reopens the column is noticed
    // here rather than in production.
    $schema = LabEnvironment::query()->getConnection()->getSchemaBuilder();

    $column = collect($schema->getColumns(LabEnvironment::query()->getModel()->getTable()))
        ->first(fn ($item) => ($item['name'] ?? $item->Field ?? null) === 'runtime_status');

    expect($column)->not->toBeNull();

    // Spelled differently between schema versions, so accept either rather than
    // failing on an unrelated upgrade.
    $nullable = $column['nullable'] ?? $column['Null'] ?? null;

    expect(in_array($nullable, [false, 'NO'], strict: false))->toBeTrue();
});