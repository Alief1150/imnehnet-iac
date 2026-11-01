<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\LabEnvironment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Every page, rendered once, as every role that can reach it.
 *
 * Three fatal errors went through this project in one session and php -l passed
 * all three, because none of them was a parse error. Each was raised when a class
 * was loaded: a method made protected that the parent declares public, a Livewire
 * view with more than one root element, a middleware that had dropped a variadic
 * from its parent's signature. Every one took down every page of both panels at
 * once, and each was only ever caught by opening a page.
 *
 * A test suite sees these only by accident, when another test happens to touch the
 * same route. This one does not rely on that: it walks the routing table, so a
 * page nobody has ever visited is still rendered.
 */

/**
 * A real identifier for each route parameter, for one viewer.
 *
 * Substitution is by parameter name because the same {record} means a different
 * model on each resource. The environment also has to belong to the viewer: a
 * student's environment detail page resolves their own row and refuses anyone
 * else's, so handing both roles the same id would report a correct refusal as a
 * broken page.
 *
 * @return array<string, string>
 */
function realIdentifiersFor(?User $viewer): array
{
    $environment = $viewer === null
        ? LabEnvironment::query()->first()
        : LabEnvironment::query()->where('user_id', $viewer->getKey())->first();

    $environment ??= LabEnvironment::factory()->create([
        'user_id' => $viewer?->getKey(),
    ]);

    $user = User::query()->where('role', '!=', UserRole::Admin->value)->first()
        ?? User::factory()->create(['role' => UserRole::Student]);

    $log = ActivityLog::query()->first()
        ?? ActivityLog::query()->create([
            'action' => 'LOGIN',
            'status' => 'completed',
            'description' => 'Placeholder written so a detail page has a row to resolve.',
        ]);

    return [
        'record' => (string) $environment->getKey(),
        'environment' => (string) $environment->getKey(),
        'user' => (string) $user->getKey(),
        'activityLog' => (string) $log->getKey(),
        'tenant' => '1',
        'widget' => '1',
        'livewire' => 'placeholder',
    ];
}

/**
 * Every page a browser can be sent to, with placeholders filled in for a viewer.
 *
 * Both panels and the plain web routes. The registration page is a Livewire page
 * like any other and had exactly as much power to take the site down; leaving it
 * out because its route is not called filament.something is how the sweep would
 * have missed it.
 *
 * Export and import downloads are excluded. They are file responses rather than
 * pages: they answer from an export record that has to exist and be finished, so
 * a GET against one in an empty database is a missing record rather than a broken
 * page. Sweeping them measures nothing about whether the interface renders.
 *
 * @return array<string, string>
 */
function pageGetRoutes(?User $viewer = null): array
{
    $identifiers = realIdentifiersFor($viewer);

    $paths = [];

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();

        if ($name === null) {
            continue;
        }

        $isPanel = str_starts_with($name, 'filament.');
        $isWeb = str_contains($name, 'register') || $name === 'login';

        if (! $isPanel && ! $isWeb) {
            continue;
        }

        if (str_contains($name, 'exports.') || str_contains($name, 'imports.')) {
            continue;
        }

        $methods = $route->methods();

        // Only GET. A POST-only endpoint cannot be rendered by a request, and
        // asserting on it would be asserting nothing.
        if (! in_array('GET', $methods, true) || in_array('POST', $methods, true)) {
            continue;
        }

        // The Livewire transport endpoints answer from a snapshot in the payload,
        // not from the path.
        if (str_contains($name, 'livewire')) {
            continue;
        }

        $path = preg_replace_callback(
            '/\{([^}?]+)\??\}/',
            function (array $matches) use ($identifiers): string {
                return $identifiers[$matches[1]] ?? '1';
            },
            '/'.ltrim($route->uri(), '/'),
        );

        $paths[$name] = (string) $path;
    }

    return $paths;
}

/**
 * Render every page for a viewer and describe what went wrong.
 *
 * @return list<string>
 */
function faultsFor(?User $viewer): array
{
    $viewer === null ? auth()->logout() : auth()->login($viewer);

    $faults = [];

    foreach (pageGetRoutes($viewer) as $name => $path) {
        $response = test()->get($path);
        $status = $response->getStatusCode();
        $body = (string) $response->getContent();

        $faults = array_merge($faults, array_map(
            fn (string $why): string => sprintf('%s (%s): %s', $name, $path, $why),
            array_filter([
                $status >= 500 ? 'server error '.$status : null,
                $status === 404 ? 'nothing resolves behind the route' : null,
                str_contains($body, 'FatalError') ? 'fatal error in the body' : null,
                str_contains($body, 'Whoops') ? 'error page in the body' : null,
            ]),
        ));
    }

    auth()->logout();

    return $faults;
}

