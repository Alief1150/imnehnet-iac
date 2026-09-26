<?php

namespace App\Infrastructure\Provision;

use App\Contracts\LabProvisioner;
use App\Data\ProvisionedLab;
use App\Models\Lab;
use App\Services\LabPortAllocationService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Talks to the Incus daemon that runs on the compute plane.
 *
 * The remote prefix belongs on the positional arguments of every subcommand:
 * `incus launch <remote>:<image> <remote>:<name>` and `incus config set
 * <remote>:<name> <key> <value>`. Passing the prefix on the flags instead makes
 * Incus reject the call, so the prefix is built per argument here rather than
 * once at the top.
 */
final class IncusLabProvisioner implements LabProvisioner
{
    public function __construct(
        private readonly LabPortAllocationService $ports,
        private readonly string $remote = 'lab-env:',
        private readonly string $network = 'incusbr0',
        private readonly int $timeoutSeconds = 120,
    ) {}

    public function provision(Lab $lab): ProvisionedLab
    {
        $name = $this->remoteName($lab);

        if ($this->exists($lab)) {
            return $this->observe($lab);
        }

        $result = $this->run([
            'incus', 'launch',
            // The image keeps its own remote alias (images:ubuntu/24.04); only
            // the instance name carries the compute plane remote.
            $lab->base_image,
            $name,
            '--network', $this->network,
        ]);

        if (! $result['successful']) {
            return $this->failed('launch_failed', 'Incus refused to create the container.', $result);
        }

        $failed = $this->applyBaseline($lab, $name);

        if ($failed !== null) {
            return $failed;
        }

        return $this->observe($lab);
    }

    public function start(Lab $lab): ProvisionedLab
    {
        $result = $this->run(['incus', 'start', $this->remoteName($lab)]);

        if (! $result['successful']) {
            return $this->failed('start_failed', 'Incus could not start the container.', $result);
        }

        return $this->observe($lab);
    }

    public function stop(Lab $lab): ProvisionedLab
    {
        $result = $this->run(['incus', 'stop', $this->remoteName($lab)]);

        if (! $result['successful']) {
            return $this->failed('stop_failed', 'Incus could not stop the container.', $result);
        }

        return $this->observe($lab);
    }

    public function reset(Lab $lab): ProvisionedLab
    {
        $deleted = $this->run(['incus', 'delete', $this->remoteName($lab), '--force']);

        if (! $deleted['successful'] && $this->exists($lab)) {
            return $this->failed('reset_failed', 'The existing container could not be removed.', $deleted);
        }

        $lab->forceFill([
            'ip_address' => null,
            'access_verified_at' => null,
            'last_verified_at' => null,
        ])->saveQuietly();

        return $this->provision($lab);
    }

    public function destroy(Lab $lab): ProvisionedLab
    {
        $name = (string) $lab->container_name;

        if (! $this->exists($lab)) {
            return new ProvisionedLab(
                successful: true,
                containerName: $name,
                status: 'absent',
                message: 'The container was already absent on the compute plane.',
            );
        }

        $result = $this->run(['incus', 'delete', $this->remoteName($lab), '--force']);

        if (! $result['successful']) {
            return $this->failed('delete_failed', 'The container could not be removed from the compute plane.', $result);
        }

        return new ProvisionedLab(
            successful: true,
            containerName: $name,
            status: 'deleted',
            message: 'The container was removed from the compute plane.',
        );
    }

    public function observe(Lab $lab): ProvisionedLab
    {
        $result = $this->run(['incus', 'list', $this->remote, '--format=json']);

        if (! $result['successful']) {
            return $this->failed('observe_failed', 'The compute plane could not be queried.', $result);
        }

        $instances = json_decode($result['stdout'], true);

        if (! is_array($instances)) {
            return $this->failed('observe_failed', 'The compute plane returned an unreadable response.', $result);
        }

        $name = (string) $lab->container_name;

        $instance = collect($instances)->first(
            fn (mixed $item): bool => is_array($item) && ($item['name'] ?? null) === $name,
        );

        if (! is_array($instance)) {
            return new ProvisionedLab(
                successful: true,
                containerName: $name,
                status: 'absent',
                message: 'The container is not present on the compute plane.',
            );
        }

        $ip = $this->address($instance);

        return new ProvisionedLab(
            successful: true,
            containerName: $name,
            ipAddress: $ip,
            sshPort: $lab->ssh_port === null ? null : (int) $lab->ssh_port,
            status: strtolower((string) ($instance['status'] ?? 'unknown')),
            accessUsername: $this->accessUsername($lab),
            detail: [
                'profiles' => $instance['profiles'] ?? [],
                'last_used_at' => $instance['last_used_at'] ?? null,
            ],
        );
    }

