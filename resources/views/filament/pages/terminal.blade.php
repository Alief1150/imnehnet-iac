<x-filament-panels::page>
    @php
        $environments = $this->environments();
        $browserAccess = (bool) config('infrastructure.terminal.browser_access_enabled');
    @endphp

    <div class="space-y-6">
        {{-- Stated once at the top rather than discovered per row. A reader who
             sees every environment reporting NOT REACHABLE deserves to know
             whether that is a deployment setting or something they can act on. --}}
        @unless ($browserAccess)
            <section class="rounded-xl border border-amber-500/40 bg-amber-500/5 p-4 dark:bg-amber-500/10">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 shrink-0 text-amber-600 dark:text-amber-400">
                        {!! \App\Support\Lucide::icon('circle-alert') !!}
                    </span>
                    <div class="text-sm">
                        <h2 class="font-semibold text-amber-900 dark:text-amber-200">Browser access is switched off</h2>
                        <p class="mt-1 text-amber-800/90 dark:text-amber-200/80">
                            A session can be started and the shell really does run on the management plane, but the
                            portal will not hand the browser an address for it. This is the deployment setting
                            <code class="font-mono">infrastructure.terminal.browser_access_enabled</code>, and it is
                            off because serving a shell over plain HTTP would send every keystroke across the network
                            in the clear.
                        </p>
                    </div>
                </div>
            </section>
        @endunless

        <section>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Environments
            </h2>

            @if ($environments->isEmpty())
                <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    There is no environment to open a terminal on. Environments are provisioned from the
                    Environments section, and the container has to exist before it has a shell.
                </div>
            @else
                <div class="grid gap-3 lg:grid-cols-2">
                    @foreach ($environments as $environment)
                        @php
                            $status = $this->statusFor($environment);
                            $session = $this->sessionFor($environment);
                            $url = $this->terminalUrl($environment);
                        @endphp

                        <article @class([
                            'rounded-xl border bg-white p-4 shadow-sm transition dark:bg-gray-900',
                            'border-teal-500/40' => $status['code'] === 'connected',
                            'border-amber-500/40' => $status['tone'] === 'warning',
                            'border-rose-500/40' => $status['tone'] === 'danger',
                            'border-gray-200 dark:border-gray-800' => $status['tone'] === 'neutral',
                        ])>
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate font-mono text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ $environment->container_name }}
                                    </h3>
                                    <p class="mt-0.5 truncate text-[11px] text-gray-500 dark:text-gray-400">
                                        {{ $environment->tofu_workspace }}
                                    </p>
                                </div>
                                <span @class([
                                    'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                    'bg-teal-500/15 text-teal-700 dark:text-teal-300' => $status['tone'] === 'success',
                                    'bg-amber-500/15 text-amber-700 dark:text-amber-300' => $status['tone'] === 'warning',
                                    'bg-rose-500/15 text-rose-700 dark:text-rose-300' => $status['tone'] === 'danger',
                                    'bg-gray-500/15 text-gray-600 dark:text-gray-300' => $status['tone'] === 'neutral',
                                ])>{{ $status['label'] }}</span>
                            </div>

                            <p class="mt-2 text-xs leading-relaxed text-gray-600 dark:text-gray-300">
                                {{ $status['detail'] }}
                            </p>

                            {{-- The frame only exists when something is genuinely listening behind it.
                                 A permanently mounted iframe would look connected while showing nothing,
                                 which is the specific dishonesty this page is meant to avoid. --}}
                            @if ($url !== null)
                                <div class="mt-3 overflow-hidden rounded-lg border border-gray-800 bg-black"
                                     data-lenis-prevent>
                                    <iframe
                                        src="{{ $url }}"
                                        class="h-[26rem] w-full border-0"
                                        title="Terminal for {{ $environment->container_name }}"
                                        allow="clipboard-write"
                                    ></iframe>
                                </div>
                            @endif

                            <div class="mt-3 flex flex-wrap gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
                                @php
                                    $canOpen = $environment->terminal_enabled
                                        && $environment->runtime_status->isRunning();
                                @endphp

                                @if ($canOpen && $session === null)
                                    <button
                                        type="button"
                                        wire:click="openTerminal({{ $environment->id }})"
                                        wire:loading.attr="disabled"
                                        class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500 disabled:opacity-50"
                                    >Open terminal</button>
                                @elseif ($canOpen && $url === null)
                                    <button
                                        type="button"
                                        wire:click="openTerminal({{ $environment->id }})"
                                        wire:loading.attr="disabled"
                                        class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500 disabled:opacity-50"
                                    >Reconnect</button>
                                @endif

                                @if ($session !== null)
                                    <button
                                        type="button"
                                        wire:click="closeSession({{ $session->id }})"
                                        wire:confirm="Close this terminal session? The process will be signalled."
                                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                    >Close session</button>
                                @endif

                                @unless ($canOpen)
                                    <span class="text-[11px] text-gray-500 dark:text-gray-400">
                                        {{ $environment->terminal_enabled ? 'The container is not running.' : 'The terminal is not enabled for this environment.' }}
                                    </span>
                                @endunless
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>