/**
 * Runs first, and deliberately so.
 *
 * An incompatible declaration raises a fatal the moment the class is loaded. The
 * sweeps below render pages, and a page that renders the offending class takes the
 * whole suite down with it before this assertion is ever reached. Checked first,
 * the fault is reported as a failure with a reason; checked later, it is only ever
 * a vanished Tests: line.
 */
test('every class in app/ can be loaded', function () {
    // The other half of the guard, and the half that actually catches a
    // class-loading fault.
    //
    // An incompatible method declaration is raised when the class is loaded, not
    // when the file is parsed, so php -l passes and a sweep only notices if some
    // test happens to render the page that uses it. Loading each class on its own
    // raises the fault directly, with no page involved.
    //
    // Run in a subprocess on purpose: a fatal here would kill this suite and print
    // no Tests: line at all, which is a detection nobody reads. A subprocess turns
    // the same fault into an exit code this test can assert on.
    $classes = [];

    $directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($directory as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relative = Str::after($file->getPathname(), app_path().DIRECTORY_SEPARATOR);

        // Pest.php and TestCase.php in the app tree are not application classes.
        if (str_ends_with($relative, 'Pest.php') || str_contains($relative, 'Tests')) {
            continue;
        }

        $classes[] = 'App\\'.str_replace(['/', '.php'], ['\\', ''], $relative);
    }

    sort($classes);

    expect($classes)->not->toBeEmpty();

    // No opening tag: php -r executes the string as code, and a leading <?php
    // is a parse error in that mode.
    $script = <<<'PHP'
        require getcwd().'/vendor/autoload.php';
        $app = require getcwd().'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $failed = [];
        foreach (array_slice($argv, 1) as $class) {
            if (! class_exists($class) && ! interface_exists($class) && ! trait_exists($class) && ! enum_exists($class)) {
                $failed[] = $class.' (not found)';
            }
        }
        echo implode("\n", $failed);
        PHP;

    $command = [PHP_BINARY, '-r', $script, ...$classes];

    $process = proc_open(
        implode(' ', array_map('escapeshellarg', $command)),
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    $exit = proc_close($process);

    expect(trim($stderr))->toBe('')
        ->and(trim($stdout))->toBe('')
        ->and($exit)->toBe(0);
});

test('the pages are enumerated, or this test is guarding nothing', function () {
    // A sweep that silently walks zero routes passes for ever. If the naming
    // convention changes, this fails rather than quietly stopping.
    $paths = pageGetRoutes();

    expect($paths)->not->toBeEmpty();

    foreach ([
        'filament.admin.pages.dashboard',
        'filament.admin.resources.environments.index',
        'filament.admin.resources.activity-logs.view',
        'filament.student.pages.dashboard',
        'filament.student.resources.environments.index',
        'filament.student.resources.environments.view',
        // Not a filament route, and it is still a page that can break.
        'register',
    ] as $expected) {
        expect(array_keys($paths))->toContain($expected);
    }
});

test('every page renders for an administrator', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin);

    expect(faultsFor($admin))->toBe([]);
});

test('every page in the student panel renders for a student', function () {
    $student = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($student);

    expect(faultsFor($student))->toBe([]);
});

test('the registration page renders for an anonymous visitor', function () {
    // Its own case because it is the one page anyone can reach without an account,
    // and the only place a new student is created.
    $page = array_intersect_key(pageGetRoutes(), ['register' => true]);

    expect($page)->toHaveCount(1);

    expect(faultsFor(null))->toBe([]);
});

test('a student is redirected away from every administrative page', function () {
    // The sweeps above ask whether pages render. This asks whether the pages a
    // student must never reach are reachable at all, which is a different question
    // with its own way to be wrong.
    $student = User::factory()->create(['role' => UserRole::Student]);
    $this->actingAs($student);

    $leaked = [];

    foreach (pageGetRoutes($student) as $name => $path) {
        if (! str_starts_with($name, 'filament.admin.')) {
            continue;
        }

        // A 302 is the redirect to the student's own panel. A 403 would also be
        // safe, but it is not what this arrangement does and the difference is
        // worth noticing.
        if ($this->get($path)->getStatusCode() !== 302) {
            $leaked[] = sprintf('%s (%s)', $name, $path);
        }
    }

    expect($leaked)->toBe([]);
});
