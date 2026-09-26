<?php

use App\Contracts\LabProvisioner;
use App\Data\ProvisionedLab;
use App\Enums\LabActualState;
use App\Enums\LabOperationState;
use App\Enums\UserRole;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Labs\LabResource;
use App\Models\ActivityLog;
use App\Models\Lab;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\LabDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the activity log table renders its header cells', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    ActivityLog::create([
        'actor_id' => $admin->id,
        'action' => 'PROVISION_LAB_RESULT',
        'status' => 'failed',
        'description' => 'The container was created and verified.',
        'resource_type' => Lab::class,
        'resource_id' => '1',
    ]);

    $response = $this->actingAs($admin)->get(ActivityLogResource::getUrl('index', panel: 'admin'));

    $response->assertOk()
        // A column hidden by default renders no header, which is exactly how
        // the list looked empty while still reporting its row count.
        ->assertSee('When', escape: false)
        ->assertSee('Action', escape: false)
        ->assertSee('Actor', escape: false)
        ->assertSee('Result', escape: false)
        ->assertSee('PROVISION_LAB_RESULT')
        ->assertSee('The container was created and verified.')
        ->assertSee('failed');
});

test('the activity log table is not rendered with every column hidden', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    ActivityLog::create([
        'actor_id' => $admin->id,
        'action' => 'PROVISION_LAB',
        'status' => 'queued',
        'description' => 'Provisioning requested.',
    ]);

    $html = $this->actingAs($admin)->get(ActivityLogResource::getUrl('index', panel: 'admin'))->getContent();

    $headers = substr_count($html, 'fi-ta-header-cell');

    expect($headers)->toBeGreaterThan(2);
});

test('an activity log detail page shows who, what and the request context', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $log = ActivityLog::create([
        'actor_id' => $admin->id,
        'action' => 'DELETE_LAB_RESULT',
        'status' => 'completed',
        'description' => 'The container was removed from the compute plane.',
        'ip_address' => '10.20.0.5',
        'metadata' => ['operation_id' => 'abc-123'],
    ]);

    $this->actingAs($admin)
        ->get(ActivityLogResource::getUrl('view', ['record' => $log], 'admin'))
        ->assertOk()
        ->assertSee('DELETE_LAB_RESULT')
        ->assertSee('The container was removed from the compute plane.')
        ->assertSee('10.20.0.5')
        ->assertSee('abc-123');
});

test('an administrator can delete a lab record whose container is already absent', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $lab = Lab::factory()->create(['actual_state' => LabActualState::Absent]);

    $this->actingAs($admin)
        ->get(LabResource::getUrl('view', ['record' => $lab], 'admin'))
        ->assertOk()
        ->assertSee('Delete Lab');
});

test('an administrator may delete a lab whose container is still live', function () {
    // Deletion goes through the compute plane, so a live container is exactly
    // the case the action exists for: it removes the container, then archives
    // the record. Blocking it here would leave operators with no way to clear a
    // lab whose container refuses to go away.
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $lab = Lab::factory()->create(['actual_state' => LabActualState::Running]);

    $this->actingAs($admin)
        ->get(LabResource::getUrl('view', ['record' => $lab], 'admin'))
        ->assertOk()
        ->assertSee('Delete Lab');
});

test('a student is never offered a lab delete', function () {
    $student = User::factory()->create();
    $lab = Lab::factory()->for($student, 'owner')->create([
        'actual_state' => LabActualState::Absent,
    ]);

    $this->actingAs($student)
        ->get(LabResource::getUrl('view', ['record' => $lab], 'admin'))
        ->assertOk()
        ->assertDontSee('Delete Lab');
});

test('deleting a lab removes the container before the record is archived', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = User::factory()->create();
    $lab = Lab::factory()->for($student, 'owner')->create([
        'actual_state' => LabActualState::Running,
    ]);

    $destroyed = false;
    $destroyedAfter = null;

    $provisioner = new class($destroyed, $destroyedAfter) implements LabProvisioner
    {
        public function __construct(
            public bool &$destroyed,
            public ?bool &$destroyedAfter,
        ) {}

        public function provision(Lab $lab): ProvisionedLab
        {
            return new ProvisionedLab(successful: true);
        }

        public function start(Lab $lab): ProvisionedLab
        {
            return new ProvisionedLab(successful: true);
        }

        public function stop(Lab $lab): ProvisionedLab
        {
            return new ProvisionedLab(successful: true);
        }

        public function reset(Lab $lab): ProvisionedLab
        {
            return new ProvisionedLab(successful: true);
        }

        public function destroy(Lab $lab): ProvisionedLab
        {
            $this->destroyed = true;
            // The record must still exist at this point, which is the whole
            // point of removing the container first.
            $this->destroyedAfter = Lab::withTrashed()->whereKey($lab->id)->exists();

            return new ProvisionedLab(
                successful: true,
                containerName: $lab->container_name,
                status: 'deleted',
            );
        }

        public function observe(Lab $lab): ProvisionedLab
        {
            return new ProvisionedLab(successful: true);
        }
    };

    $deletion = new LabDeletionService(
        $provisioner,
        app(ActivityLogService::class),
    );

    $outcome = $deletion->delete($admin, $lab);

    expect($outcome['deleted'])->toBeTrue()
        ->and($destroyed)->toBeTrue()
        // Container was removed while the record still existed.
        ->and($destroyedAfter)->toBeTrue()
        // Now the record is archived, not hard deleted.
        ->and(Lab::withTrashed()->whereKey($lab->id)->first()->trashed())->toBeTrue();
});

test('a lab is kept when the compute plane refuses to remove the container', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $lab = Lab::factory()->create(['actual_state' => LabActualState::Running]);

    $provisioner = new class implements LabProvisioner
    {
        public function provision(Lab $lab): ProvisionedLab
        {
            return new ProvisionedLab(successful: true);
        }

        public function start(Lab $lab): ProvisionedLab
        {
            return new ProvisionedLab(successful: true);
        }

        public function stop(Lab $lab): ProvisionedLab
        {
            return new ProvisionedLab(successful: true);
        }

        public function reset(Lab $lab): ProvisionedLab
        {
            return new ProvisionedLab(successful: true);
        }

        public function destroy(Lab $lab): ProvisionedLab
        {
            return new ProvisionedLab(
                successful: false,
                errorCode: 'delete_failed',
                message: 'The container is still in use.',
            );
        }

        public function observe(Lab $lab): ProvisionedLab
        {
            return new ProvisionedLab(successful: true);
        }
    };

    $deletion = new LabDeletionService(
        $provisioner,
        app(ActivityLogService::class),
    );

    $outcome = $deletion->delete($admin, $lab);

    expect($outcome['deleted'])->toBeFalse()
        // The record survives so the operator can retry.
        ->and(Lab::whereKey($lab->id)->exists())->toBeTrue()
        ->and($lab->refresh()->operation_state)->toBe(LabOperationState::Failed);
});

test('the activity log infolist never renders an updated timestamp', function () {
    // ActivityLog sets UPDATED_AT to null, so a column for it would always be
    // empty. Guard against it being reintroduced.
    $source = file_get_contents(app_path('Filament/Resources/ActivityLogs/Schemas/ActivityLogInfolist.php'));

    expect($source)->not->toContain("TextEntry::make('updated_at')");
});
