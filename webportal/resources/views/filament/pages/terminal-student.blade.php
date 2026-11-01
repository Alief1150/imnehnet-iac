<x-filament-panels::page>
    @php
        $environments = $this->environments();

        $targets = $environments->map(function ($environment) {
            return [
                'environment' => $environment,
                'status' => $this->statusFor($environment),
                'session' => $this->sessionFor($environment),
                'url' => $this->terminalUrl($environment),
                'canOpen' => (
                    $environment->terminal_enabled ||
                    $this->isSandboxVictim($environment)
                ) && ($environment->runtime_status?->isRunning() ?? false),
            ];
        });

        $targets = $targets->sortBy(function ($target) {
            return str_starts_with(
                $target['environment']->container_name,
                'attacker-'
            ) ? 0 : 1;
        })->values();
    @endphp

    <div class="space-y-5" wire:init="bootSandboxTerminals">

        <section class="card border border-base-content/10">
            <div class="card-body gap-2 p-5">
                <h2 class="card-title text-base">
                    Sandbox — Dual Terminal
                </h2>

                <p class="text-sm text-base-content/60">
                    Access your attacker and victim environments
                    independently. Both terminals use the Sandbox
                    account and do not constitute Guided CTF sessions.
                </p>
            </div>
        </section>

        @if ($targets->isEmpty())
            <div class="rounded-xl border border-dashed p-8 text-center">
                <h3 class="font-semibold">No lab available</h3>
                <p class="mt-2 text-sm opacity-60">
                    Start your assigned lab first.
                </p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                @foreach ($targets as $target)
                    @php
                        $environment = $target['environment'];
                        $status = $target['status'];
                        $session = $target['session'];
                        $url = $target['url'];
                        $canOpen = $target['canOpen'];

                        $isAttacker = str_starts_with(
                            $environment->container_name,
                            'attacker-'
                        );

                        $role = $isAttacker ? 'Attacker' : 'Victim';
                        $terminalId = 'sandbox-terminal-'.$environment->id;
                    @endphp

                    <section
                        wire:key="sandbox-panel-{{ $environment->id }}"
                        class="card min-w-0 overflow-hidden border border-base-content/10"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-content/10 p-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="font-semibold">
                                        {{ $role }} Terminal
                                    </h2>

                                    <span @class([
                                        'badge badge-sm',
                                        'badge-success' => $status['tone'] === 'success',
                                        'badge-warning' => $status['tone'] === 'warning',
                                        'badge-error' => $status['tone'] === 'danger',
                                        'badge-neutral' => $status['tone'] === 'neutral',
                                    ])>
                                        {{ $status['label'] }}
                                    </span>
                                </div>

                                <p class="mt-1 truncate font-mono text-xs opacity-60">
                                    {{ $environment->container_name }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                @if ($canOpen)
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm"
                                        wire:click="openTerminal({{ $environment->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="openTerminal"
                                    >
                                        {!! \App\Support\Lucide::html('refresh-cw') !!}
                                        {{ $session ? 'Reconnect' : 'Start' }}
                                    </button>
                                @endif

                                @if ($session !== null)
                                    <button
                                        type="button"
                                        class="btn btn-outline btn-sm"
                                        wire:click="closeSession('{{ $session->id }}')"
                                        wire:confirm="Close this terminal session?"
                                        wire:loading.attr="disabled"
                                    >
                                        {!! \App\Support\Lucide::html('square') !!}
                                        Close
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div
                            id="{{ $terminalId }}"
                            class="min-w-0 bg-[#0b1118]"
                        >
                            @if ($url !== null && $session !== null)
                                <div
                                    wire:key="sandbox-frame-{{ $environment->id }}-{{ $session->id }}"
                                    wire:ignore
                                    data-lenis-prevent
                                >
                                    <iframe
                                        src="{{ $url }}"
                                        title="{{ $role }} Sandbox terminal"
                                        style="display:block;width:100%;height:520px;border:0;background:#0b1118"
                                        allow="clipboard-read; clipboard-write"
                                    ></iframe>
                                </div>
                            @else
                                <div class="flex h-[520px] items-center justify-center px-5 text-center text-slate-300">
                                    <div class="max-w-sm">
                                        <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-xl bg-slate-900 text-teal-400">
                                            {!! \App\Support\Lucide::html('terminal') !!}
                                        </div>

                                        <h3 class="font-semibold text-white">
                                            {{ $status['label'] }}
                                        </h3>

                                        <p class="mt-2 text-sm text-slate-400">
                                            {{ $status['detail'] }}
                                        </p>

                                        @if ($canOpen)
                                            <button
                                                type="button"
                                                wire:click="openTerminal({{ $environment->id }})"
                                                class="btn btn-primary btn-sm mt-4"
                                            >
                                                Open Terminal
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="border-t border-base-content/10 px-4 py-3">
                            <p class="text-xs text-base-content/60">
                                Account: <code>student</code>
                                @if ($session !== null)
                                    · Expires:
                                    {{ $session->expires_at?->format('H:i:s') ?? '-' }}
                                @endif
                            </p>
                        </div>
                    </section>
                @endforeach
            </div>
        @endif

        <p class="text-xs text-base-content/55">
            Reconnect and Close affect only the selected terminal.
            Stop, Reset and Delete Lab are managed through the lab lifecycle controls.
        </p>
    </div>
</x-filament-panels::page>
