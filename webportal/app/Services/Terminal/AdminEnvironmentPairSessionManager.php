<?php

namespace App\Services\Terminal;

use App\Models\TerminalSession;
use App\Models\User;
use Illuminate\Support\Collection;

final class AdminEnvironmentPairSessionManager
{
    public const PURPOSE = 'admin_pair';

    public function __construct(
        private readonly AdminEnvironmentPairResolver $resolver,
    ) {}

    /**
     * Retrieve only the authenticated admin's sessions
     * for the verified attacker/victim pair.
     *
     * No shell is launched by this method.
     */
    public function sessions(User $admin, int $labId): Collection
    {
        $pair = $this->resolver->resolve($admin, $labId);

        $ids = [
            $pair['attacker']->getKey(),
            $pair['victim']->getKey(),
        ];

        return TerminalSession::query()
            ->where('user_id', $admin->getKey())
            ->where('purpose', self::PURPOSE)
            ->where('lab_session_id', $labId)
            ->whereIn('lab_environment_id', $ids)
            ->whereNull('closed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get()
            ->unique('lab_environment_id');
    }

    /**
     * Return pair-level state without exposing credentials.
     */

    /**
     * IMNEHNET_ADMIN_PAIR_CLOSE_SCOPE_V1
     *
     * Locate the admin's pair terminal sessions for cleanup.
     * Deliberately does NOT require running containers:
     * a stopped or deleted lab must not prevent shell cleanup.
     *
     * Only admin_pair sessions for this operator
     * and this lab can be returned.
     *
     * @return Collection<int, TerminalSession>
     */
    public function sessionsForClose(User $admin, int $labId): Collection
    {
        if (
            ! $admin->isActive()
            || ! $admin->canOperateInfrastructure()
            || $labId <= 0
        ) {
            throw new \RuntimeException(
                'Administrative infrastructure access is required.'
            );
        }

        return TerminalSession::query()
            ->where('user_id', $admin->getKey())
            ->where('purpose', self::PURPOSE)
            ->where('lab_session_id', $labId)
            ->whereNull('closed_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function status(User $admin, int $labId): array
    {
        $pair = $this->resolver->resolve($admin, $labId);
        $sessions = $this->sessions($admin, $labId);

        $attackerId = $pair['attacker']->getKey();
        $victimId = $pair['victim']->getKey();

        $attackerOpen = $sessions->contains(
            fn (TerminalSession $session): bool =>
                (int) $session->lab_environment_id === (int) $attackerId
                && $session->processIsAlive()
        );

        $victimOpen = $sessions->contains(
            fn (TerminalSession $session): bool =>
                (int) $session->lab_environment_id === (int) $victimId
                && $session->processIsAlive()
        );

        return [
            'lab_id' => $labId,
            'attacker' => $pair['attacker']->container_name,
            'victim' => $pair['victim']->container_name,
            'attacker_open' => $attackerOpen,
            'victim_open' => $victimOpen,
            'pair_open' => $attackerOpen && $victimOpen,
        ];
    }
}
