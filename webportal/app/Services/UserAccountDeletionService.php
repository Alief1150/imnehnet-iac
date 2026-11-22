<?php
namespace App\Services;

use App\Enums\EnvironmentStatus;
use App\Enums\LabSessionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\FinalizeUserDeletion;
use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\User;
use App\Services\GuidedCtf\AdminPairedLabDeletionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use DomainException;

final class UserAccountDeletionService
{
    public function request(User $actor, User $subject): void
    {
        if (!$actor->isActive() || !$actor->isAdmin() || $actor->is($subject)) {
            throw new DomainException('Hanya admin aktif yang dapat menghapus akun lain.');
        }
        $id = (int) $subject->getKey();
        Cache::lock('imnehnet:user-deletion:'.$id, 180)->block(10, function () use ($actor, $id): void {
            $user = User::query()->findOrFail($id);
            if ($user->role === UserRole::Admin && $user->status === UserStatus::Active
                && !User::query()->where('role', UserRole::Admin)->where('status', UserStatus::Active)
                    ->whereKeyNot($id)->exists()) {
                throw new DomainException('Admin aktif terakhir tidak boleh dihapus.');
            }
            $labs = LabSession::query()->where('user_id', $id)
                ->where('status', '!=', LabSessionStatus::Deleted->value)->get();
            $owned = LabEnvironment::query()->where('user_id', $id)->get();
            $covered = [];
            foreach ($labs as $lab) {
                if (!in_array($lab->status, [
                    LabSessionStatus::Ready, LabSessionStatus::Running,
                    LabSessionStatus::Completed, LabSessionStatus::Stopped,
                    LabSessionStatus::Failed, LabSessionStatus::Deleting,
                ], true)) {
                    throw new DomainException('Lab masih provisioning/reset. Tidak aman menghapus user sekarang.');
                }
                foreach ([$lab->attackerEnvironment, $lab->victimEnvironment] as $env) {
                    if (!$env || $env->isProtected()) {
                        throw new DomainException('Pasangan lab tidak lengkap atau dilindungi.');
                    }
                    if ($env->user_id !== null && (int)$env->user_id !== $id) {
                        throw new DomainException('Ownership lab tidak sesuai.');
                    }
                    $covered[(int)$env->getKey()] = true;
                }
            }
            $individual = [];
            foreach ($owned as $env) {
                if (isset($covered[(int)$env->getKey()])) continue;
                if ($env->isProtected() || !app(EnvironmentLifecycleService::class)->isActionAllowed($env, 'delete')) {
                    throw new DomainException('Environment standalone tidak aman dihapus otomatis.');
                }
                $individual[] = $env;
            }
            $wasActive = $user->status === UserStatus::Active;
            $queued = 0;
            try {
                // Prevent new student operations while infrastructure is torn down.
                $user->forceFill(['status' => UserStatus::Disabled])->save();
                foreach ($labs as $lab) {
                    if ($lab->status === LabSessionStatus::Deleting) continue;
                    app(AdminPairedLabDeletionService::class)->request($actor, $lab->attackerEnvironment);
                    $queued++;
                }
                foreach ($individual as $env) {
                    app(EnvironmentLifecycleService::class)->request($actor, $env, 'delete');
                    $queued++;
                }
                FinalizeUserDeletion::dispatch($id)->delay(now()->addSeconds(30));
                Log::notice('USER_DELETE_CLEANUP_QUEUED', [
                    'actor_id' => $actor->getKey(), 'user_id' => $id,
                    'labs' => $labs->count(), 'standalone' => count($individual),
                ]);
            } catch (\Throwable $e) {
                // If any destruction was queued, keep account disabled.
                if ($queued === 0 && $wasActive) {
                    $user->forceFill(['status' => UserStatus::Active])->save();
                }
                throw $e;
            }
        });
    }
}
