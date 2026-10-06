<?php

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Infrastructure;
use App\Filament\Pages\Monitoring;
use App\Filament\Pages\Security;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Environments\EnvironmentResource;
use App\Filament\Resources\Environments\Pages\ListEnvironments;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\EnvironmentStatsOverview;
use App\Models\LabEnvironment;
use App\Models\User;
use App\Services\EnvironmentLifecycleService;
use App\Support\Lucide;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Column names and their configuration are asserted on the table definition
 * rather than on rendered HTML. Filament defers the table body and the
 * dashboard widgets, so a rendered assertion would be testing whether the
 * component loaded rather than whether the screen is right.
 */
function environmentColumnNames(): array
{
    // Read the columns off a real component instance rather than building a
    // Table by hand: the schema object Filament needs is not something a test
    // should be assembling.
    // Livewire::actingAs() rather than $this->actingAs(): a plain helper
    // function has no test instance bound to it.
    $component = Livewire::actingAs(User::factory()->create(['role' => 'admin']))
        ->test(ListEnvironments::class);

    return array_values(array_map(
        fn ($column): string => (string) $column->getName(),
        $component->instance()->getTable()->getColumns()
    ));
}

/**
 * @return array<string, string>
 */
function environmentStatFigures(): array
{
    $method = new ReflectionMethod(EnvironmentStatsOverview::class, 'getStats');
    $method->setAccessible(true);

    $out = [];

    foreach ($method->invoke(new EnvironmentStatsOverview) as $stat) {
        $out[(string) $stat->getLabel()] = (string) $stat->getValue();
    }

    return $out;
}

test('the environment table declares every column the specification names', function () {
    expect(environmentColumnNames())->toBe([
        'container_name',
        'internal_ip',
        'image',
        'cpu_limit',
        'memory_limit_mb',
        'status',
        'runtime_status',
        'tofu_workspace',
        'updated_at',
    ]);
});

test('lifecycle status and runtime status are two separate columns', function () {
    $names = environmentColumnNames();

    // Collapsing these would make "ready" mean something different on each
    // screen, and an environment can be ready in one sense and stopped in the
    // other.
    expect($names)->toContain('status')
        ->and($names)->toContain('runtime_status');
});

test('the environment list page renders without a server error', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    LabEnvironment::factory()->create([
        'container_name' => 'lab-demo-01',
        'internal_ip' => '10.20.0.205',
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Running,
        'tofu_workspace' => 'env-lab-demo-01',
    ]);

    $this->actingAs($admin)
        ->get(EnvironmentResource::getUrl('index', panel: 'admin'))
        ->assertOk();

    Livewire::actingAs($admin)->test(ListEnvironments::class)->assertOk();
});

test('lifecycle and runtime are reported independently from live data', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    // Ready in the lifecycle sense, stopped as a container.
    LabEnvironment::factory()->create([
        'container_name' => 'lab-demo-02',
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Stopped,
    ]);

    $environment = LabEnvironment::query()->where('container_name', 'lab-demo-02')->sole();

    expect($environment->status->label())->toBe('Ready')
        ->and($environment->runtime_status->label())->toBe('Stopped')
        ->and($environment->hasStateMismatch())->toBeTrue();
});

test('an unobserved address and runtime are shown as gaps, never invented', function () {
    $environment = LabEnvironment::factory()->create([
        'container_name' => 'lab-demo-03',
        'internal_ip' => null,
        'status' => EnvironmentStatus::Provisioning,
        'runtime_status' => RuntimeStatus::Unknown,
    ]);

    expect($environment->internal_ip)->toBeNull()
        ->and($environment->runtime_status->isKnown())->toBeFalse()
        // Reporting an unobserved container as stopped would make a broken
        // control plane look like a healthy one.
        ->and($environment->runtime_status)->not->toBe(RuntimeStatus::Stopped);
});

