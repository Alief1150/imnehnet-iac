<?php

namespace App\Filament\Pages;

use App\Models\LabEnvironment;
use App\Models\LabSession;
use App\Models\TerminalSession;
use App\Services\InfrastructureTopologyService;
use App\Services\Terminal\AdminTerminalService;
use App\Services\TerminalService;
use App\Support\Lucide;
use App\Support\TerminalSlotAuth;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\Url;
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

    protected static ?string $navigationLabel = 'Sandbox';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Sandbox';

    protected static ?int $navigationSort = 2;

    /**
     * The selected target is server-side state so only one terminal iframe is
     * mounted at a time. This avoids opening browser connections to every
     * environment merely because it appears in the target selector.
     */
    public ?int $selectedEnvironmentId = null;

    /**
     * Admin Terminal selects infrastructure hosts, not student LabEnvironment
     * records. Transport is attached by AdminTerminalService, which is the only
     * thing that may start one.
     *
     * Kept in the address so a reload lands back on the chosen node instead of
     * falling back to the first one: the address bar is the only piece of page
     * state that survives a refresh.
     */
    #[Url(as: 'target')]
    public string $selectedAdminTarget = 'iaas-mgmt';

    /**
     * Open a shell on the selected node.
     *
     * Every refusal is turned into a message on the page rather than an exception,
     * because the refusals are the expected cases here: an unprivileged role, a node
     * that will not answer, a slot that is already in use. A stack trace for any of
     * those tells an operator nothing they can act on.
     */
    public function openAdminSession(): void
    {
        $this->openAdminSessionOn($this->selectedAdminTarget);
    }

    /**
     * Open a shell on the named node, selecting it first.
     *
     * One click instead of select-then-open, so a shell never depends on an
     * earlier selection round trip having applied. The selection is set before
     * the shell exists: if the open fails, the panel shows that node with no
     * session and the reason, rather than some other node.
     */
    public function openAdminSessionFor(string $node): void
    {
        $allowed = collect($this->adminTargets())->pluck('key');

        if (! $allowed->contains($node)) {
            $this->deny();

            return;
        }

        $this->selectedAdminTarget = $node;

        $this->openAdminSessionOn($node);
    }

    /**
     * Open a shell on a node key the caller has already decided.
     *
     * Every refusal is turned into a message on the page rather than an exception,
     * because the refusals are the expected cases here: an unprivileged role, a node
     * that will not answer, a slot that is already in use. A stack trace for any of
     * those tells an operator nothing they can act on.
     */
    private function openAdminSessionOn(string $nodeKey): void
    {
        $user = auth()->user();

        if ($user === null) {
            $this->deny();

            return;
        }

        try {
            $service = app(AdminTerminalService::class);
            $session = $service->open($user, $nodeKey);

            $this->rememberEmbedTicket($service, $session);

            Notification::make()
                ->title('Shell opened')
                ->body(sprintf('Connected to %s as %s.', $session->target_node, $session->shell_user))
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Could not open a shell')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    /**
     * Close this person's live shell on the selected node.
     */
    public function closeAdminSession(): void
    {
        $session = $this->adminSession();

        if ($session === null) {
            return;
        }

        app(AdminTerminalService::class)->close($session);
        $this->forgetEmbedTicket($session);

        Notification::make()
            ->title('Shell closed')
            ->body('The listener was stopped and its credential revoked.')
            ->success()
            ->send();
    }

    /**
     * Close this person's live shell on the named node.
     *
     * Scoped by argument rather than by selection, so each target card can offer
     * its own close button next to its open one: closing a shell never depends
     * on the selected node being the right one at click time. The selection
     * follows the node, so the panel shows what was just closed.
     */
    public function closeAdminSessionFor(string $node): void
    {
        $user = auth()->user();

        if ($user === null || ! $user->canOperateInfrastructure()) {
            $this->deny();

            return;
        }

        $allowed = collect($this->adminTargets())->pluck('key');

        if (! $allowed->contains($node)) {
            $this->deny();

            return;
        }

        $session = TerminalSession::query()
            ->where('user_id', $user->getKey())
            ->where('target_kind', 'node')
            ->where('target_node', $node)
            ->whereNull('closed_at')
            ->orderByDesc('id')
            ->first();

        $this->selectedAdminTarget = $node;

        if ($session === null) {
            return;
        }

        app(AdminTerminalService::class)->close($session);
        $this->forgetEmbedTicket($session);

        Notification::make()
            ->title('Shell closed')
            ->body('The listener was stopped and its credential revoked.')
            ->success()
            ->send();
    }

    /**
     * This person's live shell on the selected node, if there is one.
     *
     * Scoped to the signed-in user rather than to "the newest row for this node": a
     * node can have one shell per person, and an administrator must never be shown
     * another administrator's terminal.
     */
    public function adminSession(): ?TerminalSession
    {
        $user = auth()->user();

        if ($user === null || ! $user->canOperateInfrastructure()) {
            return null;
        }

        return TerminalSession::query()
            ->where('user_id', $user->getKey())
            ->where('target_kind', 'node')
            ->where('target_node', $this->selectedAdminTarget)
            ->whereNull('closed_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Whether this person holds a live shell on the named node.
     *
     * Read per card so each target can offer its own close button. One indexed
     * lookup per card per render — three small queries, not a scan.
     */
    public function hasAdminSession(string $node): bool
    {
        $user = auth()->user();

        if ($user === null || ! $user->canOperateInfrastructure()) {
            return false;
        }

        return TerminalSession::query()
            ->where('user_id', $user->getKey())
            ->where('target_kind', 'node')
            ->where('target_node', $node)
            ->whereNull('closed_at')
            ->exists();
    }

    /**
     * The URL the browser opens for the shell.
     *
     * Null when there is no live session, and null again when browser access is switched
     * off — the same gate terminalUrl() applies, because an administrative shell is a
     * shell like any other and a deployment that has decided not to hand shells to
     * browsers has not decided that administrators are the exception.
     *
     * No credential in the path or the query. ttyd's -c is HTTP Basic auth, verified on
     * this host: no credential answers 401, the right one answers 200 and upgrades the
     * websocket. A query parameter is not read as a credential by anything, and it is
     * written to the access log, to browser history, and to the Referer of anything the
     * page loads. The secret is offered to the operator separately by
     * AdminTerminalService::credentialsFor().
     */
    public function adminUrl(): ?string
    {
        if (! config('infrastructure.terminal.browser_access_enabled')) {
            return null;
        }

        $session = $this->adminSession();

        if ($session === null) {
            return null;
        }

        return app(AdminTerminalService::class)->browserUrl($session);
    }

    /**
     * The address the embedded frame loads for the shell.
     *
     * Names only the slot — never the ticket, which the browser carries in a cookie
     * queued when the session opened. Null when there is no live session, when
     * browser access is switched off, or when the slot is not leased for embedding,
     * and each of those is a state in which no frame may mount.
     */
    public function adminEmbedUrl(): ?string
    {
        $session = $this->adminSession();

        if ($session === null) {
            return null;
        }

        return app(AdminTerminalService::class)->embedUrl($session);
    }

    /**
     * The frame's address with the console's look appended.
     *
     * The look travels in the query string because that is where this build of the
     * client reads its preferences; the ticket stays in the cookie. Only
     * presentation — font size and theme, neither of which is a secret.
     */
    public function adminEmbedFrameSrc(): ?string
    {
        $url = $this->adminEmbedUrl();

        if ($url === null) {
            return null;
        }

        return $url.'?'.http_build_query(AdminTerminalService::terminalPreferences());
    }

    /**
     * Hand the browser the ticket cookie for this session.
     *
     * Queued rather than set, because a Livewire action answers with JSON and the
     * cookie rides on that response. httpOnly so page script never sees the ticket —
     * the frame needs no script access to it, only the browser's cookie jar.
     * Path-limited to /embed/ so it is sent nowhere else. No Secure flag: this
     * deployment serves plain HTTP, and a Secure cookie would never be sent.
     */
    private function rememberEmbedTicket(AdminTerminalService|TerminalService $service, TerminalSession $session): void
    {
        if (! config('infrastructure.terminal.browser_access_enabled')) {
            return;
        }

        $ticket = $service->embedTicketFor($session);

        if ($ticket === null) {
            return;
        }

        Cookie::queue(cookie(
            AdminTerminalService::embedCookieName((int) $session->ttyd_port),
            $ticket,
            max(1, (int) ceil(($session->expires_at->getTimestamp() - time()) / 60)),
            '/embed/',
            null,
            request()->isSecure(),
            true,
            false,
            'Lax',
        ));
    }

    /**
     * Take the ticket cookie back when the session closes.
     *
     * The ticket file is already revoked by close(), so a leftover cookie authorises
     * nothing — but a dead secret lingering in the jar is how a later session
     * inherits confusion, so it goes with the session.
     */
    private function forgetEmbedTicket(TerminalSession $session): void
    {
        Cookie::queue(Cookie::forget(
            AdminTerminalService::embedCookieName((int) $session->ttyd_port),
            '/embed/',
        ));
    }

    /**
     * The pair the browser must present, for the operator to be handed.
     *
     * Surfaced rather than embedded. An iframe cannot send an Authorization header —
     * there is no attribute for it — so the shell opens in its own tab and the browser is
     * prompted once.
     *
     * @return array{user: string, password: string}|null
     */
    /**
     * The pair this student's browser must present for their own session.
     *
     * Read from the session row rather than regenerated, so what is offered is the credential
     * Apache is actually checking. Scoped to the one environment asked about, so one student is
     * never handed another's.
     *
     * @return array{user: string, password: string}|null
     */
    public function studentCredentials(LabEnvironment $environment): ?array
    {
        $session = $this->sessionFor($environment);

        return $session === null
            ? null
            : [
                'user' => TerminalSlotAuth::USERNAME,
                'password' => (string) $session->credential,
            ];
    }

    public function adminCredentials(): ?array
    {
        $session = $this->adminSession();

        return $session === null
            ? null
            : app(AdminTerminalService::class)->credentialsFor($session);
    }

    /**
     * Whether the shell is alive, for the badge.
     *
     * Reports NOT REACHABLE rather than Disconnected when a session exists but the
     * deployment is not handing addresses to browsers. Those are different situations and
     * the operator has to be able to tell them apart: one is a shell that died, the other
     * is a shell that is fine and cannot be seen.
     */
    public function adminSessionState(): array
    {
        $session = $this->adminSession();

        if ($session === null) {
            return [
                'connected' => false,
                'reachable' => false,
                'label' => 'Disconnected',
                'duration' => 'Not started',
            ];
        }

        $connected = app(AdminTerminalService::class)->isConnected($session);
        $reachable = (bool) config('infrastructure.terminal.browser_access_enabled');

        return [
            'connected' => $connected,
            'reachable' => $reachable,
            'label' => match (true) {
                ! $connected => 'Disconnected',
                ! $reachable => 'Not reachable',
                default => 'Connected',
            },
            'duration' => $session->created_at === null
                ? 'Not started'
                : sprintf(
                    '%02d:%02d:%02d',
                    intdiv((int) $session->created_at->diffInSeconds(now()), 3600),
                    intdiv((int) $session->created_at->diffInSeconds(now()) % 3600, 60),
                    (int) $session->created_at->diffInSeconds(now()) % 60,
                ),
        ];
    }

    /**
     * @return list<array{key:string,name:string,role:string,address:?string,health:string,healthLabel:string,detail:?string}>
     */
    public function adminTargets(): array
    {
        $fallback = collect([
            ['key' => 'iaas-mgmt', 'name' => 'IAAS-MGMT', 'role' => 'Management Plane'],
            ['key' => 'lab-env', 'name' => 'LAB-ENV', 'role' => 'Lab Compute'],
            ['key' => 'lab-atk', 'name' => 'LAB-ATK', 'role' => 'Attacker Plane'],
        ]);

        try {
            $hosts = collect(app(InfrastructureTopologyService::class)->topology()['hosts'] ?? []);

            return $fallback->map(function (array $target) use ($hosts): array {
                $host = $hosts->firstWhere('name', $target['name']);

                return [
                    ...$target,
                    'address' => $host['address'] ?? null,
                    'health' => $host['health'] ?? 'unknown',
                    'healthLabel' => match ($host['health'] ?? 'unknown') {
                        'healthy' => 'Online',
                        'offline' => 'Offline',
                        'degraded' => 'Degraded',
                        default => 'Unknown',
                    },
                    'detail' => $host['detail'] ?? null,
                ];
            })->all();
        } catch (Throwable) {
            return $fallback->map(fn (array $target): array => [
                ...$target,
                'address' => null,
                'health' => 'unknown',
                'healthLabel' => 'Unknown',
                'detail' => null,
            ])->all();
        }
    }

    public function selectAdminTarget(mixed $target): void
    {
        if (! auth()->user()?->canOperateInfrastructure()) {
            abort(403);
        }

        $allowed = collect($this->adminTargets())->pluck('key');

        // Refused, not fatal: a caller reaching this with anything but a declared
        // key is lost, and a 500 would not tell them which keys exist.
        if (! is_string($target) || ! $allowed->contains($target)) {
            abort(404);
        }

        $this->selectedAdminTarget = $target;
    }

    public function mount(): void
    {
        $this->selectedEnvironmentId = $this->environments()->first()?->id;

        // A value from the address bar is user input: reset anything outside the
        // declared set rather than rendering a node the console cannot open.
        $allowed = collect($this->adminTargets())->pluck('key');

        if (! $allowed->contains($this->selectedAdminTarget)) {
            $this->selectedAdminTarget = 'iaas-mgmt';
        }
    }

    public function selectEnvironment(int $environmentId): void
    {
        $environment = $this->environments()->firstWhere('id', $environmentId);

        if (! $environment instanceof LabEnvironment) {
            $this->deny();

            return;
        }

        $this->selectedEnvironmentId = $environment->id;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return auth()->user()?->canOperateInfrastructure()
            ? 'Secure administrative console for authorized IMNEHNET lab environments.'
            : 'Secure shell access to your assigned IMNEHNET lab environment.';
    }

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
        $user = auth()->user();
        if ($user === null || ! $user->isActive()) {
            return collect();
        }

        $pairs = LabSession::query()
            ->where('user_id', $user->getKey())
            ->whereIn('status', ['READY', 'RUNNING', 'COMPLETED'])
            ->get();

        $ids = $pairs->flatMap(fn ($lab) => [
            $lab->attacker_environment_id,
            $lab->victim_environment_id,
        ])->filter()->unique()->all();

        return LabEnvironment::query()
            ->where('user_id', $user->getKey())
            ->whereIn('id', $ids)
            ->orderBy('container_name')
            ->get()
            ->filter(fn (LabEnvironment $environment) => $pairs->contains(fn ($lab) => (
                (int) $lab->attacker_environment_id === (int) $environment->id
                && $lab->attacker_container === $environment->container_name
                && rtrim((string) $lab->attacker_remote, ':') === rtrim((string) $environment->incus_remote, ':')
            ) || (
                (int) $lab->victim_environment_id === (int) $environment->id
                && $lab->victim_container === $environment->container_name
                && rtrim((string) $lab->victim_remote, ':') === rtrim((string) $environment->incus_remote, ':')
            )
            )
            )->values();
    }

    public function isSandboxVictim(LabEnvironment $environment): bool
    {
        $user = auth()->user();

        if ($user === null || ! $user->isActive()
            || (int) $environment->user_id !== (int) $user->getKey()) {
            return false;
        }

        return LabSession::query()
            ->where('user_id', $user->getKey())
            ->where('victim_environment_id', $environment->getKey())
            ->whereIn('status', ['READY', 'RUNNING', 'COMPLETED'])
            ->get()
            ->contains(fn ($lab) => $lab->victim_container === $environment->container_name
                && rtrim((string) $lab->victim_remote, ':') === rtrim((string) $environment->incus_remote, ':')
                && rtrim((string) $environment->incus_remote, ':') === rtrim((string) config('infrastructure.incus_remote', 'lab-env'), ':')
            );
    }

    public function sessionFor(LabEnvironment $environment): ?TerminalSession
    {
        return TerminalSession::query()
            ->where('lab_environment_id', $environment->id)
            ->where('purpose', 'sandbox')
            ->where('user_id', auth()->id())
            ->usable()
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Open, or reattach to, a terminal on an environment.
     */
    // IMNEHNET_DUAL_SANDBOX_V1
    // Bootstrap both independent Sandbox sessions on page initialization.
    // The service retains ownership, lab lifecycle and purpose checks.
    public function bootSandboxTerminals(): void
    {
        $user = auth()->user();

        if ($user === null || ! $user->isActive()) {
            return;
        }

        $environments = $this->environments();

        foreach ($environments as $environment) {
            if (! $environment->runtime_status?->isRunning()) {
                continue;
            }

            if (! $environment->terminal_enabled
                && ! $this->isSandboxVictim($environment)) {
                continue;
            }

            $existing = $this->sessionFor($environment);

            if ($existing !== null
                && app(TerminalService::class)->isConnected($existing)) {
                try {
                    $this->rememberEmbedTicket(
                        app(TerminalService::class),
                        $existing
                    );
                } catch (Throwable) {
                    // An explicit Reconnect remains available.
                }

                continue;
            }

            $this->openTerminal((int) $environment->getKey());
        }
    }

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
            $service = app(TerminalService::class);
            $session = $service->open(auth()->user(), $environment);
            $this->rememberEmbedTicket($service, $session);
            $this->selectedEnvironmentId = $environment->id;
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

    public function closeSession(string $sessionId): void
    {
        $session = TerminalSession::query()->where('user_id', auth()->id())->where('purpose', 'sandbox')->whereKey($sessionId)->first();

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
        $this->forgetEmbedTicket($session);

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

        if (! $environment->terminal_enabled && ! $this->isSandboxVictim($environment)) {
            return $this->state(
                'terminal_disabled',
                'NOT CONFIGURED',
                'neutral',
                'This environment has the terminal switched off.',
            );
        }

        /*
         * runtime_status is nullable, and a row can exist before anything has read
         * it: an environment record is written when the environment is requested,
         * which is before the control plane has observed it running. Calling
         * isRunning() on that null took the whole page down with a fatal.
         *
         * The honest answer is UNKNOWN, which is what RuntimeStatus::Unknown is for.
         * "NOT RUNNING" would be actionable and would be a lie: nothing has said
         * this container is stopped, only that nobody has looked.
         */
        $runtime = $environment->runtime_status;

        if ($runtime === null) {
            return $this->state(
                'runtime_unknown',
                'UNKNOWN',
                'warning',
                'The runtime state of this container has never been read, so whether it is running is not known. Reconcile it before opening a terminal.',
            );
        }

        if (! $runtime->isRunning()) {
            return $this->state(
                'not_running',
                'NOT RUNNING',
                'danger',
                sprintf(
                    'The container is %s. Start it before opening a terminal.',
                    $runtime->label(),
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


    public static function shouldRegisterNavigation(): bool

    {

        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'admin'

            && auth()->user()?->isActive() === true

            && auth()->user()?->isAdmin() === true;

    }



    public static function getNavigationLabel(): string

    {

        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'admin'

            ? 'Terminal'

            : parent::getNavigationLabel();

    }



    public function getTitle(): string

    {

        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'admin'

            ? 'Terminal'

            : parent::getTitle();

    }


}
