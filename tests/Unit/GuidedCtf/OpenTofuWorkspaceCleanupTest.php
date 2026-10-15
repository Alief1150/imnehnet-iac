<?php

namespace Tests\Unit\GuidedCtf;

use App\Data\ProcessResult;
use App\Models\LabEnvironment;
use App\Services\OpenTofuService;
use App\Support\EnvironmentWorkspace;
use RuntimeException;
use Tests\TestCase;

class OpenTofuWorkspaceCleanupTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir()
            . '/imnehnet-tofu-test-' . bin2hex(random_bytes(8));

        mkdir($this->temporaryDirectory, 0700, true);
    }

    protected function tearDown(): void
    {
        if (isset($this->temporaryDirectory)) {
            foreach (glob($this->temporaryDirectory . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            if (is_dir($this->temporaryDirectory)) {
                rmdir($this->temporaryDirectory);
            }
        }

        parent::tearDown();
    }

    private function environment(): LabEnvironment
    {
        $container = 'ctf-victim-150';

        return new LabEnvironment([
            'container_name' => $container,
            'tofu_workspace' => EnvironmentWorkspace::forContainer($container),
        ]);
    }

    private function fake(array $responses): FakeCleanupOpenTofu
    {
        return new FakeCleanupOpenTofu(
            $this->temporaryDirectory,
            $responses,
        );
    }

    private function makeProcessResult(int $exitCode, string $stdout = ''): ProcessResult
    {
        return new ProcessResult(
            command: ['mock-tofu'],
            exitCode: $exitCode,
            stdout: $stdout,
            stderr: $exitCode === 0 ? '' : 'Simulated failure',
            durationMs: 1,
        );
    }

    public function test_missing_workspace_requires_no_delete(): void
    {
        $environment = $this->environment();

        $tofu = $this->fake([
            $this->makeProcessResult(0, "default\n"),
        ]);

        $tofu->cleanupWorkspaceIfPresent($environment);

        $this->assertSame([
            ['workspace', 'list'],
        ], $tofu->commands);
    }

    public function test_existing_workspace_is_deleted_and_verified(): void
    {
        $environment = $this->environment();
        $workspace = $environment->tofu_workspace;

        $tofu = $this->fake([
            $this->makeProcessResult(0, "default\n" . $workspace . "\n"),
            $this->makeProcessResult(0),
            $this->makeProcessResult(0),
            $this->makeProcessResult(0, "default\n"),
        ]);

        $tofu->cleanupWorkspaceIfPresent($environment);

        $this->assertCount(4, $tofu->commands);
        $this->assertSame(['workspace', 'list'], $tofu->commands[0]);
        $this->assertSame(['workspace', 'delete', $workspace], $tofu->commands[2]);
        $this->assertSame(['workspace', 'list'], $tofu->commands[3]);
    }

    public function test_inventory_failure_prevents_deletion(): void
    {
        $tofu = $this->fake([
            $this->makeProcessResult(1),
        ]);

        try {
            $tofu->cleanupWorkspaceIfPresent($this->environment());
            $this->fail('Expected inventory failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'Cannot inspect OpenTofu workspaces',
                $exception->getMessage()
            );
        }

        $this->assertSame([
            ['workspace', 'list'],
        ], $tofu->commands);
    }

    public function test_workspace_still_present_after_delete_is_failure(): void
    {
        $environment = $this->environment();
        $workspace = $environment->tofu_workspace;

        $tofu = $this->fake([
            $this->makeProcessResult(0, "default\n" . $workspace . "\n"),
            $this->makeProcessResult(0),
            $this->makeProcessResult(0),
            $this->makeProcessResult(0, "default\n" . $workspace . "\n"),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenTofu workspace remains after cleanup.');

        $tofu->cleanupWorkspaceIfPresent($environment);
    }

    public function test_delete_command_failure_is_not_ignored(): void
    {
        $environment = $this->environment();
        $workspace = $environment->tofu_workspace;

        $tofu = $this->fake([
            $this->makeProcessResult(0, "default\n" . $workspace . "\n"),
            $this->makeProcessResult(0),
            $this->makeProcessResult(1),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenTofu workspace cleanup failed.');

        $tofu->cleanupWorkspaceIfPresent($environment);
    }

    public function test_resource_destroy_uses_existing_workspace_without_deleting_it(): void
    {
        $environment = $this->environment();
        $workspace = $environment->tofu_workspace;

        $tofu = $this->fake([
            $this->makeProcessResult(0, "default\n" . $workspace . "\n"),
            $this->makeProcessResult(0),
            $this->makeProcessResult(0),
        ]);

        $tofu->destroyWorkspaceResourcesIfPresent($environment);

        $this->assertSame([
            ['workspace', 'list'],
            ['workspace', 'select', $workspace],
            ['destroy', '-auto-approve', '-input=false', '-no-color'],
        ], $tofu->commands);
    }

    public function test_missing_workspace_never_creates_a_new_workspace(): void
    {
        $tofu = $this->fake([
            $this->makeProcessResult(0, "default\n"),
        ]);

        try {
            $tofu->destroyWorkspaceResourcesIfPresent($this->environment());
            $this->fail('Expected missing workspace failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'workspace is missing',
                $exception->getMessage()
            );
        }

        $this->assertSame([
            ['workspace', 'list'],
        ], $tofu->commands);
    }

    public function test_resource_destroy_failure_stops_without_workspace_cleanup(): void
    {
        $environment = $this->environment();
        $workspace = $environment->tofu_workspace;

        $tofu = $this->fake([
            $this->makeProcessResult(0, "default\n" . $workspace . "\n"),
            $this->makeProcessResult(0),
            $this->makeProcessResult(1),
        ]);

        try {
            $tofu->destroyWorkspaceResourcesIfPresent($environment);
            $this->fail('Expected destroy failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'resource destroy failed',
                $exception->getMessage()
            );
        }

        $this->assertCount(3, $tofu->commands);
        $this->assertSame(
            ['destroy', '-auto-approve', '-input=false', '-no-color'],
            $tofu->commands[2]
        );
    }

    public function test_resource_destroy_inventory_failure_runs_no_destroy(): void
    {
        $tofu = $this->fake([
            $this->makeProcessResult(1),
        ]);

        try {
            $tofu->destroyWorkspaceResourcesIfPresent($this->environment());
            $this->fail('Expected inventory failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'Cannot inspect OpenTofu workspaces',
                $exception->getMessage()
            );
        }

        $this->assertSame([
            ['workspace', 'list'],
        ], $tofu->commands);
    }

}

/**
 * Executes the real workspace cleanup and lock code, but never
 * starts an OpenTofu process.
 */
class FakeCleanupOpenTofu extends OpenTofuService
{
    public array $commands = [];

    private array $responses;

    public function __construct(string $directory, array $responses)
    {
        parent::__construct(
            directory: $directory,
            binary: '/nonexistent/mock-tofu',
            lockFile: $directory . '/tofu.lock',
            timeoutSeconds: 2,
            lockWaitSeconds: 2,
        );

        $this->responses = $responses;
    }

    public function run(array $arguments): ProcessResult
    {
        $this->commands[] = $arguments;

        if ($this->responses === []) {
            throw new RuntimeException(
                'Unexpected OpenTofu command: ' . implode(' ', $arguments)
            );
        }

        return array_shift($this->responses);
    }
}