test('the reference environment is listed but offers no lifecycle action', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    LabEnvironment::factory()->create([
        'container_name' => 'lab-tofu-test',
        'tofu_workspace' => 'default',
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Running,
    ]);

    $reference = LabEnvironment::query()->where('container_name', 'lab-tofu-test')->sole();

    expect(app(EnvironmentLifecycleService::class)->allowedActions($reference))->toBe(['refresh'])
        ->and($reference->isProtected())->toBeTrue();

    $this->actingAs($admin)
        ->get(EnvironmentResource::getUrl('index', panel: 'admin'))
        ->assertOk();
});

/**
 * The dashboard heading and subheading.
 *
 * This used to assert the literal strings "IMNEHNET DASHBOARD" and "Cybersecurity
 * Infrastructure Control Plane". The dashboard was redesigned and both went, which is
 * the right outcome — they were the ugliest text on the page.
 *
 * What replaces them is not a weaker version of the same test. Asserting the word
 * "Dashboard" would be close to vacuous: the string is in the navigation, the page
 * title and the layout, so it would pass against a dashboard that renders nothing at
 * all. What is worth protecting is the property the strings were standing in for, which
 * is that the dashboard describes itself differently depending on who is looking at it.
 * An administrator sees the estate; a student sees their own workspace. If that ever
 * collapses to one wording, an administrator is reading a page addressed to a student,
 * and no amount of literal string matching would have noticed.
 */
test('the dashboard addresses the administrator and the student differently', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($admin)
        ->get(Dashboard::getUrl(panel: 'admin'))
        ->assertOk()
        ->assertSee('Overview of your cybersecurity lab infrastructure');

    $this->actingAs($student)
        ->get(Dashboard::getUrl(panel: 'student'))
        ->assertOk()
        ->assertSee('Your cybersecurity lab workspace');

    // And the two are genuinely different, asked of the method under each role rather
    // than scraped from markup, so this cannot pass by both roles happening to render
    // both strings somewhere on the page.
    //
    // The role is re-established before each call because getSubheading() reads it from
    // the session. Capturing both first and comparing afterwards compares two reads of
    // the same session, which is how this first returned the student's wording twice.
    $subheading = new ReflectionMethod(new Dashboard(), 'getSubheading');

    $this->actingAs($admin);
    $asAdmin = $subheading->invoke(new Dashboard());

    $this->actingAs($student);
    $asStudent = $subheading->invoke(new Dashboard());

    expect($asAdmin)
        ->toBeString()
        ->not->toBe($asStudent)
        ->and($asStudent)->toBeString();
});

test('the dashboard reports the five figures the specification names, from live data', function () {
    // The tiles are scoped to what the viewer may see, and the specification asks
    // for the five figures of the estate as a whole, which is the administrator's
    // view. Asserting them without signing in counted nothing, and asserting them
    // as a student would be asserting a smaller number than the specification
    // names.
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    LabEnvironment::factory()->create(['status' => EnvironmentStatus::Ready, 'runtime_status' => RuntimeStatus::Running]);
    LabEnvironment::factory()->create(['status' => EnvironmentStatus::Ready, 'runtime_status' => RuntimeStatus::Running]);
    LabEnvironment::factory()->error()->create();
    LabEnvironment::factory()->create(['status' => EnvironmentStatus::Stopped, 'runtime_status' => RuntimeStatus::Stopped]);

    // The errored environment still has a running container, so it appears in
    // both figures. The counts are separate questions and are not expected to
    // add up to the total.
    expect(environmentStatFigures())->toBe([
        'Total Environments' => '4',
        'Running' => '3',
        'Ready' => '2',
        'Stopped' => '1',
        'Errors' => '1',
    ]);
});

test('the brand reads IMNEHNET', function () {
    expect(Filament\Facades\Filament::getPanel('admin')->getBrandName())->toBe('IMNEHNET');
});

