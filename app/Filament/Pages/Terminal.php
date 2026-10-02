<?php

namespace App\Filament\Pages;

use App\Models\LabEnvironment;
use App\Models\TerminalSession;
use App\Services\TerminalService;
use App\Support\Lucide;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

/**
 * The web terminal.
 *
 * The page never asks the browser for anything that decides which shell opens.
 * It is handed an environment id, the container behind that id is read from the
 * inventory, and the address the browser is given names a slot rather than a
 * container. A request that arrives with a container name is not a request this
 * page can act on at all.
 *
 * Every state here is a real observation. There is no path that renders a
 * terminal-looking frame when nothing is listening behind it, and the page says
 * which of the several reasons applies rather than showing a single "offline".
 * A student who cannot open a shell should be able to tell "the container is
 * stopped" from "the terminal is switched off for this environment" from
 * "browser access is not configured", because only one of those is theirs to
 * fix.
 */
final class Terminal extends Page
{
    protected string $view = 'filament.pages.terminal';

    protected static ?string $navigationLabel = 'Terminal';

    protected static ?int $navigationSort = 30;

    protected static string|\UnitEnum|null $navigationGroup = 'Environments';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->isActive();
    }

    public static function getNavigationIcon(): Htmlable|string|null
    {
        return Lucide::icon('terminal');
    }

    /**
     * The environments this user may open a shell on.
     *
     * An administrator sees all of them. Anyone else sees only what they own,
     * and ownership is compared here rather than assumed, because the whole
     * point of the session record is that it cannot be pointed somewhere it was
     * not granted.
     *
     * @return Collection<int, LabEnvironment>
     */
    public function environments(): Collection
    {
        return LabEnvironment::query()
            ->when(
                ! auth()->user()?->isAdmin(),
                fn ($query) => $query->where('user_id', auth()->id()),
            )
            ->orderBy('container_name')
            ->get();
    }

    public function sessionFor(LabEnvironment $environment): ?TerminalSession
    {
        return TerminalSession::query()
            ->where('lab_environment_id', $environment->id)
            ->usable()
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Open, or reattach to, a terminal on an environment.
     */
    public function openTerminal(int $environmentId): void
    {
        $environment = $this->environments()->firstWhere('id', $environmentId);

        if (! $environment instanceof LabEnvironment) {
            // Refused without an explanation of what exists: the caller is not
            // being told whether an environment they cannot see is real.
            $this->deny();

            return;
        }

        try {
            $session = app(TerminalService::class)->open(auth()->user(), $environment);
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Terminal not available')
                ->body($exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'The terminal session could not be started.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $url = app(TerminalService::class)->browserUrl($session);

        $notification = Notification::make()
            ->title($url === null ? 'Session started, browser access is off' : 'Terminal ready')
            ->body($url === null
                ? 'The shell is running on the management plane, but browser access is not configured, so there is no address to give the browser. See infrastructure.terminal.browser_access_enabled.'
                : sprintf('Session expires at %s.', $session->expires_at->format('H:i:s')));

        // A warning rather than a success when there is no address: the shell is
        // genuinely running, and saying so plainly is more useful than a green
        // box next to a terminal that will not load.
        $url === null ? $notification->warning()->send() : $notification->success()->send();
    }

    public function closeSession(int $sessionId): void
    {
        $session = TerminalSession::query()->whereKey($sessionId)->first();

        // Scoped to the same list the page renders. A session id from anywhere
        // else must not be closable, because closing is a state change and the
        // row is the authority.
        $visible = $session !== null
            && $this->environments()->contains('id', $session->lab_environment_id);

        if ($session === null || ! $visible) {
            $this->deny();

            return;
        }

        app(TerminalService::class)->close($session);

        Notification::make()
            ->title('Session closed')
            ->body('The process was signalled and the slot is no longer granted.')
            ->success()
            ->send();
    }

    /**
     * Why a terminal is or is not available right now.
     *
     * Ordered so the first thing that stops the session is the thing reported.
     * Reporting "browser access is off" for a container that is not running
     * would point the reader at the wrong problem.
     *
     * @return array{code: string, label: string, tone: string, detail: string}
     */
    public function statusFor(LabEnvironment $environment): array
    {
        $service = app(TerminalService::class);

        if (! $environment->terminal_enabled) {
            return $this->state(
                'terminal_disabled',
                'NOT CONFIGURED',
                'neutral',
                'This environment has the terminal switched off.',
            );
        }

        if (! $environment->runtime_status->isRunning()) {
            return $this->state(
                'not_running',
                'NOT RUNNING',
                'danger',
                sprintf(
                    'The container is %s. Start it before opening a terminal.',
                    $environment->runtime_status->label(),
                ),
            );
        }

        $session = $this->sessionFor($environment);

        if ($session === null) {
            return $this->state(
                'no_session',
                'NOT CONNECTED',
                'neutral',
                'No terminal session is open for this environment.',
            );
        }

        if (! $service->isConnected($session)) {
            return $this->state(
                'process_gone',
                'NOT CONNECTED',
                'danger',
                'A session row exists but its process is gone. Reconnect to start a new one.',
            );
        }

        if (! config('infrastructure.terminal.browser_access_enabled')) {
            return $this->state(
                'browser_access_off',
                'NOT REACHABLE',
                'warning',
                'The shell is running, but browser access is switched off, so there is no address the browser can use. This is a deployment setting, not something wrong with the environment.',
            );
        }

        if ($service->browserUrl($session) === null) {
            return $this->state(
                'no_address',
                'NOT REACHABLE',
                'danger',
                'The session has no usable address. Close it and reconnect.',
            );
        }

        return $this->state(
            'connected',
            'CONNECTED',
            'success',
            sprintf('Expires at %s.', $session->expires_at->format('H:i:s')),
        );
    }

    public function terminalUrl(LabEnvironment $environment): ?string
    {
        $session = $this->sessionFor($environment);

        if ($session === null || ! app(TerminalService::class)->isConnected($session)) {
            return null;
        }

        return app(TerminalService::class)->browserUrl($session);
    }

    /**
     * @return array{code: string, label: string, tone: string, detail: string}
     */
    private function state(string $code, string $label, string $tone, string $detail): array
    {
        return compact('code', 'label', 'tone', 'detail');
    }

    private function deny(): void
    {
        Notification::make()
            ->title('Not available')
            ->body('That environment is not yours to open, or no longer exists.')
            ->danger()
            ->send();
    }
}