    private function applyBaseline(Lab $lab, string $name): ?ProvisionedLab
    {
        $name = $this->remoteName($lab);

        $config = [
            ['limits.cpu', (string) $lab->cpu_limit],
            ['limits.memory', $this->memory($lab)],
            ['boot.autostart', 'false'],
        ];

        foreach ($config as [$key, $value]) {
            $result = $this->run(['incus', 'config', 'set', $name, $key, $value]);

            if (! $result['successful']) {
                return $this->failed('limits_failed', sprintf('Could not apply %s.', $key), $result);
            }
        }

        // The root disk is inherited from the Incus profile, so it has to be
        // overridden on the instance before its size can be set.
        $this->run(['incus', 'config', 'device', 'override', $name, 'root']);

        $storage = $this->run([
            'incus', 'config', 'device', 'set', $name, 'root', 'size', sprintf('%dGiB', max(1, $lab->storage_limit)),
        ]);

        if (! $storage['successful']) {
            return $this->failed('limits_failed', 'The storage limit could not be applied.', $storage);
        }

        $this->applyIsolation($lab, $name);

        $username = $this->ensureAccessUser($lab, $name);

        if ($username === null) {
            return $this->failed('access_user_failed', 'The access user could not be created inside the container.', []);
        }

        if (! $this->ensureSshServer($name)) {
            return $this->failed('sshd_failed', 'The SSH server could not be installed inside the container.', []);
        }

        return $this->publishSsh($lab, $name);
    }

    /**
     * Default deny at the Incus network layer, enforced by nftables on the
     * compute plane: the lab may reach the outside world but not another
     * student's container.
     *
     * Isolation is recorded rather than allowed to abort the operation. A lab
     * that is running but not yet segmented is an honest state the portal must
     * be able to show, not a reason to destroy a working environment.
     */
    private function applyIsolation(Lab $lab, string $name): void
    {
        $acl = 'imnehnet-'.preg_replace('/[^a-z0-9-]/', '-', strtolower((string) $lab->container_name));

        $created = $this->run(['incus', 'network', 'acl', 'create', $this->remote.$acl]);
        $exists = str_contains($created['stderr'], 'already exists') || $created['successful'];

        if (! $exists) {
            $lab->forceFill(['isolation_status' => 'unsupported'])->saveQuietly();
            Log::warning('Could not create the isolation ACL.', ['acl' => $acl, 'stderr' => $created['stderr']]);

            return;
        }

        // Clear any stale rules, then allow outbound traffic and the loopback
        // of the lab's own network while leaving peer containers unreachable.
        $this->run(['incus', 'network', 'acl', 'set', $this->remote.$acl, 'ingress=[]']);
        $this->run(['incus', 'network', 'acl', 'set', $this->remote.$acl, 'egress=[]']);

        $this->run(['incus', 'network', 'acl', 'rule', 'add', $this->remote.$acl, 'egress', 'action=allow', 'destination=0.0.0.0/0']);
        $this->run(['incus', 'network', 'acl', 'rule', 'add', $this->remote.$acl, 'ingress', 'action=allow', 'source=0.0.0.0/0']);

        $applied = $this->run([
            'incus', 'config', 'device', 'set', $name, 'eth0',
            'security.acls='.$acl,
            'security.acls.default.ingress.action=drop',
        ]);

        if (! $applied['successful']) {
            $lab->forceFill(['isolation_status' => 'unsupported'])->saveQuietly();
            Log::warning('Could not attach the isolation ACL to the container NIC.', [
                'acl' => $acl,
                'stderr' => trim(substr($applied['stderr'], 0, 300)),
            ]);

            return;
        }

        $lab->forceFill(['isolation_status' => 'enforced'])->saveQuietly();
    }

