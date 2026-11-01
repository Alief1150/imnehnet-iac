<?php

namespace App\Services\GuidedCtf;

use App\Models\ActivityLog;
use App\Models\LabSession;
use App\Services\ActivityLogService;

final class ProvisioningTimeline
{
    public static function events(int $sessionId): array
    {
        $session = LabSession::query()->find($sessionId);
        $user = auth()->user();
        if (! $session || ! $user || (! $user->isAdmin() && (int) $session->user_id !== (int) $user->id)) {
            return [];
        }

        return ActivityLog::query()->where('action', 'CTF_LIFECYCLE')
            ->where('resource_type', LabSession::class)->where('resource_id', (string) $sessionId)
            ->where('actor_id', $session->user_id)->latest('id')->limit(200)->get()->reverse()
            ->map(fn ($row): array => [
                'time' => $row->created_at?->timezone('Asia/Jakarta')->format('H:i:s') ?? '',
                'stage' => $row->metadata['phase'] ?? '',
                'message' => $row->description,
                'level' => $row->status === 'completed' ? 'success' : ($row->status === 'failed' ? 'error' : 'info'),
            ])->values()->all();
    }

    public static function record(int $sessionId, string $stage, string $message, string $level = 'info'): void
    {
        $session = LabSession::query()->findOrFail($sessionId);
        $pair = [];
        foreach (['victim', 'attacker'] as $role) {
            $environment = $session->{$role.'Environment'};
            $pair[$role] = [
                'environment_id' => $session->{$role.'_environment_id'},
                'container_name' => $session->{$role.'_container'},
                'remote' => $session->{$role.'_remote'},
                'project' => $environment?->incus_project ?? 'default',
                'workspace' => $environment?->tofu_workspace,
            ];
        }
        app(ActivityLogService::class)->record(
            $session->owner(), 'CTF_LIFECYCLE', $session,
            $level === 'success' ? 'completed' : ($level === 'error' ? 'failed' : 'running'),
            $message,
            ['phase' => $stage, 'nested' => [
                'session_id' => $session->id, 'user_id' => $session->user_id, 'containers' => $pair,
            ]],
        );
    }
}
