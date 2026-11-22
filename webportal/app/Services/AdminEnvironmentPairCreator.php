<?php

namespace App\Services;

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Jobs\ProvisionAdminEnvironmentPair;
use App\Models\AdminEnvironmentPair;
use App\Models\LabEnvironment;
use App\Models\User;
use App\Services\GuidedCtf\PairAddressAllocator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AdminEnvironmentPairCreator
{
    public function create(User $user, string $name): AdminEnvironmentPair
    {
        if (! $user->isActive() || ! $user->isAdmin()) {
            throw new RuntimeException('Only an active administrator may create pairs.');
        }
        if (preg_match('/^[a-z][a-z0-9-]{2,38}$/D', $name) !== 1) {
            throw new RuntimeException('Pair name must contain 3–39 lowercase letters, digits or dashes.');
        }

        $pair = Cache::lock('imnehnet:ctf:pair-allocation', 120)->block(45, function () use ($user, $name): AdminEnvironmentPair {
            // The allocator sees records created by both Guided CTF and Admin Pair.
            $addresses = app(PairAddressAllocator::class)->allocate();
            $db = DB::connection((string) config('infrastructure.environment_connection', 'imnehnet'));

            return $db->transaction(function () use ($user, $name, $addresses): AdminEnvironmentPair {
                if (AdminEnvironmentPair::query()->where('user_id', $user->getKey())->where('name', $name)->exists()) {
                    throw new RuntimeException('This administrator already has a pair with that name.');
                }
                $pair = AdminEnvironmentPair::query()->create([
                    'user_id' => $user->getKey(), 'name' => $name, 'status' => 'QUEUED',
                ]);
                $id = (int) $pair->getKey();
                $uid = (int) $user->getKey();
                $victim = LabEnvironment::query()->create([
                    'user_id' => $uid,
                    'container_name' => "victim-user-{$uid}-p{$id}",
                    'incus_remote' => rtrim((string) config('infrastructure.incus_remote', 'lab-env'), ':'),
                    'incus_project' => 'default',
                    'image' => (string) config('infrastructure.lab_image', 'images:ubuntu/24.04'),
                    'internal_ip' => $addresses['victim_ip'],
                    'status' => EnvironmentStatus::Provisioning,
                    'runtime_status' => RuntimeStatus::Unknown,
                    'cpu_limit' => (int) config('infrastructure.default_cpu_limit', 2),
                    'memory_limit_mb' => (int) config('infrastructure.default_memory_limit_mb', 2048),
                    'disk_limit_gb' => (int) config('infrastructure.default_disk_limit_gb', 8),
                    'tofu_workspace' => "admin-victim-p{$id}",
                    'terminal_enabled' => false,
                ]);
                $attacker = LabEnvironment::query()->create([
                    'user_id' => $uid,
                    'container_name' => "attacker-user-{$uid}-p{$id}",
                    'incus_remote' => rtrim((string) config('infrastructure.incus_attacker_remote', 'lab-atk'), ':'),
                    'incus_project' => 'default',
                    'image' => (string) config('infrastructure.attacker_image', 'images:kali'),
                    'internal_ip' => $addresses['attacker_ip'],
                    'status' => EnvironmentStatus::Provisioning,
                    'runtime_status' => RuntimeStatus::Unknown,
                    'cpu_limit' => (int) config('infrastructure.default_cpu_limit', 2),
                    'memory_limit_mb' => (int) config('infrastructure.ctf_attacker_memory_limit_mb', 1024),
                    'disk_limit_gb' => (int) config('infrastructure.default_disk_limit_gb', 8),
                    'tofu_workspace' => "admin-attacker-p{$id}",
                    'terminal_enabled' => false,
                ]);
                $pair->forceFill([
                    'victim_environment_id' => $victim->getKey(),
                    'attacker_environment_id' => $attacker->getKey(),
                ])->save();

                return $pair;
            });
        });

        try {
            ProvisionAdminEnvironmentPair::dispatch((int) $pair->getKey());
        } catch (\Throwable $error) {
            $pair->forceFill(['status' => 'ERROR', 'last_error' => 'Queue dispatch failed. No compute work was started.'])->save();
            throw $error;
        }

        return $pair;
    }
}