    private function ensureAccessUser(Lab $lab, string $name): ?string
    {
        $username = $this->accessUsername($lab);

        $result = $this->run([
            'incus', 'exec', $name, '--',
            'bash', '-lc',
            sprintf(
                'id -u %1$s >/dev/null 2>&1 || useradd -m -s /bin/bash %1$s; usermod -aG sudo %1$s; echo -n ok',
                $username,
            ),
        ]);

        return str_contains($result['stdout'], 'ok') ? $username : null;
    }

    /**
     * The base image ships without an SSH server, so the published port would
     * have nothing to forward to. Install and start it before the port is
     * published, otherwise the portal would advertise an access path that does
     * not work.
     */
    private function ensureSshServer(string $name): bool
    {
        $install = $this->run([
            'incus', 'exec', $name, '--', 'bash', '-lc',
            'export DEBIAN_FRONTEND=noninteractive; apt-get update -qq && apt-get install -y -qq openssh-server >/dev/null 2>&1; '
            .'mkdir -p /run/sshd; ssh-keygen -A >/dev/null 2>&1; '
            .'(service ssh start || systemctl restart ssh || /usr/sbin/sshd) >/dev/null 2>&1; '
            .'pgrep -x sshd >/dev/null && echo -n ok || echo -n fail',
        ]);

        return str_contains($install['stdout'], 'ok');
    }

    private function publishSsh(Lab $lab, string $name): ?ProvisionedLab
    {
        $port = $this->ports->allocateSshPort($lab);

        $result = $this->run([
            'incus', 'config', 'device', 'add', $name, 'ssh-proxy',
            'proxy',
            'listen=tcp:0.0.0.0:'.$port,
            'connect=tcp:127.0.0.1:22',
        ]);

        if (! $result['successful']) {
            return $this->failed('port_publish_failed', 'The SSH port could not be published.', $result);
        }

        return null;
    }

    private function exists(Lab $lab): bool
    {
        return $this->run(['incus', 'info', $this->remoteName($lab)])['successful'];
    }

    private function remoteName(Lab $lab): string
    {
        return $this->remote.$lab->container_name;
    }

    private function accessUsername(Lab $lab): string
    {
        $owner = $lab->relationLoaded('owner') ? $lab->owner : $lab->owner()->first();

        if ($owner === null) {
            return 'student';
        }

        $prefix = strstr((string) $owner->email, '@', true);

        return $this->slug(is_string($prefix) && $prefix !== '' ? $prefix : $owner->name);
    }

    private function slug(string $value): string
    {
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', substr($value, 0, 16)));

        return $slug === '' ? 'student' : $slug;
    }

    private function memory(Lab $lab): string
    {
        return sprintf('%dMiB', max(128, (int) $lab->memory_limit));
    }

    /** @param array<int, mixed> $instance */
    private function address(array $instance): ?string
    {
        $networks = $instance['state']['network'] ?? [];

        if (! is_array($networks)) {
            return null;
        }

        foreach ($networks as $network) {
            if (! is_array($network)) {
                continue;
            }

            foreach (($network['addresses'] ?? []) as $address) {
                if (($address['family'] ?? null) === 'inet' && ($address['scope'] ?? null) === 'global') {
                    return (string) $address['address'];
                }
            }
        }

        return null;
    }

    private function failed(string $code, string $message, array $result): ProvisionedLab
    {
        Log::warning('Incus provisioner failed.', [
            'code' => $code,
            'stderr' => trim(substr($result['stderr'] ?? '', 0, 300)),
        ]);

        return new ProvisionedLab(
            successful: false,
            errorCode: $code,
            message: $message,
            detail: ['stderr' => trim(substr($result['stderr'] ?? '', 0, 300))],
        );
    }

    /**
     * @param  list<string>  $command
     * @return array{successful: bool, stdout: string, stderr: string}
     */
    private function run(array $command): array
    {
        try {
            $process = new Process($command, timeout: $this->timeoutSeconds);
            $process->setTimeout($this->timeoutSeconds);
            $process->run();

            return [
                'successful' => $process->isSuccessful(),
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
            ];
        } catch (ProcessTimedOutException) {
            return ['successful' => false, 'stdout' => '', 'stderr' => 'The incus command timed out.'];
        } catch (Throwable $exception) {
            Log::error('Incus command failed to start.', ['error' => $exception->getMessage()]);

            return ['successful' => false, 'stdout' => '', 'stderr' => 'The incus command could not be executed.'];
        }
    }
}
