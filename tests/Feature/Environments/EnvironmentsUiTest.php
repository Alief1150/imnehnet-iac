<?php

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
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

test('the dashboard is titled IMNEHNET DASHBOARD with the control plane subheading', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(Dashboard::getUrl(panel: 'admin'))
        ->assertOk()
        ->assertSee('IMNEHNET DASHBOARD')
        ->assertSee('Cybersecurity Infrastructure Control Plane');
});

test('the dashboard reports the five figures the specification names, from live data', function () {
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

test('navigation is grouped the way the specification lays it out', function () {
    $groups = [];

    foreach (Filament\Facades\Filament::getPanel('admin')->getResources() as $resource) {
        if ($resource::shouldRegisterNavigation()) {
            $groups[class_basename($resource)] = $resource::getNavigationGroup();
        }
    }

    expect($groups)->toMatchArray([
        'EnvironmentResource' => 'Environments',
        'ActivityLogResource' => 'Operations',
        'UserResource' => 'Administration',
    ]);
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
