<?php

namespace Tests\Feature\GuidedCtf;

use App\Enums\LabSessionStatus;
use App\Infrastructure\Health\CommandRunner;
use App\Jobs\DeleteLabSession;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Services\GuidedCtf\ContainerAbsenceVerifier;
use App\Services\GuidedCtf\LabFirewall;
use App\Services\GuidedCtf\PairedLabTeardownService;
use App\Services\OpenTofuService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class DeleteLabSessionE2ETest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('infrastructure.environment_connection', 'sqlite');
        config()->set('cache.default', 'array');

        Schema::connection('sqlite')->dropIfExists('lab_sessions');
        Schema::connection('sqlite')->dropIfExists('lab_environments');

        Schema::connection('sqlite')->create('lab_environments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('container_name');
            $table->string('incus_remote');
            $table->string('tofu_workspace')->nullable();
            $table->string('internal_ip')->nullable();
            $table->string('status')->nullable();
            $table->string('runtime_status')->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('lab_sessions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('victim_environment_id')->nullable();
            $table->unsignedBigInteger('attacker_environment_id')->nullable();
            $table->string('victim_remote');
            $table->string('victim_container');
            $table->string('victim_ip')->nullable();
            $table->string('attacker_remote');
            $table->string('attacker_container');
            $table->string('attacker_ip')->nullable();
            $table->string('status');
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    private function createPair(LabSessionStatus $status): LabSession
    {
        $victim = LabEnvironment::query()->create([
            'user_id' => 77,
            'container_name' => 'victim-user-77',
            'incus_remote' => 'lab-env',
            'tofu_workspace' => 'victim-user-77',
            'internal_ip' => '10.10.10.150',
        ]);

        $attacker = LabEnvironment::query()->create([
            'user_id' => 77,
            'container_name' => 'attacker-user-77',
            'incus_remote' => 'lab-atk',
            'tofu_workspace' => 'attacker-user-77',
            'internal_ip' => '10.10.20.150',
        ]);

        return LabSession::query()->create([
            'user_id' => 77,
            'course_id' => 1,
            'victim_environment_id' => $victim->id,
            'attacker_environment_id' => $attacker->id,
            'victim_remote' => 'lab-env',
            'victim_container' => 'victim-user-77',
            'victim_ip' => '10.10.10.150',
            'attacker_remote' => 'lab-atk',
            'attacker_container' => 'attacker-user-77',
            'attacker_ip' => '10.10.20.150',
            'status' => $status,
        ]);
    }

    private function teardownService(
        bool $fail = false,
        bool $liveContainer = false
    ): PairedLabTeardownService {
        $tofu = Mockery::mock(OpenTofuService::class);
        $tofu->shouldReceive('workspaceFor')
            ->andReturnUsing(
                fn (LabEnvironment $env) => (string) $env->tofu_workspace
            );

        if ($fail) {
            $tofu->shouldReceive('workspaceExists')
                ->once()
                ->andThrow(new \RuntimeException('Simulated infrastructure failure'));
        } else {
            $tofu->shouldReceive('workspaceExists')
                ->andReturn(false);
        }

        $runner = Mockery::mock(CommandRunner::class);
        $runner->shouldReceive('run')->andReturn([
            'successful' => true,
            'stdout' => $liveContainer
                ? '[{"name":"victim-user-77"}]'
                : '[]',
            'stderr' => '',
        ]);

        $firewall = Mockery::mock(LabFirewall::class);
        $firewall->shouldReceive('revoke')->andReturnNull();

        return new PairedLabTeardownService(
            $tofu,
            new ContainerAbsenceVerifier($runner),
            $firewall
        );
    }

    public function test_successful_delete_releases_quota_and_preserves_history(): void
    {
        $session = $this->createPair(LabSessionStatus::Deleting);
        $id = $session->id;

        (new DeleteLabSession($id))->handle($this->teardownService());

        $deleted = LabSession::query()->findOrFail($id);

        $this->assertSame(LabSessionStatus::Deleted, $deleted->status);
        $this->assertNull($deleted->victim_environment_id);
        $this->assertNull($deleted->attacker_environment_id);
        $this->assertSame(0, LabEnvironment::query()->count());
        $this->assertSame(1, LabSession::query()->count());
        $this->assertSame(
            0,
            LabSession::query()
                ->where('user_id', 77)
                ->where('status', '!=', LabSessionStatus::Deleted->value)
                ->count()
        );

        // A second queued attempt must be harmless.
        (new DeleteLabSession($id))->handle($this->teardownService());
        $this->assertSame(1, LabSession::query()->count());
    }

    public function test_failed_infrastructure_keeps_quota_and_environment_records(): void
    {
        $session = $this->createPair(LabSessionStatus::Deleting);

        (new DeleteLabSession($session->id))->handle(
            $this->teardownService(fail: true)
        );

        $session->refresh();

        $this->assertSame(LabSessionStatus::Deleting, $session->status);
        $this->assertNotNull($session->last_error);
        $this->assertSame(2, LabEnvironment::query()->count());
        $this->assertNotNull($session->victim_environment_id);
        $this->assertNotNull($session->attacker_environment_id);
    }

    public function test_live_container_with_missing_workspace_blocks_quota_release(): void
    {
        $session = $this->createPair(LabSessionStatus::Deleting);

        (new DeleteLabSession($session->id))->handle(
            $this->teardownService(liveContainer: true)
        );

        $this->assertSame(
            LabSessionStatus::Deleting,
            $session->refresh()->status
        );
        $this->assertSame(2, LabEnvironment::query()->count());
    }

    public function test_job_refuses_to_delete_session_not_in_deleting_state(): void
    {
        $session = $this->createPair(LabSessionStatus::Ready);

        // READY must exit without invoking any infrastructure operation.
        $tofu = Mockery::mock(OpenTofuService::class);
        $tofu->shouldNotReceive('workspaceFor');
        $tofu->shouldNotReceive('workspaceExists');
        $tofu->shouldNotReceive('destroyWorkspaceResourcesIfPresent');
        $tofu->shouldNotReceive('cleanupWorkspaceIfPresent');

        $runner = Mockery::mock(CommandRunner::class);
        $runner->shouldNotReceive('run');

        $firewall = Mockery::mock(LabFirewall::class);
        $firewall->shouldNotReceive('revoke');

        (new DeleteLabSession($session->id))->handle(
            new PairedLabTeardownService(
                $tofu,
                new ContainerAbsenceVerifier($runner),
                $firewall
            )
        );

        $this->assertSame(LabSessionStatus::Ready, $session->refresh()->status);
        $this->assertSame(2, LabEnvironment::query()->count());
    }
}
