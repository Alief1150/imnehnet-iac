<?php

namespace App\Http\Controllers;

use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\TerminalSession;
use App\Models\User;
use App\Services\Terminal\AdminEnvironmentPairResolver;
use App\Services\Terminal\AdminEnvironmentPairTerminalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class AdminEnvironmentPairController extends Controller
{
    private function admin(): User
    {
        $user = auth()->user();

        abort_unless(
            $user instanceof User
            && $user->isActive()
            && $user->canOperateInfrastructure(),
            403
        );

        return $user;
    }

    private function lab(int $environment): LabSession
    {
        return LabSession::query()
            ->where('attacker_environment_id', $environment)
            ->firstOrFail();
    }

    private function pair(int $environment): array
    {
        $lab = $this->lab($environment);

        $attacker = LabEnvironment::query()
            ->findOrFail($lab->attacker_environment_id);

        $victim = LabEnvironment::query()
            ->findOrFail($lab->victim_environment_id);

        return [$lab, $attacker, $victim];
    }

    private function activeSessions(User $admin, LabSession $lab): array
    {
        $service = app(AdminEnvironmentPairTerminalService::class);

        $rows = TerminalSession::query()
            ->where('user_id', $admin->getKey())
            ->where(
                'purpose',
                AdminEnvironmentPairTerminalService::PURPOSE
            )
            ->where('lab_session_id', $lab->getKey())
            ->whereNull('closed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();

        $result = [];

        foreach ([
            'attacker' => $lab->attacker_environment_id,
            'victim' => $lab->victim_environment_id,
        ] as $role => $environmentId) {
            $session = $rows->first(
                fn (TerminalSession $row) =>
                    (int) $row->lab_environment_id === (int) $environmentId
                    && $service->embedUrl($row) !== null
            );

            $result[$role] = $session
                ? [
                    'url' => $service->embedUrl($session),
                    'port' => (int) $session->ttyd_port,
                ]
                : null;
        }

        return $result;
    }

    public function show(int $environment)
    {
        $admin = $this->admin();

        [$lab, $attacker, $victim] = $this->pair($environment);

        $sessions = $this->activeSessions($admin, $lab);

        return view('admin.environment-pair', [
            'lab' => $lab,
            'attacker' => $attacker,
            'victim' => $victim,
            'owner' => User::query()->find($lab->user_id),
            'sessions' => $sessions,
            'environmentId' => $environment,
            'pairOpen' =>
                $sessions['attacker'] !== null
                && $sessions['victim'] !== null,
        ]);
    }

    public function open(int $environment): RedirectResponse
    {
        $admin = $this->admin();
        [$lab] = $this->pair($environment);

        // Validate ownership, node mapping, container state and privileges.
        app(AdminEnvironmentPairResolver::class)
            ->resolve($admin, (int) $lab->getKey());

        try {
            $service = app(AdminEnvironmentPairTerminalService::class);

            $sessions = $service->openPair(
                $admin,
                (int) $lab->getKey()
            );

            foreach ($sessions as $session) {
                $ticket = $service->embedTicket($session);

                if (!$ticket) {
                    throw new RuntimeException(
                        'Embed authentication ticket unavailable.'
                    );
                }

                $port = (int) $session->ttyd_port;

                Cookie::queue(cookie(
                    'imnehnet_slot_'.$port,
                    $ticket,
                    30,
                    '/',
                    null,
                    request()->isSecure(),
                    true,
                    false,
                    'lax'
                ));
            }

            Log::notice('ADMIN_PAIR_OPEN', [
                'admin_id' => $admin->getKey(),
                'lab_session_id' => $lab->getKey(),
            ]);

            return redirect()
                ->route('admin.environment-pair.show', $environment)
                ->with('success', 'Pair terminal opened.');

        } catch (Throwable $exception) {
            Log::error('ADMIN_PAIR_OPEN_FAILED', [
                'admin_id' => $admin->getKey(),
                'lab_session_id' => $lab->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('admin.environment-pair.show', $environment)
                ->with('error', 'Open Pair gagal. Periksa log ADMIN_PAIR_OPEN_FAILED.');
        }
    }

    public function close(int $environment): RedirectResponse
    {
        $admin = $this->admin();
        [$lab] = $this->pair($environment);

        $sessions = TerminalSession::query()
            ->where('user_id', $admin->getKey())
            ->where(
                'purpose',
                AdminEnvironmentPairTerminalService::PURPOSE
            )
            ->where('lab_session_id', $lab->getKey())
            ->whereNull('closed_at')
            ->get();

        try {
            app(AdminEnvironmentPairTerminalService::class)
                ->closeAll($admin, (int) $lab->getKey());

            foreach ($sessions as $session) {
                if (is_numeric($session->ttyd_port)) {
                    Cookie::queue(
                        Cookie::forget(
                            'imnehnet_slot_'.(int) $session->ttyd_port
                        )
                    );
                }
            }

            Log::notice('ADMIN_PAIR_CLOSE', [
                'admin_id' => $admin->getKey(),
                'lab_session_id' => $lab->getKey(),
            ]);

            return redirect()
                ->route('admin.environment-pair.show', $environment)
                ->with('success', 'Administrative pair closed.');

        } catch (Throwable $exception) {
            Log::error('ADMIN_PAIR_CLOSE_FAILED', [
                'admin_id' => $admin->getKey(),
                'lab_session_id' => $lab->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('admin.environment-pair.show', $environment)
                ->with('error', 'Close All gagal. Periksa log ADMIN_PAIR_CLOSE_FAILED.');
        }
    }
}
