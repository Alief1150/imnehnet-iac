<?php

use App\Filament\Resources\Environments\Pages\CreateEnvironment;
use App\Models\LabEnvironment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A student sizes nothing.
 *
 * The brief says the only choices a student has are create, start, stop, reset and
 * delete. The create form was offering CPU and memory as inputs, which puts the
 * sizing decision in the student's hands. That is contrary to the brief on its own
 * terms, and it is also the only control in the portal through which one student
 * could ask the compute plane for more than it holds.
 *
 * The limits moved into configuration so the portal applies them to everyone. These
 * tests exist so they cannot quietly come back: a form that grows a memory field
 * again is a form that hands the decision back.
 */

/**
 * The field names the create form is allowed to offer.
 *
 * An allow list rather than a deny list. A deny list of the fields that were
 * removed would pass the moment somebody added a different field, which is the
 * thing worth catching.
 *
 * @return list<string>
 */
function offeredCreateFields(): array
{
    $source = file_get_contents(
        (new ReflectionClass(CreateEnvironment::class))->getFileName(),
    );

    preg_match_all(
        "/(?:TextInput|Select|Toggle|Textarea|DatePicker|Slider|KeyValue)::make\('([^']+)'\)/",
        $source,
        $matches,
    );

    return $matches[1];
}

test('the create form offers no resource sizing', function () {
    $fields = offeredCreateFields();

    foreach (['cpu_limit', 'memory_limit_mb', 'disk_limit_gb', 'cpu', 'memory', 'ram'] as $forbidden) {
        expect($fields)->not->toContain($forbidden);
    }
});

test('the create form offers only the container name and the image', function () {
    // The whole surface, stated. If the form grows a field that is neither of
    // these, this fails and the addition has to be argued for.
    expect(offeredCreateFields())->toEqual(['container_name', 'image']);
});

test('an environment is created with the configured limits, not with none', function () {
    // The limits have to be written before the record is saved. A record that
    // reaches the lifecycle service with no cpu_limit is not sized by the portal
    // at all; it is sized by whatever Incus decides when the field is absent.
    config()->set('infrastructure.default_cpu_limit', 3);
    config()->set('infrastructure.default_memory_limit_mb', 3072);
    config()->set('infrastructure.default_disk_limit_gb', 12);

    $user = User::factory()->create();

    $data = (new class extends CreateEnvironment {
        public function limitsFor(array $data): array
        {
            return $this->mutateFormDataBeforeCreate($data);
        }
    })->limitsFor(['container_name' => 'lab-limit-check', 'image' => 'images:ubuntu/24.04']);

    expect($data['cpu_limit'])->toBe(3)
        ->and($data['memory_limit_mb'])->toBe(3072)
        ->and($data['disk_limit_gb'])->toBe(12);
});

test('the limits do not change when the configured values change mid-session', function () {
    // Two environments created at different times get the limits in force when they
    // were created. This asserts the value is read once per creation rather than
    // being stored as a reference and re-read later, which would silently resize
    // environments that already exist.
    config()->set('infrastructure.default_memory_limit_mb', 1024);

    $page = new class extends CreateEnvironment {
        public function limitsFor(array $data): array
        {
            return $this->mutateFormDataBeforeCreate($data);
        }
    };

    $first = $page->limitsFor(['container_name' => 'lab-limits-a', 'image' => 'images:ubuntu/24.04']);

    config()->set('infrastructure.default_memory_limit_mb', 8192);

    $second = $page->limitsFor(['container_name' => 'lab-limits-b', 'image' => 'images:ubuntu/24.04']);

    expect($first['memory_limit_mb'])->toBe(1024)
        ->and($second['memory_limit_mb'])->toBe(8192);
});

test('the limit columns still exist on the record', function () {
    // The form no longer fills them, so the columns are written only by
    // configuration. If a column were dropped the create path would fail on an
    // unknown column rather than quietly storing nothing, and this says so.
    foreach (['cpu_limit', 'memory_limit_mb', 'disk_limit_gb'] as $column) {
        expect(LabEnvironment::query()->getConnection()
            ->getSchemaBuilder()
            ->hasColumn(LabEnvironment::query()->getModel()->getTable(), $column),
        )->toBeTrue();
    }
});