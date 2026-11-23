<?php

namespace App\Services\Terminal;

use App\Models\TerminalSession;
use App\Models\User;
use RuntimeException;

final class AdminEnvironmentPairIdleService
{
    public const IDLE_MINUTES = 30;
    public const MAX_SESSION_MINUTES = 120;

    /**
     * Called only after a verified terminal interaction.
     *
     * Activity on either pane refreshes both sessions
     * belonging to the same administrator and lab pair.
     */
    public function recordActivity(
        User $admin,
        int $labId,
        string $sessionId
    ): int {
        if (
            ! $admin->isActive()
            || ! $admin->canOperateInfrastructure()
            || $labId <= 0
            || $sessionId === ''
        ) {
            throw new RuntimeException('Unauthorised terminal activity.');
        }

        $source = TerminalSession::query()
            ->whereKey($sessionId)
            ->where('user_id', $admin->getKey())
            ->where('lab_session_id', $labId)
            ->where(
                'purpose',
                AdminEnvironmentPairTerminalService::PURPOSE
            )
            ->whereNull('closed_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $source) {
            throw new RuntimeException(
                'Terminal session is closed, expired or inaccessible.'
            );
        }

        $refreshed = 0;
        $now = now();

        $sessions = TerminalSession::query()
            ->where('user_id', $admin->getKey())
            ->where('lab_session_id', $labId)
            ->where(
                'purpose',
                AdminEnvironmentPairTerminalService::PURPOSE
            )
            ->whereNull('closed_at')
            ->where('expires_at', '>', $now)
            ->get();

        foreach ($sessions as $session) {
            $idleDeadline = $now->copy()
                ->addMinutes(self::IDLE_MINUTES);

            $maximumDeadline = $session->created_at->copy()
                ->addMinutes(self::MAX_SESSION_MINUTES);

            $deadline = $idleDeadline->lt($maximumDeadline)
                ? $idleDeadline
                : $maximumDeadline;

            if ($deadline->lte($now)) {
                continue;
            }

            $updated = TerminalSession::query()
                ->whereKey($session->getKey())
                ->where('user_id', $admin->getKey())
                ->where('lab_session_id', $labId)
                ->where(
                    'purpose',
                    AdminEnvironmentPairTerminalService::PURPOSE
                )
                ->whereNull('closed_at')
                ->where('expires_at', '>', $now)
                ->where('expires_at', '<', $deadline)
                ->update(['expires_at' => $deadline]);

            $refreshed += $updated;
        }

        return $refreshed;
    }
}
