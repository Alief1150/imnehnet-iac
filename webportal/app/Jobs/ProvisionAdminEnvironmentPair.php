<?php

namespace App\Jobs;

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Models\AdminEnvironmentPair;
use App\Models\LabEnvironment;
use App\Services\GuidedCtf\AttackerPlane;
use App\Services\OpenTofuService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

final class ProvisionAdminEnvironmentPair implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 2400;
    public bool $failOnTimeout = true;

    public function __construct(public readonly int $pairId) {}

    public function handle(OpenTofuService $tofu, AttackerPlane $plane): void
    {
        Cache::lock('imnehnet:admin-pair:lifecycle:'.$this->pairId, 2700)
            ->block(60, function () use ($tofu, $plane): void {
                $pair = AdminEnvironmentPair::query()->find($this->pairId);
                if (! $pair || $pair->status !== 'QUEUED') {
                    return;
                }
                $attacker = LabEnvironment::query()->findOrFail($pair->attacker_environment_id);
                $victim = LabEnvironment::query()->findOrFail($pair->victim_environment_id);
                if ((int) $attacker->user_id !== (int) $pair->user_id
                    || (int) $victim->user_id !== (int) $pair->user_id
                    || $attacker->isProtected() || $victim->isProtected()) {
                    throw new RuntimeException('Ownership or protected workspace validation failed.');
                }
                $pair->forceFill(['status' => 'PROVISIONING'])->save();
                try {
                    $this->apply($tofu, $victim, '');
                    $this->apply($tofu, $attacker, (string) config('infrastructure.attacker_acl', 'ctf-attacker'));
                    $plane->allowPair((string) $attacker->internal_ip, (string) $victim->internal_ip);
                    $plane->persistRuleset();

                    foreach ([$attacker, $victim] as $environment) {
                        $environment->forceFill([
                            'status' => EnvironmentStatus::Ready,
                            'runtime_status' => RuntimeStatus::Running,
                            'provisioned_at' => now(),
                            'terminal_enabled' => false,
                        ])->save();
                    }
                    $pair->forceFill(['status' => 'READY', 'last_error' => null])->save();
                } catch (Throwable $error) {
                    report($error);
                    $this->markError($error);
                }
            });
    }

    public function failed(?Throwable $error): void
    {
        $this->markError($error ?? new RuntimeException('Worker failed or timed out.'));
    }

    private function markError(Throwable $error): void
    {
        $pair = AdminEnvironmentPair::query()->find($this->pairId);
        if (! $pair || $pair->status === 'READY') {
            return;
        }
        $pair->forceFill([
            'status' => 'ERROR',
            'last_error' => 'Provisioning failed; inspect Laravel logs and both OpenTofu workspaces. Partial resources may exist.',
        ])->save();
        foreach ([$pair->attacker_environment_id, $pair->victim_environment_id] as $id) {
            $env = LabEnvironment::query()->find($id);
            if ($env && in_array($env->status, [EnvironmentStatus::Provisioning, EnvironmentStatus::Configuring], true)) {
                $env->forceFill([
                    'status' => EnvironmentStatus::Error,
                    'runtime_status' => RuntimeStatus::Unknown,
                    'last_error' => 'Admin Pair provisioning failed. Do not delete one half individually.',
                ])->save();
            }
        }
    }

    private function apply(OpenTofuService $tofu, LabEnvironment $env, string $acl): void
    {
        if ($env->isProtected() || ! LabEnvironment::isValidContainerName((string) $env->container_name)) {
            throw new RuntimeException('Unsafe container name or workspace.');
        }
        $variables = [
            'container_name' => (string) $env->container_name,
            'container_image' => (string) $env->image,
            'incus_remote' => (string) $env->incus_remote,
            'static_ipv4' => (string) $env->internal_ip,
            'incus_network' => (string) config('infrastructure.incus_network', 'incusbr0'),
            'cpu_limit' => (int) $env->cpu_limit,
            'memory_limit' => (int) $env->memory_limit_mb.'MiB',
            'desired_running' => 'true',
        ];
        if ($acl !== '') {
            $variables['security_acls'] = $acl;
        }
        $tofu->withDirectoryLock(function () use ($tofu, $env, $variables): void {
            $selected = $tofu->selectOrCreateWorkspace((string) $env->tofu_workspace);
            if (! $selected->successful()) {
                throw new RuntimeException('Cannot select admin workspace.');
            }
            $result = $tofu->apply($variables);
            if (! $result->successful()) {
                throw new RuntimeException('OpenTofu failed provisioning '.$env->container_name.'.');
            }
        });
    }
}
