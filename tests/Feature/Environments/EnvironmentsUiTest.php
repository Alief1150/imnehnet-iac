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
use App\Models\LabEnvironment;
use App\Models\User;
use App\Services\EnvironmentLifecycleService;
use App\Support\Lucide;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The operational table is the screen an operator lives in, so it is checked
 * against the specification's columns and its state vocabulary rather than
 * only for not throwing.
 */
test('the environment table renders every column the specification names', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    LabEnvironment::factory()->create([
        'container_name' => 'lab-demo-01',
        'internal_ip' => '10.20.0.205',
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Running,
        'tofu_workspace' => 'env-lab-demo-01',
        'cpu_limit' => 2,
        'memory_limit_mb' => 2048,
    ]);

    $this->actingAs($admin);

    Livewire::actingAs($admin)
        ->test(ListEnvironments::class)
        ->assertOk()
        ->assertCanSeeTableRecords(['lab-demo-01']);

    // Every heading from the specification, so a renamed column is a failure
    // rather than something nobody notices until a demo.
    foreach ([
        'Container',
        'IP address',
        'Image',
        'CPU',
        'Memory',
        'Provisioning',
        'Runtime',
        'Workspace',
        'Last update',
    ] as $heading) {
        Livewire::actingAs($admin)
            ->test(ListEnvironments::class)
            ->assertSee($heading);
    }
});

test('lifecycle and runtime are shown as two separate states', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    // Ready in the lifecycle sense, stopped as a container. Collapsing these
    // into one column would make the record unreadable.
    LabEnvironment::factory()->create([
        'container_name' => 'lab-demo-02',
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Stopped,
    ]);

    Livewire::actingAs($admin)
        ->test(ListEnvironments::class)
        ->assertOk()
        ->assertSee('Ready')
        ->assertSee('Stopped');
});

test('an unobserved address is shown as unassigned rather than blank', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    LabEnvironment::factory()->create([
        'container_name' => 'lab-demo-03',
        'internal_ip' => null,
        'status' => EnvironmentStatus::Provisioning,
        'runtime_status' => RuntimeStatus::Unknown,
    ]);

    // A blank cell reads as an oversight; an explicit placeholder reads as an
    // honest gap.
    Livewire::actingAs($admin)
        ->test(ListEnvironments::class)
        ->assertOk()
        ->assertSee('Not assigned')
        ->assertSee('Unknown');
});

test('the reference environment is listed but offers no lifecycle action', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    LabEnvironment::factory()->create([
        'container_name' => 'lab-tofu-test',
        'tofu_workspace' => 'default',
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Running,
    ]);

    $lifecycle = app(EnvironmentLifecycleService::class);
    $reference = LabEnvironment::query()->where('container_name', 'lab-tofu-test')->sole();

    expect($lifecycle->allowedActions($reference))->toBe(['refresh'])
        ->and($reference->isProtected())->toBeTrue();

    Livewire::actingAs($admin)
        ->test(ListEnvironments::class)
        ->assertOk()
        ->assertSee('Protected reference environment');
});

test('the dashboard reports live figures and its own heading', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    LabEnvironment::factory()->create(['status' => EnvironmentStatus::Ready, 'runtime_status' => RuntimeStatus::Running]);
    LabEnvironment::factory()->error()->create();
    LabEnvironment::factory()->create(['status' => EnvironmentStatus::Stopped, 'runtime_status' => RuntimeStatus::Stopped]);

    $this->actingAs($admin)
        ->get(EnvironmentResource::getUrl('index', panel: 'admin'))
        ->assertOk();

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertOk()
        // The five figures the specification names.
        ->assertSee('Total Environments')
        ->assertSee('Running')
        ->assertSee('Ready')
        ->assertSee('Stopped')
        ->assertSee('Errors');
});

test('the dashboard title is the one the specification asks for', function () {
    $page = new Dashboard;

    expect($page->getHeading())->toBe('IMNEHNET DASHBOARD')
        ->and($page->getSubheading())->toBe('Cybersecurity Infrastructure Control Plane');
});

test('the brand reads IMNEHNET', function () {
    expect(Filament\Facades\Filament::getPanel('admin')->getBrandName())->toBe('IMNEHNET');
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
        // An unknown name degrades to something ordinary rather than breaking.
        ->and(Lucide::icon('no-such-icon'))->toBeNull()
        ->and(Lucide::svg('server'))->toContain('<svg');
});
