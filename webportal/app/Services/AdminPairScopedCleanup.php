<?php

namespace App\Services;

use App\Models\LabEnvironment;
use RuntimeException;
use Symfony\Component\Process\Process;

final class AdminPairScopedCleanup
{
    public function __construct(
        private readonly OpenTofuService $tofu,
    ) {}

    private function exists(string $target): bool
    {
        $p = new Process(['incus', 'info', $target]);
        $p->setTimeout(30);
        $p->run();

        if ($p->isSuccessful()) {
            return true;
        }

        $output = $p->getErrorOutput().$p->getOutput();

        if (str_contains($output, 'Instance not found')) {
            return false;
        }

        throw new RuntimeException(
            'Incus status inspection failed: '.$target
        );
    }

    public function remove(
        LabEnvironment $env,
        string $role,
        int $pairId
    ): void {
        if (!in_array($role, ['attacker', 'victim'], true)) {
            throw new RuntimeException('Invalid Admin Pair role.');
        }

        $remote = $role === 'attacker' ? 'lab-atk' : 'lab-env';
        $workspace = "admin-{$role}-p{$pairId}";
        $name = (string) $env->container_name;
        $target = "{$remote}:{$name}";

        if (
            $env->isProtected()
            || $name !== "{$role}-user-{$env->user_id}-p{$pairId}"
            || $env->tofu_workspace !== $workspace
            || rtrim((string) $env->incus_remote, ':') !== $remote
            || ($env->incus_project ?: 'default') !== 'default'
            || !$env->internal_ip
        ) {
            throw new RuntimeException(
                'Admin Pair resource identity mismatch.'
            );
        }

        $this->tofu->withDirectoryLock(function () use (
            $env, $workspace, $target, $remote, $role, $name
        ): void {
            $inventory = $this->tofu->listWorkspaces();

            if (!$inventory->successful()) {
                throw new RuntimeException('Workspace inventory unavailable.');
            }

            $names = array_map(
                static fn ($line) => trim(ltrim(trim($line), '* ')),
                preg_split('/\R/', $inventory->stdout)
            );

            if (!in_array($workspace, $names, true)) {
                if ($this->exists($target)) {
                    throw new RuntimeException(
                        'Live container has no expected workspace.'
                    );
                }

                return;
            }

            $selected = $this->tofu->run([
                'workspace', 'select', $workspace
            ]);
            $shown = $this->tofu->run(['workspace', 'show']);

            if (
                !$selected->successful()
                || !$shown->successful()
                || trim($shown->stdout) !== $workspace
            ) {
                throw new RuntimeException('Workspace selection mismatch.');
            }

            $pulled = $this->tofu->run(['state', 'pull']);

            if (!$pulled->successful()) {
                throw new RuntimeException('State read failed.');
            }

            $state = json_decode(
                $pulled->stdout,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (
                !is_array($state)
                || !isset($state['version'])
                || !is_array($state['resources'] ?? null)
            ) {
                throw new RuntimeException('Invalid OpenTofu state.');
            }

            $resources = $state['resources'];

            if ($resources === []) {
                if ($this->exists($target)) {
                    throw new RuntimeException(
                        'Live container with empty state; manual review required.'
                    );
                }
            } else {
                if (
                    count($resources) !== 1
                    || ($resources[0]['mode'] ?? '') !== 'managed'
                    || ($resources[0]['type'] ?? '') !== 'incus_instance'
                    || ($resources[0]['name'] ?? '') !== 'student_lab'
                    || count($resources[0]['instances'] ?? []) !== 1
                ) {
                    throw new RuntimeException(
                        'Unexpected managed resources in workspace.'
                    );
                }

                $attrs = $resources[0]['instances'][0]['attributes'] ?? [];

                if (
                    ($attrs['name'] ?? '') !== $name
                    || rtrim((string) ($attrs['remote'] ?? ''), ':') !== $remote
                    || ($attrs['project'] ?? 'default') !== 'default'
                ) {
                    throw new RuntimeException(
                        'OpenTofu state ownership mismatch.'
                    );
                }

                if (!$this->exists($target)) {
                    throw new RuntimeException(
                        'State tracks missing container; reconcile before deleting.'
                    );
                }

                $dir = storage_path(
                    'app/private/admin-pair-delete/'
                    .$workspace.'-'.date('Ymd-His')
                );

                if (!is_dir($dir)
                    && !mkdir($dir, 0700, true)
                    && !is_dir($dir)) {
                    throw new RuntimeException('Cannot create backup directory.');
                }

                chmod($dir, 0700);

                if (file_put_contents(
                    $dir.'/state.json',
                    $pulled->stdout,
                    LOCK_EX
                ) === false) {
                    throw new RuntimeException('State backup failed.');
                }

                chmod($dir.'/state.json', 0600);

                $variables = [
                    'container_name' => $name,
                    'container_image' => (string) $env->image,
                    'incus_remote' => $remote,
                    'static_ipv4' => (string) $env->internal_ip,
                    'incus_network' => (string) config(
                        'infrastructure.incus_network', 'incusbr0'
                    ),
                    'cpu_limit' => (int) $env->cpu_limit,
                    'memory_limit' => (int) $env->memory_limit_mb.'MiB',
                    'desired_running' => 'true',
                    'security_acls' => $role === 'attacker'
                        ? (string) config(
                            'infrastructure.attacker_acl', 'ctf-attacker'
                        )
                        : '',
                ];

                $args = [
                    'plan', '-destroy', '-refresh=false',
                    '-input=false', '-no-color',
                    '-out='.$dir.'/delete.tfplan',
                ];

                foreach ($variables as $key => $value) {
                    $args[] = '-var='.$key.'='.$value;
                }

                $plan = $this->tofu->run($args);

                if (!$plan->successful()) {
                    throw new RuntimeException(
                        'Scoped destroy plan failed: '
                        .substr($plan->stderr, 0, 350)
                    );
                }

                $shownPlan = $this->tofu->run([
                    'show', '-json', $dir.'/delete.tfplan'
                ]);

                if (!$shownPlan->successful()) {
                    throw new RuntimeException('Cannot inspect destroy plan.');
                }

                $decoded = json_decode(
                    $shownPlan->stdout,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
                $changes = $decoded['resource_changes'] ?? [];

                if (
                    count($changes) !== 1
                    || ($changes[0]['address'] ?? '') !== 'incus_instance.student_lab'
                    || ($changes[0]['change']['actions'] ?? []) !== ['delete']
                    || ($changes[0]['change']['before']['name'] ?? '') !== $name
                    || rtrim(
                        (string) ($changes[0]['change']['before']['remote'] ?? ''),
                        ':'
                    ) !== $remote
                    || ($changes[0]['change']['before']['project'] ?? 'default')
                        !== 'default'
                ) {
                    throw new RuntimeException(
                        'Destroy plan is not limited to the intended Admin container.'
                    );
                }

                $applied = $this->tofu->run([
                    'apply', '-input=false', '-no-color',
                    $dir.'/delete.tfplan'
                ]);

                if (!$applied->successful()) {
                    throw new RuntimeException(
                        'Scoped destroy failed: '
                        .substr($applied->stderr, 0, 350)
                    );
                }

                if ($this->exists($target)) {
                    throw new RuntimeException(
                        'Container still exists after destroy.'
                    );
                }

                $afterResult = $this->tofu->run(['state', 'pull']);

                if (!$afterResult->successful()) {
                    throw new RuntimeException('Post-destroy state unavailable.');
                }

                $after = json_decode(
                    $afterResult->stdout,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                if (($after['resources'] ?? null) !== []) {
                    throw new RuntimeException('Workspace still owns resources.');
                }
            }

            $deleted = $this->tofu->deleteWorkspace($workspace);

            if (!$deleted->successful()) {
                throw new RuntimeException(
                    'Cannot remove empty workspace.'
                );
            }

            $final = $this->tofu->listWorkspaces();

            if (!$final->successful()) {
                throw new RuntimeException(
                    'Cannot verify workspace removal.'
                );
            }

            $finalNames = array_map(
                static fn ($line) => trim(ltrim(trim($line), '* ')),
                preg_split('/\R/', $final->stdout)
            );

            if (in_array($workspace, $finalNames, true)) {
                throw new RuntimeException(
                    'Workspace remains after deletion.'
                );
            }
        });
    }
}
