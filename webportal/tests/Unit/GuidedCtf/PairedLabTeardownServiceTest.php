<?php

namespace Tests\Unit\GuidedCtf;

use App\Infrastructure\Health\CommandRunner;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Services\GuidedCtf\ContainerAbsenceVerifier;
use App\Services\GuidedCtf\LabFirewall;
use App\Services\GuidedCtf\PairedLabTeardownService;
use App\Services\OpenTofuService;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PairedLabTeardownServiceTest extends TestCase
{
    private function makeLabSession(): LabSession
    {
        $session = new LabSession([
            'user_id' => 77,
            'victim_remote' => 'lab-env',
            'victim_container' => 'victim-user-77',
            'attacker_remote' => 'lab-atk',
            'attacker_container' => 'attacker-user-77',
        ]);

        $session->setRelation('victimEnvironment', new LabEnvironment([
            'user_id' => 77,
            'incus_remote' => 'lab-env',
            'container_name' => 'victim-user-77',
            'tofu_workspace' => 'victim-user-77',
        ]));

        $session->setRelation('attackerEnvironment', new LabEnvironment([
            'user_id' => 77,
            'incus_remote' => 'lab-atk',
            'container_name' => 'attacker-user-77',
            'tofu_workspace' => 'attacker-user-77',
        ]));

        return $session;
    }

    private function tofu(): OpenTofuService
    {
        $tofu = Mockery::mock(OpenTofuService::class);

        $tofu->shouldReceive('workspaceFor')
            ->andReturnUsing(
                fn (LabEnvironment $environment) => $environment->tofu_workspace
            );

        return $tofu;
    }

    private function absence(array $inventories): ContainerAbsenceVerifier
    {
        $commands = Mockery::mock(CommandRunner::class);

        $commands->shouldReceive('run')
            ->times(count($inventories))
            ->andReturn(...array_map(
                fn (string $json) => [
                    'successful' => true,
                    'stdout' => $json,
                    'stderr' => '',
                ],
                $inventories
            ));

        return new ContainerAbsenceVerifier($commands);
    }

    private function firewall(LabSession $session, bool $shouldRevoke = true): LabFirewall
    {
        $firewall = Mockery::mock(LabFirewall::class);
        if ($shouldRevoke) {
            $firewall->shouldReceive('revoke')->once()->with($session);
        } else {
            $firewall->shouldNotReceive('revoke');
        }

        return $firewall;
    }

    public function test_missing_workspaces_and_absent_containers_are_idempotent(): void
    {
        $session = $this->makeLabSession();
        $tofu = $this->tofu();

        $tofu->shouldReceive('workspaceExists')->twice()->andReturn(false);
        $tofu->shouldNotReceive('destroyWorkspaceResourcesIfPresent');
        $tofu->shouldNotReceive('cleanupWorkspaceIfPresent');

        (new PairedLabTeardownService(
            $tofu,
            $this->absence(['[]', '[]']),
            $this->firewall($session)
        ))->teardown($session);

        $this->assertTrue(true);
    }

    public function test_both_workspaces_are_destroyed_verified_and_cleaned(): void
    {
        $session = $this->makeLabSession();
        $tofu = $this->tofu();

        $tofu->shouldReceive('workspaceExists')->twice()->andReturn(true);
        $tofu->shouldReceive('destroyWorkspaceResourcesIfPresent')->twice();
        $tofu->shouldReceive('cleanupWorkspaceIfPresent')->twice();

        (new PairedLabTeardownService(
            $tofu,
            $this->absence(['[]', '[]']),
            $this->firewall($session)
        ))->teardown($session);

        $this->assertTrue(true);
    }

    public function test_destroy_failure_prevents_cleanup_and_second_destroy(): void
    {
        $session = $this->makeLabSession();
        $tofu = $this->tofu();

        $tofu->shouldReceive('workspaceExists')->once()->andReturn(true);
        $tofu->shouldReceive('destroyWorkspaceResourcesIfPresent')
            ->once()
            ->andThrow(new RuntimeException('Simulated destroy failure'));
        $tofu->shouldNotReceive('cleanupWorkspaceIfPresent');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Simulated destroy failure');

        (new PairedLabTeardownService(
            $tofu,
            $this->absence([]),
            $this->firewall($session)
        ))->teardown($session);
    }

    public function test_container_still_present_prevents_workspace_cleanup(): void
    {
        $session = $this->makeLabSession();
        $tofu = $this->tofu();

        $tofu->shouldReceive('workspaceExists')->once()->andReturn(true);
        $tofu->shouldReceive('destroyWorkspaceResourcesIfPresent')->once();
        $tofu->shouldNotReceive('cleanupWorkspaceIfPresent');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Container remains present');

        (new PairedLabTeardownService(
            $tofu,
            $this->absence(['[{"name":"victim-user-77"}]']),
            $this->firewall($session)
        ))->teardown($session);
    }

    public function test_missing_workspace_with_live_container_fails_closed(): void
    {
        $session = $this->makeLabSession();
        $tofu = $this->tofu();

        $tofu->shouldReceive('workspaceExists')->once()->andReturn(false);
        $tofu->shouldNotReceive('destroyWorkspaceResourcesIfPresent');
        $tofu->shouldNotReceive('cleanupWorkspaceIfPresent');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('workspace is missing');

        (new PairedLabTeardownService(
            $tofu,
            $this->absence(['[{"name":"victim-user-77"}]']),
            $this->firewall($session)
        ))->teardown($session);
    }

    public function test_identity_mismatch_prevents_infrastructure_changes(): void
    {
        $session = $this->makeLabSession();
        $session->victim_container = 'different-container';

        $tofu = $this->tofu();
        $tofu->shouldNotReceive('workspaceExists');
        $tofu->shouldNotReceive('destroyWorkspaceResourcesIfPresent');
        $tofu->shouldNotReceive('cleanupWorkspaceIfPresent');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('identity mismatch');

        (new PairedLabTeardownService(
            $tofu,
            $this->absence([]),
            $this->firewall($session, false)
        ))->teardown($session);
    }

    public function test_owner_mismatch_prevents_infrastructure_changes(): void
    {
        $session = $this->makeLabSession();
        $session->victimEnvironment->user_id = 999;

        $tofu = $this->tofu();
        $tofu->shouldNotReceive('workspaceExists');
        $tofu->shouldNotReceive('destroyWorkspaceResourcesIfPresent');
        $tofu->shouldNotReceive('cleanupWorkspaceIfPresent');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('identity mismatch');

        (new PairedLabTeardownService(
            $tofu,
            $this->absence([]),
            $this->firewall($session, false)
        ))->teardown($session);
    }
}
