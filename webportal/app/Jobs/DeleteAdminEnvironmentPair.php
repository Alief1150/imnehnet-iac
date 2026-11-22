<?php

namespace App\Jobs;

use App\Models\AdminEnvironmentPair;
use App\Models\LabEnvironment;
use App\Services\GuidedCtf\AttackerPlane;
use App\Services\OpenTofuService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

final class DeleteAdminEnvironmentPair implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 2400;

    public function __construct(public readonly int $pairId) {}

    public function handle(OpenTofuService $tofu, AttackerPlane $plane): void
    {
        Cache::lock('imnehnet:admin-pair:lifecycle:'.$this->pairId, 2700)
            ->block(60, function () use ($tofu, $plane): void {
                $pair = AdminEnvironmentPair::query()->find($this->pairId);
                if (! $pair || $pair->status !== 'DELETING') {
                    return;
                }
                $attacker = LabEnvironment::query()->find($pair->attacker_environment_id);
                $victim = LabEnvironment::query()->find($pair->victim_environment_id);
                if (! $attacker || ! $victim
                    || (int) $attacker->user_id !== (int) $pair->user_id
                    || (int) $victim->user_id !== (int) $pair->user_id
                    || $attacker->isProtected() || $victim->isProtected()) {
                    $pair->forceFill(['status' => 'ERROR', 'last_error' => 'Invalid pair ownership or missing inventory. Manual review required.'])->save();
                    return;
                }
                try {
                    $plane->denyPair((string) $attacker->internal_ip, (string) $victim->internal_ip);
                    $plane->persistRuleset();
                    foreach ([$attacker, $victim] as $env) {
                        $role = (int) $env->getKey()
                            === (int) $attacker->getKey()
                            ? 'attacker' : 'victim';

                        app(\App\Services\AdminPairScopedCleanup::class)
                            ->remove($env, $role, (int) $pair->getKey());
                    }
                    // Do not remove inventory until both infrastructure teardowns succeed.
                    $attacker->delete();
                    $victim->delete();
                    $pair->delete();
                } catch (Throwable $error) {
                    report($error);
                    $pair->forceFill(['status' => 'ERROR', 'last_error' => 'Pair cleanup incomplete. Review logs before retry.'])->save();
                    foreach ([$attacker, $victim] as $env) {
                        if ($env) {
                            $env->forceFill([
                                'status' => \App\Enums\EnvironmentStatus::Error,
                                'runtime_status' => \App\Enums\RuntimeStatus::Unknown,
                            ])->save();
                        }
                    }
                }
            });
    }
}
