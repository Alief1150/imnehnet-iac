<?php
namespace App\Jobs;

use App\Enums\LabSessionStatus;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\User;
use App\Services\GuidedCtf\ContainerAbsenceVerifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class FinalizeUserDeletion implements ShouldQueue
{
    use Queueable;
    public int $tries = 120;
    public int $timeout = 60;
    public function __construct(public readonly int $userId) {}

    public function handle(ContainerAbsenceVerifier $absence): void
    {
        $user = User::query()->find($this->userId);
        if (!$user) return;
        if ($user->isActive()) {
            throw new RuntimeException('Account is active again. Refusing to finalize.');
        }
        $pending = LabSession::query()->where('user_id', $this->userId)
            ->where('status', '!=', LabSessionStatus::Deleted->value)->exists();
        $environments = LabEnvironment::query()->where('user_id', $this->userId)->exists();
        if ($pending || $environments) {
            // Do not delete user while a teardown is running or has failed.
            // Delayed retries keep the account intact and disabled.
            $this->release(30);
            return;
        }
        // Even if DB rows have been lost, verify canonical Incus targets.
        foreach ([
            [config('infrastructure.incus_attacker_remote', 'lab-atk'), 'attacker-user-'.$this->userId],
            [config('infrastructure.incus_remote', 'lab-env'), 'victim-user-'.$this->userId],
        ] as [$remote, $name]) {
            if (!$absence->isAbsent(rtrim((string)$remote, ':'), $name)) {
                throw new RuntimeException('Container still exists or cannot be verified: '.$name);
            }
        }
        // UserObserver applies the same database guards to all deletion paths.
        $user->delete();
        Log::notice('USER_DELETE_FINALIZED', ['user_id' => $this->userId]);
    }
}