/**
 * Navigation grouping on the admin panel.
 *
 * The groups were renamed when the sidebar was regrouped: Environments became
 * LAB MANAGEMENT and Administration became USERS. The literal names are updated, but
 * the second half is the part that was missing and is now asserted — that every
 * resource *and* every page sits in some group.
 *
 * Filament renders an ungrouped item loose in the sidebar with no heading above it, so
 * one resource left out of a group is invisible in review and only shows up as an item
 * floating above the sections. toMatchArray cannot see that: it passes on the entries it
 * names regardless of whether a fourth one exists. The specification names three
 * resources; the panel now has three resources and five pages, and the pages are
 * exactly where grouping mistakes show up.
 */
test('navigation is grouped the way the sidebar lays it out', function () {
    $panel = Filament\Facades\Filament::getPanel('admin');

    $groups = [];

    foreach ($panel->getResources() as $resource) {
        if ($resource::shouldRegisterNavigation()) {
            $groups[class_basename($resource)] = $resource::getNavigationGroup();
        }
    }

    expect($groups)->toMatchArray([
        'EnvironmentResource' => 'LAB MANAGEMENT',
        'ActivityLogResource' => 'OPERATIONS',
        'UserResource' => 'USERS',
    ]);

    // A resource with no group is always a mistake. Filament draws an ungrouped entry
    // loose in the sidebar, above the sections rather than inside one, and toMatchArray
    // cannot see that: it passes on the entries it names whether or not a fourth sits
    // beside them. So this is asserted over every resource, not only the three above.
    foreach ($panel->getResources() as $resource) {
        if ($resource::shouldRegisterNavigation()) {
            expect($resource::getNavigationGroup())
                ->toBeString(
                    class_basename($resource).' registers no navigation group, so the sidebar renders it loose',
                );
        }
    }

    // Pages are held to the same rule, except the ones meant to sit at the top alone.
    //
    // Dashboard and Terminal are ungrouped by design and always were: the dashboard is
    // the first thing in the sidebar and a heading above it is noise. Asserting they
    // were grouped would be asserting a worse sidebar. Naming them makes the exception
    // visible — a third ungrouped page fails here, and has to be argued for rather than
    // arriving by omission.
    //
    // getPages() on this Filament version returns file path => page class name, as
    // strings: not page objects, and not keyed by class. Only shouldRegisterNavigation()
    // is asked of them; the hidden flag lives on the registration rather than the class,
    // and reaching it would mean building that registration for no gain here.
    $deliberatelyTopLevel = ['Dashboard', 'Terminal'];

    $pages = [];

    foreach ($panel->getPages() as $class) {
        if ($class::shouldRegisterNavigation()) {
            $pages[class_basename($class)] = $class::getNavigationGroup();
        }
    }

    expect($pages)->not->toBeEmpty();

    foreach ($pages as $class => $group) {
        if (in_array($class, $deliberatelyTopLevel, true)) {
            expect($group)->toBeNull("{$class} was moved out of its top-level place");

            continue;
        }

        expect($group)
            ->toBeString("{$class} registers no navigation group, so the sidebar renders it loose");
    }
});

test('every navigation entry carries a Lucide icon as markup', function () {
    // A name would be resolved through the blade-icons default set, which only
    // knows Heroicons, and would raise SvgNotFound on the sidebar.
    foreach ([
        Dashboard::class,
        Infrastructure::class,
        Monitoring::class,
        Security::class,
        EnvironmentResource::class,
        ActivityLogResource::class,
        UserResource::class,
    ] as $entry) {
        $icon = $entry::getNavigationIcon();

        expect($icon, $entry.' must have an icon')->toBeInstanceOf(Htmlable::class)
            ->and((string) $icon)->toContain('<svg')
            ->and((string) $icon)->toContain('data-lucide');
    }
});

test('the generated icon set is real SVG from the installed package', function () {
    $map = Lucide::map();

    expect($map)->not->toBeEmpty()
        ->and(Lucide::has('server'))->toBeTrue()
        ->and(Lucide::has('circle-check'))->toBeTrue()
        // An unknown name degrades to null so a caller can fall back rather
        // than render an icon that means nothing.
        ->and(Lucide::icon('no-such-icon'))->toBeNull()
        ->and(Lucide::svg('server'))->toContain('<svg');
});
