<x-filament-panels::page>
    @php
        $environments = $this->environments();
        $browserAccess = (bool) config('infrastructure.terminal.browser_access_enabled');

        $targets = $environments->map(function ($environment) {
            $status = $this->statusFor($environment);
            $session = $this->sessionFor($environment);
            $url = $this->terminalUrl($environment);
            $runtime = $environment->runtime_status;
            $canOpen = $environment->terminal_enabled && $runtime !== null && $runtime->isRunning();

            return compact('environment', 'status', 'session', 'url', 'runtime', 'canOpen');
        });

        $selectedTarget = $targets->first(
            fn ($target) => $target['environment']->id === $this->selectedEnvironmentId,
        ) ?? $targets->first();
    @endphp

    <div class="space-y-5">
        @unless ($browserAccess)
            <div class="alert alert-soft alert-warning" role="alert">
                <span class="shrink-0">{!! \App\Support\Lucide::html('circle-alert') !!}</span>
                <div>
                    <h2 class="font-semibold">Browser access is switched off</h2>
                    <p class="mt-1 text-sm opacity-80">
                        The shell can run on the management plane, but the secured browser relay is disabled.
                        No terminal frame is mounted until browser access is available.
                    </p>
                </div>
            </div>
        @endunless

        <section class="card border border-base-content/10 shadow-sm">
            <div class="card-body gap-4 p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="card-title text-base">Select Target</h2>
                        <p class="mt-1 text-sm text-base-content/55">
                            Choose an environment you are authorized to connect to.
                        </p>
                    </div>

                    @if ($selectedTarget)
                        @php
                            $selectedEnvironment = $selectedTarget['environment'];
                            $selectedSession = $selectedTarget['session'];
                            $selectedUrl = $selectedTarget['url'];
                            $selectedCanOpen = $selectedTarget['canOpen'];
                        @endphp

                        <div class="flex flex-wrap items-center gap-2">
                            @if ($selectedCanOpen && $selectedSession === null)
                                <button
                                    type="button"
                                    wire:click="openTerminal({{ $selectedEnvironment->id }})"
                                    wire:loading.attr="disabled"
                                    class="btn btn-primary btn-sm"
                                >
                                    {!! \App\Support\Lucide::html('plus') !!}
                                    New Session
                                </button>
                            @elseif ($selectedCanOpen)
                                <button
                                    type="button"
                                    wire:click="openTerminal({{ $selectedEnvironment->id }})"
                                    wire:loading.attr="disabled"
                                    class="btn {{ $selectedUrl === null ? 'btn-primary' : 'btn-outline' }} btn-sm"
                                >
                                    {!! \App\Support\Lucide::html('refresh-cw') !!}
                                    Reconnect
                                </button>
                            @endif

                            @if ($selectedSession !== null)
                                <button
                                    type="button"
                                    wire:click="closeSession({{ $selectedSession->id }})"
                                    wire:confirm="Close this terminal session? The process will be signalled."
                                    class="btn btn-outline btn-sm"
                                >
                                    {!! \App\Support\Lucide::html('square') !!}
                                    Close
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                @if ($targets->isEmpty())
                    <div class="rounded-xl border border-dashed border-base-content/15 px-5 py-8 text-center">
                        <div class="mx-auto flex size-11 items-center justify-center rounded-xl bg-base-200 text-base-content/55">
                            {!! \App\Support\Lucide::html('server') !!}
                        </div>
                        <h3 class="mt-3 font-semibold">No terminal targets</h3>
                        <p class="mt-1 text-sm text-base-content/55">
                            No environment is currently assigned to this account.
                        </p>
                    </div>
                @else
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($targets as $target)
                            @php
                                $environment = $target['environment'];
                                $status = $target['status'];
                                $runtime = $target['runtime'];
                                $online = $runtime !== null && $runtime->isRunning();
                                $selected = $selectedTarget['environment']->id === $environment->id;
                            @endphp

                            <button
                                type="button"
                                wire:click="selectEnvironment({{ $environment->id }})"
                                aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                @class([
                                    'group rounded-xl border p-4 text-left transition',
                                    'border-primary/55 bg-primary/5 ring-1 ring-primary/20' => $selected,
                                    'border-base-content/10 bg-base-100 hover:border-base-content/20 hover:bg-base-200/35' => ! $selected,
                                ])
                            >
                                <div class="flex items-center gap-3">
                                    <span @class([
                                        'flex size-5 shrink-0 items-center justify-center rounded-full border transition',
                                        'border-primary bg-primary' => $selected,
                                        'border-base-content/25 bg-base-100' => ! $selected,
                                    ])>
                                        @if ($selected)
                                            <span class="size-2 rounded-full bg-primary-content"></span>
                                        @endif
                                    </span>

                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-base-200 text-base-content/70">
                                        {!! \App\Support\Lucide::html('server') !!}
                                    </span>

                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-base-content">
                                            {{ $environment->container_name }}
                                        </span>
                                        <span class="mt-0.5 block truncate text-xs text-base-content/50">
                                            {{ $environment->tofu_workspace ?: 'Lab environment' }}
                                        </span>
                                    </span>

                                    <span @class([
                                        'badge badge-soft badge-sm shrink-0',
                                        'badge-success' => $online,
                                        'badge-warning' => ! $online && $status['tone'] === 'warning',
                                        'badge-error' => ! $online && $status['tone'] === 'danger',
                                        'badge-neutral' => ! $online && ! in_array($status['tone'], ['warning', 'danger'], true),
                                    ])>
                                        <span class="size-1.5 rounded-full bg-current"></span>
                                        {{ $online ? 'Online' : ($runtime?->label() ?? 'Unknown') }}
                                    </span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        @if ($selectedTarget)
            @php
                $environment = $selectedTarget['environment'];
                $status = $selectedTarget['status'];
                $session = $selectedTarget['session'];
                $url = $selectedTarget['url'];
                $canOpen = $selectedTarget['canOpen'];
                $terminalId = 'terminal-shell-'.$environment->id;
            @endphp

            <section
                wire:key="terminal-target-{{ $environment->id }}"
                class="card overflow-hidden border border-base-content/10 shadow-sm"
                x-data="{
                    toggleFullscreen() {
                        const element = document.getElementById(@js($terminalId));
                        if (! element) return;
                        document.fullscreenElement ? document.exitFullscreen() : element.requestFullscreen?.();
                    }
                }"
            >
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-content/10 px-5 py-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-base-200 text-base-content/70">
                            {!! \App\Support\Lucide::html('server') !!}
                        </span>

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-sm font-semibold text-base-content">
                                    {{ $environment->container_name }}
                                </h2>
                                <span @class([
                                    'badge badge-soft badge-sm',
                                    'badge-success' => $status['tone'] === 'success',
                                    'badge-warning' => $status['tone'] === 'warning',
                                    'badge-error' => $status['tone'] === 'danger',
                                    'badge-neutral' => $status['tone'] === 'neutral',
                                ])>
                                    {{ str($status['label'])->headline() }}
                                </span>
                            </div>
                            <p class="mt-0.5 truncate text-xs text-base-content/50">
                                {{ $status['detail'] }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5">
                        @if ($url !== null)
                            <button
                                type="button"
                                class="btn btn-square btn-ghost btn-sm"
                                title="Fullscreen terminal"
                                aria-label="Fullscreen terminal"
                                @click="toggleFullscreen()"
                            >
                                {!! \App\Support\Lucide::html('box') !!}
                            </button>
                        @endif

                        <details class="dropdown dropdown-end">
                            <summary class="btn btn-square btn-ghost btn-sm list-none" aria-label="Terminal menu">
                                {!! \App\Support\Lucide::html('settings') !!}
                            </summary>
                            <ul class="dropdown-content menu z-20 mt-2 w-48 rounded-box border border-base-content/10 bg-base-100 p-2 shadow-lg">
                                @if ($canOpen)
                                    <li>
                                        <button type="button" wire:click="openTerminal({{ $environment->id }})">
                                            {!! \App\Support\Lucide::html('refresh-cw') !!}
                                            Reconnect
                                        </button>
                                    </li>
                                @endif
                                @if ($session !== null)
                                    <li>
                                        <button
                                            type="button"
                                            wire:click="closeSession({{ $session->id }})"
                                            wire:confirm="Close this terminal session? The process will be signalled."
                                        >
                                            {!! \App\Support\Lucide::html('square') !!}
                                            Close session
                                        </button>
                                    </li>
                                @endif
                            </ul>
                        </details>
                    </div>
                </div>

                <div id="{{ $terminalId }}" class="bg-[#0b1118]">
                    @if ($url !== null)
                        {{-- A tab, not a frame.

                             The gate is HTTP Basic: Apache checks this slot's per-session
                             password file with Require valid-user. An iframe cannot send an
                             Authorization header — there is no attribute for it — so an
                             embedded frame would reach ttyd, be answered with a 401 it cannot
                             answer, and render a blank panel that reads as a broken terminal
                             rather than as a login.

                             The address carries no credential. Apache answers an
                             unauthenticated request with a challenge, so the browser prompts on
                             its own; what the prompt needs is below, because a student has no way
                             to read it out of the database. --}}
                        <div data-lenis-prevent class="min-h-[24rem] bg-[#0b1118] px-6 py-10">
                            <div class="mx-auto max-w-md text-center">
                                <div class="mx-auto flex size-12 items-center justify-center rounded-xl border border-slate-700 bg-slate-900 text-teal-400">
                                    {!! \App\Support\Lucide::html('terminal') !!}
                                </div>
                                <h3 class="mt-4 text-sm font-semibold text-white">
                                    Shell ready on {{ $environment->container_name }}
                                </h3>
                                <p class="mt-2 text-sm leading-6 text-slate-400">
                                    The browser asks for this session's credential once, when it
                                    opens. It is below, and it is not written into the address.
                                </p>
                                <a href="{{ $url }}" target="_blank" rel="noopener"
                                   class="mt-5 inline-flex h-9 items-center gap-2 rounded-lg bg-teal-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-teal-700">
                                    {!! \App\Support\Lucide::html('terminal') !!}
                                    Open Shell
                                </a>
                            </div>

                            @php($studentCredentials = $this->studentCredentials($environment))

                            @if ($studentCredentials !== null)
                                <details class="mx-auto mt-6 max-w-md rounded-lg border border-slate-700 bg-slate-900/60 px-4 py-3 text-left">
                                    <summary class="cursor-pointer list-none text-xs font-semibold text-slate-300 hover:text-white">
                                        Session credential
                                    </summary>
                                    <dl class="mt-3 space-y-2 font-mono text-xs">
                                        <div class="flex items-baseline gap-3">
                                            <dt class="w-20 shrink-0 text-slate-500">username</dt>
                                            <dd class="text-slate-200">{{ $studentCredentials['user'] }}</dd>
                                        </div>
                                        <div class="flex items-baseline gap-3">
                                            <dt class="w-20 shrink-0 text-slate-500">password</dt>
                                            <dd class="select-all break-all text-teal-300">{{ $studentCredentials['password'] }}</dd>
                                        </div>
                                    </dl>
                                    <p class="mt-3 text-[11px] leading-relaxed text-slate-500">
                                        Closing the session revokes this. Closing the tab does not —
                                        the reaper does, once the session's window has passed.
                                    </p>
                                </details>
                            @endif
                        </div>
                    @else
                        <div class="flex min-h-[32rem] items-center justify-center px-6 py-12 text-center text-slate-300">
                            <div class="max-w-md">
                                <div class="mx-auto flex size-12 items-center justify-center rounded-xl border border-slate-700 bg-slate-900 text-teal-400">
                                    {!! \App\Support\Lucide::html('terminal') !!}
                                </div>
                                <h3 class="mt-4 text-sm font-semibold text-white">{{ $status['label'] }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ $status['detail'] }}</p>

                                @if ($canOpen)
                                    <button
                                        type="button"
                                        wire:click="openTerminal({{ $environment->id }})"
                                        wire:loading.attr="disabled"
                                        class="btn btn-primary btn-sm mt-5"
                                    >
                                        {!! \App\Support\Lucide::html($session === null ? 'terminal' : 'refresh-cw') !!}
                                        {{ $session === null ? 'Start Terminal' : 'Reconnect' }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                <div class="grid divide-y divide-base-content/10 border-t border-base-content/10 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                    <div class="flex items-center gap-3 px-5 py-3">
                        <span class="flex size-8 items-center justify-center rounded-full bg-base-200 text-base-content/60">
                            {!! \App\Support\Lucide::html('clock') !!}
                        </span>
                        <div>
                            <p class="text-[11px] text-base-content/45">Session</p>
                            <p class="text-xs font-semibold text-base-content">
                                {{ $session?->created_at?->diffForHumans(short: true) ?? 'Not started' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 px-5 py-3">
                        <span class="flex size-8 items-center justify-center rounded-full bg-base-200 text-base-content/60">
                            {!! \App\Support\Lucide::html('users') !!}
                        </span>
                        <div class="min-w-0">
                            <p class="text-[11px] text-base-content/45">User</p>
                            <p class="truncate text-xs font-semibold text-base-content">
                                {{ auth()->user()?->name ?? 'Unknown' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 px-5 py-3">
                        <span class="flex size-8 items-center justify-center rounded-full bg-base-200 text-base-content/60">
                            {!! \App\Support\Lucide::html('server') !!}
                        </span>
                        <div class="min-w-0">
                            <p class="text-[11px] text-base-content/45">Target</p>
                            <p class="truncate font-mono text-xs font-semibold text-base-content">
                                {{ $environment->container_name }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
