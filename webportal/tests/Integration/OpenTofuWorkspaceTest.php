<?php

use App\Services\OpenTofuService;

/**
 * The delete flow runs against the real OpenTofu client, but never against the
 * real working directory: each test gets a throwaway one with no provider and no
 * resources, so nothing can be created or destroyed on the compute plane.
 *
 * This exists because of a specific failure. The flow selects an environment's
 * workspace in order to destroy through it, and then asks OpenTofu to delete that
 * same workspace. OpenTofu refuses to delete the active workspace, so the destroy
 * succeeded and the delete failed, and every deleted environment stayed in the
 * inventory with its container already gone.
 */

/**
 * @return array{0: OpenTofuService, 1: string} the service and its scratch directory
 */
function scratchTofu(): array
{
    $directory = sys_get_temp_dir().'/imnehnet-tofu-test-'.bin2hex(random_bytes(6));

    mkdir($directory, 0o775, true);
    mkdir($directory.'/lockdir', 0o775, true);

    // No provider and no resources: the test is about workspace selection, and a
    // resource here would be something a stray command could try to act on.
    file_put_contents($directory.'/main.tf', <<<'TF'
variable "marker" {
  type    = string
  default = "scratch"
}

output "marker" {
  value = var.marker
}
TF);

    $service = new OpenTofuService(
        directory: $directory,
        binary: 'tofu',
        lockFile: $directory.'/lockdir/tofu.lock',
        timeoutSeconds: 60,
        lockWaitSeconds: 5,
    );

    // The service refuses a directory it cannot use, which would make a test fail
    // for the wrong reason if the client were missing.
    $service->listWorkspaces();

    return [$service, $directory];
}

function cleanupScratch(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    // Recursive, because OpenTofu leaves terraform.tfstate.d/<workspace>/ behind
    // and a flat unlink fails on those directories. The first version did, and the
    // test failed on its own cleanup while the behaviour it was checking passed.
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }

    @rmdir($directory);
}

test('a workspace can be deleted after moving the selection away from it', function () {
    [$tofu, $directory] = scratchTofu();

    try {
        // Create the target the way the delete flow does: by selecting it.
        $tofu->selectOrCreateWorkspace('env-scratch-a');

        $before = $tofu->parseWorkspaceList($tofu->listWorkspaces()->stdout);
        expect($before)->toContain('env-scratch-a');

        $result = $tofu->deleteWorkspace('env-scratch-a');

        expect($result->successful())->toBeTrue();

        $after = $tofu->parseWorkspaceList($tofu->listWorkspaces()->stdout);
        expect($after)->not->toContain('env-scratch-a');

        // The selection has to land somewhere safe, and it must not be the
        // workspace that was just removed.
        $show = $tofu->run(['workspace', 'show']);
        expect($show->successful())->toBeTrue()
            ->and(trim($show->stdout))->not->toBe('env-scratch-a');
    } finally {
        cleanupScratch($directory);
    }
});

test('deleting a workspace that is not the active one still works', function () {
    [$tofu, $directory] = scratchTofu();

    try {
        $created = $tofu->selectOrCreateWorkspace('env-scratch-b');
        expect($created->successful())->toBeTrue();

        $created = $tofu->selectOrCreateWorkspace('env-scratch-c');
        expect($created->successful())->toBeTrue();

        $selected = $tofu->run(['workspace', 'select', 'default']);
        expect($selected->successful())->toBeTrue();

        expect($tofu->deleteWorkspace('env-scratch-b')->successful())->toBeTrue();

        $remaining = $tofu->parseWorkspaceList($tofu->listWorkspaces()->stdout);
        expect($remaining)->not->toContain('env-scratch-b')
            ->and($remaining)->toContain('env-scratch-c');
    } finally {
        cleanupScratch($directory);
    }
});

test('a protected workspace is never deleted, however it is reached', function () {
    config()->set('infrastructure.protected_workspaces', ['default']);

    [$tofu, $directory] = scratchTofu();

    try {
        // This is the guard that stops a delete from removing the workspace
        // holding the reference environment's state.
        expect(fn () => $tofu->deleteWorkspace('default'))
            ->toThrow(DomainException::class, 'reserved for the reference environment');

        expect($tofu->parseWorkspaceList($tofu->listWorkspaces()->stdout))->toContain('default');
    } finally {
        cleanupScratch($directory);
    }
});
