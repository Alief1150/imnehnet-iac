<?php

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Terminal;
use App\Filament\Resources\Environments\EnvironmentResource;
use App\Filament\Resources\Environments\Pages\CreateEnvironment;
use App\Filament\Widgets\EnvironmentStatsOverview;
use App\Filament\Widgets\RecentEnvironments;
use App\Jobs\ProvisionEnvironment;
use App\Models\LabEnvironment;
use App\Models\User;
use App\Support\EnvironmentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The student path, which is the path the portal exists for.
 *
 * The list page was scoped to the owner and the form took the owner from the
 * session, but the dashboard was not: two of its widgets counted and listed
 * LabEnvironment straight off the model, so every student saw every other
 * student's container name and address on the front page. These cover the whole
 * journey, because a student who cannot create a lab has nothing else to do here.
 */

function aStudent(string $name = 'student-one'): User
{
    return User::factory()->create(['role' => UserRole::Student, 'name' => $name]);
}

function anEnvironmentFor(?User $owner, string $name, string $ip): LabEnvironment
{
    return LabEnvironment::factory()->create([
        'container_name' => $name,
        'tofu_workspace' => EnvironmentWorkspace::forContainer($name),
        'user_id' => $owner?->getKey(),
        'internal_ip' => $ip,
        'status' => EnvironmentStatus::Ready,
        'runtime_status' => RuntimeStatus::Running,
    ]);
}

/**
 * The number the dashboard's Total Environments tile renders for a viewer.
 *
 * Asked of the widget rather than read off the page: the figure is a bare integer
 * in the markup, so asserting a "1" appears somewhere would pass for the wrong
 * reason.
 */
function dashboardTotalFor(User $viewer): int|string
{
    $widget = app(EnvironmentStatsOverview::class);

    $method = new ReflectionMethod($widget, 'getStats');
    $method->setAccessible(true);

    foreach ($method->invoke($widget) as $stat) {
        if ($stat->getLabel() === 'Total Environments') {
            return $stat->getValue();
        }
    }

    throw new RuntimeException('The Total Environments tile is not on the dashboard.');
}

test('a student reaches the create page and is asked for what a lab needs', function () {
    $this->actingAs(aStudent())
        ->get(EnvironmentResource::getUrl('create', panel: 'admin'))
        ->assertSuccessful()
        ->assertSee('Container name')
        ->assertSee('Image')
        // Without these the student cannot pick a size, and a default they never
        // chose is not a choice.
        ->assertSee('CPU limit')
        ->assertSee('Memory limit');
});

test('a student creating a lab owns it and the work is queued', function () {
    Queue::fake();

    $student = aStudent();

    Livewire::actingAs($student)
        ->test(CreateEnvironment::class)
        ->fillForm([
            'container_name' => 'lab-praktikum-1',
            'image' => 'images:ubuntu/24.04',
            'cpu_limit' => 1,
            'memory_limit_mb' => 1024,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $environment = LabEnvironment::query()->where('container_name', 'lab-praktikum-1')->first();

    // The owner comes from the session, so this is the check the whole
    // self-service model rests on.
    expect($environment)->not->toBeNull()
        ->and($environment->user_id)->toBe($student->getKey())
        ->and($environment->tofu_workspace)->toBe('env-lab-praktikum-1')
        // Provisioning is the lifecycle service's transition, never the form's.
        ->and($environment->status)->toBe(EnvironmentStatus::Provisioning);

    Queue::assertPushed(ProvisionEnvironment::class, 1);
});

test('the recent environments widget lists a student only their own labs', function () {
    $student = aStudent();
    $mine = anEnvironmentFor($student, 'lab-praktikum-1', '10.20.0.240');
    $theirs = anEnvironmentFor(aStudent('student-two'), 'lab-rahasia-mahasiswa', '10.20.0.241');

    // The widget used to read LabEnvironment straight off the model, so the name
    // and the address of another student's environment were on their front page.
    Livewire::actingAs($student)
        ->test(RecentEnvironments::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

test('an administrator still sees every environment on the dashboard', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $first = anEnvironmentFor(aStudent('student-lima'), 'lab-empat', '10.20.0.260');
    $second = anEnvironmentFor(aStudent('student-enam'), 'lab-lima', '10.20.0.261');

    // Scoping the dashboard must not have quietly hidden the estate from whoever
    // operates it, so the same widget is checked from the other side.
    Livewire::actingAs($admin)
        ->test(RecentEnvironments::class)
        ->assertCanSeeTableRecords([$first, $second]);
});

test('the dashboard figures count only the environments the viewer may see', function () {
    $student = aStudent();
    anEnvironmentFor($student, 'lab-satu', '10.20.0.250');
    anEnvironmentFor(aStudent('student-tiga'), 'lab-dua', '10.20.0.251');
    anEnvironmentFor(aStudent('student-empat'), 'lab-tiga', '10.20.0.252');

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    // Three environments exist and the student owns one. The figures used to read
    // three for everybody, which told a student how much everyone else was running.
    expect(LabEnvironment::query()->count())->toBe(3);

    $this->actingAs($student);
    expect(dashboardTotalFor($student))->toBe(1);

    $this->actingAs($admin);
    expect(dashboardTotalFor($admin))->toBe(3);
});

test("another student's environment does not exist as far as this student is concerned", function () {
    $student = aStudent();
    $other = anEnvironmentFor(aStudent('student-tujuh'), 'lab-rahasia-2', '10.20.0.270');

    // Not found rather than forbidden. Filament resolves the record through the
    // scoped query, so a record outside it is absent, and answering 403 would
    // confirm that somebody else's environment exists.
    $this->actingAs($student)
        ->get(EnvironmentResource::getUrl('view', ['record' => $other], panel: 'admin'))
        ->assertNotFound();
});

test('a student reaches the terminal and is offered only their own labs', function () {
    $student = aStudent();
    anEnvironmentFor($student, 'lab-terminal-saya', '10.20.0.280');
    anEnvironmentFor(aStudent('student-delapan'), 'lab-terminal-milik-mereka', '10.20.0.281');

    $this->actingAs($student)
        ->get(Terminal::getUrl(panel: 'admin'))
        ->assertSuccessful()
        ->assertSee('lab-terminal-saya')
        ->assertDontSee('lab-terminal-milik-mereka');
});