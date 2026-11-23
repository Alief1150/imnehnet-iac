@include("filament.pages.partials.security-alerts-style")


@if ($showAlerts)

    @php($browser = $this->alertBrowser())



    <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/75 p-4">

        <div class="absolute inset-0" wire:click="closeAlerts"></div>



        <section role="dialog" aria-modal="true" aria-label="All Security Alerts" data-im-alert-modal

            class="relative flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white text-gray-900 shadow-2xl dark:bg-gray-900 dark:text-white">



            <header class="flex items-center justify-between border-b p-4 dark:border-gray-700">

                <div>

                    <h2 class="text-lg font-bold">All Security Alerts</h2>

                    <p class="text-xs text-gray-500">

                        Last 24 hours · {{ $browser['retained'] }} retained · max 500 alerts

                    </p>

                </div>

                <button type="button" wire:click="closeAlerts" class="btn btn-sm btn-ghost">✕ Close</button>

            </header>



            <div class="flex flex-wrap gap-3 p-4">

                <input type="search" wire:model.live.debounce.300ms="alertsSearch"

                    placeholder="Search agent, event, rule, IP"

                    class="input input-bordered min-w-[200px] flex-1">



                <select wire:model.live="alertsSeverity" class="select select-bordered">

                    <option value="all">All severities</option>

                    <option value="low">Low</option>

                    <option value="medium">Medium</option>

                    <option value="high">High</option>

                </select>

            </div>



            <div class="im-alerts-scroll min-h-0 flex-1 overflow-auto px-4">

                <table class="table w-full min-w-[680px] text-sm">

                    <thead>

                        <tr>

                            <th>Time</th>

                            <th>Source</th>

                            <th>Event</th>

                            <th>Severity</th>

                            <th>Rule</th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse ($browser['rows'] as $alert)

                            <tr>

                                <td class="whitespace-nowrap text-xs">

                                    {{ \Illuminate\Support\Carbon::createFromTimestamp($alert['ts'])->format('d M H:i:s') }}

                                </td>

                                <td class="font-mono text-xs">{{ $alert['agent'] }}</td>

                                <td class="text-xs">{{ $alert['description'] }}</td>

                                <td class="text-xs font-semibold uppercase">{{ $alert['severity'] }}</td>

                                <td class="font-mono text-xs">{{ $alert['rule'] }}</td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="5" class="py-6 text-center text-gray-500">

                                    No matching alerts in the last 24 hours.

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>



            <footer class="flex items-center justify-between gap-3 border-t p-4 text-xs dark:border-gray-700">

                <span>{{ $browser['total'] }} matching alerts</span>

                <div class="flex items-center gap-2">

                    <button type="button" class="btn btn-sm btn-ghost"

                        wire:click="setAlertsPage({{ $browser['page'] - 1 }})"

                        @disabled($browser['page'] <= 1)>Previous</button>



                    <span>{{ $browser['page'] }} / {{ $browser['pages'] }}</span>



                    <button type="button" class="btn btn-sm btn-ghost"

                        wire:click="setAlertsPage({{ $browser['page'] + 1 }})"

                        @disabled($browser['page'] >= $browser['pages'])>Next</button>

                </div>

            </footer>

        </section>

    </div>

@endif
