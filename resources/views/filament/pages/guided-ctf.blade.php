{{--
    The guided lab: a course card before the lab exists, the workspace after.

    Follows the supplied design: objectives on the left with their state, the objective in
    play spelled out, real terminals on the right, the target and the session controls
    along the bottom.

    Two things are deliberately not here. The target's service list, because the student
    is meant to find it. And the evidence value of any locked objective, because
    Objective::toPublicArray withholds it and this view is given only what that returns.

    The terminal is a link rather than an embed. The terminal page already owns opening a
    session, resolving the shell uid and rendering the frame; reproducing that here would
    be a second copy of it, and two copies of terminal handling are two places for them to
    disagree about who a session belongs to.
--}}
@php
    use App\Enums\LabSessionStatus;
    use App\Enums\ObjectiveStatus;
    use App\Enums\ValidationType;
    use App\Support\Lucide;

    $course = $this->course();
    $session = $this->session();
    $objectives = $this->objectiveList();
    $progress = $session?->progressCount() ?? ['completed' => 0, 'total' => 0];
    $percent = $progress['total'] > 0 ? (int) round($progress['completed'] / $progress['total'] * 100) : 0;
    $active = $objectives->firstWhere('status', ObjectiveStatus::Active);
    $attacker = $this->attackerEnvironment();
    $victim = $this->victimEnvironment();
    $finished = $session !== null && $session->status === LabSessionStatus::Completed;
    $building = $session !== null && in_array(
        $session->status,
        [LabSessionStatus::Creating, LabSessionStatus::Provisioning, LabSessionStatus::Configuring, LabSessionStatus::Resetting],
        true,
    );
@endphp

<x-filament-panels::page>
    <div class="imnehnet-ctf">

        @if ($course === null)
            {{-- Said plainly. A missing course is a deployment fault, and an empty page
                 would read as "you have no labs" rather than "this build is incomplete". --}}
            <div class="rounded-xl border border-amber-500/40 bg-amber-500/5 p-6 dark:bg-amber-500/10">
                <h2 class="text-sm font-semibold text-amber-900 dark:text-amber-200">No course is available</h2>
                <p class="mt-1 text-sm text-amber-800/90 dark:text-amber-200/80">
                    The guided CTF course has not been loaded. Tell an administrator.
                </p>
            </div>

        @elseif ($session === null)
            {{-- ---------------- course card ---------------- --}}
            <div class="mx-auto max-w-2xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 bg-gradient-to-br from-teal-500/10 to-transparent px-8 py-8 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase tracking-widest text-teal-600 dark:text-teal-400">
                        Guided CTF
                    </p>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                        {{ $course->title }}
                    </h2>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        {{ $course->difficulty }} · {{ $course->objectiveCount() }} Objectives · Practical Lab
                    </p>
                </div>

                <div class="px-8 py-6">
                    <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                        {{ $course->summary }}
                    </p>

                    <div class="mt-6">
                        <x-filament::button size="lg" :icon="Lucide::icon('play')"
                                             wire:click="startLab" wire:loading.attr="disabled">
                            Start Lab
                        </x-filament::button>
                    </div>

                    <p class="mt-4 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                        Starting the lab builds an isolated attacker container for you to work in and a paired
                        victim container to attack, then permits the route between them and nothing else.
                        It takes about two minutes.
                    </p>
                </div>
            </div>

        @else
            {{-- ---------------- workspace ---------------- --}}
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                        Guided CTF &middot; {{ $course->title }}
                    </p>
                    <h2 class="mt-0.5 text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                        {{ $course->title }}
                    </h2>

                    <div class="mt-2 flex items-center gap-3">
                        <div class="h-2 w-full max-w-sm overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                            <div class="h-full rounded-full bg-teal-500 transition-all duration-500"
                                 style="width: {{ $percent }}%"></div>
                        </div>
                        <span class="shrink-0 text-sm font-semibold tabular-nums text-gray-600 dark:text-gray-300">
                            {{ $progress['completed'] }} / {{ $progress['total'] }}
                        </span>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <span @class([
                        'rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide',
                        'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' => $this->canWork($session),
                        'bg-amber-500/15 text-amber-700 dark:text-amber-300' => $building,
                        'bg-rose-500/15 text-rose-700 dark:text-rose-300' => $session->status === LabSessionStatus::Failed,
                        'bg-gray-500/15 text-gray-700 dark:text-gray-300' => in_array($session->status, [LabSessionStatus::Stopped, LabSessionStatus::Completed], true),
                    ])>
                        {{ $this->headline($session) }}
                    </span>

                    @if ($this->canWork($session) && $session->status->acceptsOperation())
                        <x-filament::button color="gray" size="sm" :icon="Lucide::icon('rotate-ccw')"
                                             wire:click="resetLab" wire:loading.attr="disabled"
                                             wire:confirm="This will recreate your lab environment to the initial state. Your objective progress is kept.">
                            Reset Lab
                        </x-filament::button>
                    @endif

                    <x-filament::button color="danger" size="sm" :icon="Lucide::icon('square')"
                                         wire:click="finishLab" wire:loading.attr="disabled"
                                         wire:confirm="Finish this lab? Your progress is kept and the containers are released.">
                        Stop Lab
                    </x-filament::button>
                </div>
            </div>

            {{-- ---------------- provisioning ---------------- --}}
            @if ($building)
                <div class="rounded-xl border border-amber-500/40 bg-amber-500/5 p-5 dark:bg-amber-500/10">
                    <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                        {{ $this->headline($session) }}
                    </h3>
                    <p class="mt-1 text-sm text-amber-800/90 dark:text-amber-200/80">
                        Please wait while we prepare your isolated environment.
                    </p>

                    <ol class="mt-4 space-y-2">
                        @foreach ($this->stages($session) as $stage)
                            <li class="flex items-center gap-2.5 text-sm">
                                <span @class([
                                    'flex size-4 shrink-0 items-center justify-center rounded-full',
                                    'bg-teal-500 text-white' => $stage['state'] === 'done',
                                    'border-2 border-amber-500' => $stage['state'] === 'current',
                                    'border border-gray-400 dark:border-gray-600' => $stage['state'] === 'pending',
                                ])>
                                    @if ($stage['state'] === 'done')
                                        {!! Lucide::icon('circle-check') !!}
                                    @endif
                                </span>

                                <span @class([
                                    'font-medium',
                                    'text-gray-500 line-through dark:text-gray-500' => $stage['state'] === 'done',
                                    'text-amber-900 dark:text-amber-100' => $stage['state'] === 'current',
                                    'text-gray-500 dark:text-gray-500' => $stage['state'] === 'pending',
                                ])>{{ $stage['label'] }}</span>

                                <span class="ml-auto text-xs uppercase tracking-wide text-gray-500 dark:text-gray-500">
                                    {{ $stage['state'] }}
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </div>

            @elseif ($session->status === LabSessionStatus::Failed)
                <div class="rounded-xl border border-rose-500/40 bg-rose-500/5 p-5 dark:bg-rose-500/10">
                    <h3 class="text-sm font-semibold text-rose-900 dark:text-rose-200">
                        Lab provisioning failed
                    </h3>
                    <p class="mt-1 text-sm text-rose-800/90 dark:text-rose-200/80">
                        An administrator has the reason in the activity log.
                    </p>
                    <div class="mt-3">
                        <x-filament::button size="sm" color="danger" wire:click="startLab">
                            Retry
                        </x-filament::button>
                    </div>
                </div>
            @endif

            {{-- ---------------- results ---------------- --}}
            @if ($finished)
                <div class="rounded-xl border border-emerald-500/40 bg-emerald-500/5 p-6 text-center dark:bg-emerald-500/10">
                    <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-emerald-500/15">
                        {!! Lucide::icon('circle-check') !!}
                    </div>

                    <h3 class="mt-3 text-lg font-bold text-gray-950 dark:text-white">Course Completed!</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        You have successfully completed {{ $course->title }}.
                    </p>

                    <dl class="mx-auto mt-5 grid max-w-md gap-2 text-left text-sm sm:grid-cols-2">
                        <div class="flex justify-between gap-3 rounded-lg bg-white/60 px-3 py-2 dark:bg-white/5">
                            <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                            <dd class="font-semibold text-emerald-600 dark:text-emerald-400">COMPLETED</dd>
                        </div>
                        <div class="flex justify-between gap-3 rounded-lg bg-white/60 px-3 py-2 dark:bg-white/5">
                            <dt class="text-gray-500 dark:text-gray-400">Started at</dt>
                            <dd class="font-semibold">{{ $session->started_at?->format('d M Y, H:i') }}</dd>
                        </div>
                        <div class="flex justify-between gap-3 rounded-lg bg-white/60 px-3 py-2 dark:bg-white/5">
                            <dt class="text-gray-500 dark:text-gray-400">Completed at</dt>
                            <dd class="font-semibold">{{ $session->completed_at?->format('d M Y, H:i') }}</dd>
                        </div>
                        <div class="flex justify-between gap-3 rounded-lg bg-white/60 px-3 py-2 dark:bg-white/5">
                            <dt class="text-gray-500 dark:text-gray-400">Duration</dt>
                            <dd class="font-mono font-semibold">{{ $this->duration($session) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-5">
                        <x-filament::button wire:click="finishLab">Finish Lab</x-filament::button>
                    </div>
                </div>
            @endif

            <div class="grid gap-4 lg:grid-cols-5">

                {{-- ---------------- objectives ---------------- --}}
                <section class="lg:col-span-2 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Objectives
                    </h3>

                    <ol class="mt-4 space-y-2">
                        @foreach ($objectives as $row)
                            @php
                                $payload = $row['payload'];
                                $done = $row['status'] === ObjectiveStatus::Completed;
                                $current = $row['status'] === ObjectiveStatus::Active;
                                $hint = $this->hintFor($payload['hint']);
                            @endphp

                            <li @class([
                                'rounded-lg border p-3',
                                'border-teal-500/40 bg-teal-500/5' => $current,
                                'border-gray-200 dark:border-gray-800' => ! $current,
                                'opacity-70' => $row['status'] === ObjectiveStatus::Locked,
                            ])>
                                <div class="flex items-start gap-2">
                                    <span class="mt-0.5 shrink-0 {{ $done ? 'text-teal-600 dark:text-teal-400' : ($current ? 'text-teal-500' : 'text-gray-400') }}">
                                        @if ($done)
                                            {!! Lucide::icon('circle-check') !!}
                                        @elseif ($current)
                                            <span class="block size-2.5 rounded-full bg-teal-500"></span>
                                        @else
                                            {!! Lucide::icon('lock') !!}
                                        @endif
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-gray-950 dark:text-white">
                                            {{ $row['objective']->numberedTitle() }}
                                        </p>

                                        {{-- A locked objective shows its title and nothing
                                             else. Not a blank, and not a teaser: the model
                                             withholds the description because objective
                                             four's would otherwise hand over the hidden
                                             directory. --}}
                                        @if ($current && $payload['description'])
                                            <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                                                {{ $payload['description'] }}
                                            </p>

                                            @if ($hint)
                                                {{-- A command the student can run. This is a
                                                     guided lab: the page says how to look, and
                                                     the validator still only accepts what the
                                                     victim actually recorded. --}}
                                                <div class="mt-3 rounded-md bg-gray-950 px-3 py-2">
                                                    <code class="block break-all font-mono text-xs text-teal-300">{{ $hint }}</code>
                                                </div>
                                                <button type="button" wire:click="$set('clipboard', {{ json_encode($hint) }})"
                                                        onclick="navigator.clipboard.writeText(this.previousElementSibling.querySelector('code').textContent)"
                                                        class="mt-1.5 text-xs font-medium text-teal-600 hover:underline dark:text-teal-400">
                                                    Copy
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ol>

                    @if ($active && $active['objective']->validation_type === ValidationType::Flag)
                        <div class="mt-5 border-t border-gray-200 pt-4 dark:border-gray-800">
                            <label for="ctf-flag" class="block text-sm font-semibold text-gray-900 dark:text-gray-100">
                                Final Flag
                            </label>

                            <input id="ctf-flag" type="text" wire:model="flag"
                                   placeholder="FLAG{...}"
                                   autocomplete="off" spellcheck="false"
                                   class="mt-2 w-full rounded-lg border-gray-300 font-mono text-sm dark:border-gray-700 dark:bg-gray-950" />

                            <div class="mt-3">
                                <x-filament::button wire:click="submitFlag" wire:loading.attr="disabled">
                                    Submit Flag
                                </x-filament::button>
                            </div>
                        </div>

                    @elseif ($this->canWork($session) && ! $finished)
                        {{-- The student asks when they want to know. Reading the victim's
                             logs on a timer would mean doing it constantly, for every open
                             page, for a verdict that rarely changes. --}}
                        <div class="mt-5 border-t border-gray-200 pt-4 dark:border-gray-800">
                            <x-filament::button color="gray" size="sm" :icon="Lucide::icon('circle-dot')"
                                                 wire:click="checkProgress" wire:loading.attr="disabled">
                                Check my progress
                            </x-filament::button>
                        </div>
                    @endif
                </section>

                {{-- ---------------- terminals ---------------- --}}
                <section class="lg:col-span-3 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Real Terminal
                    </h3>

                    @if ($this->canWork($session) && $attacker && $victim)
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                            Your shell runs inside the <strong>attacker</strong> container as
                            <code class="font-mono">{{ config('infrastructure.terminal.shell_user') }}</code>,
                            not in the browser. What you type executes there and the real output
                            comes back. The target's own files are shown read-only beside it.
                        </p>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                <p class="text-xs font-semibold uppercase tracking-wide text-teal-600 dark:text-teal-400">
                                    Attacker Terminal
                                </p>
                                <p class="mt-1 truncate font-mono text-xs text-gray-500 dark:text-gray-400">
                                    {{ $attacker->container_name }}
                                </p>
                            </div>

                            <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Victim Terminal (Read-Only)
                                </p>
                                <p class="mt-1 truncate font-mono text-xs text-gray-500 dark:text-gray-400">
                                    {{ $victim->container_name }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 flex gap-2">
                            <x-filament::button :icon="Lucide::icon('terminal')"
                                                 tag="a" href="{{ \App\Filament\Pages\Terminal::getUrl(panel: 'student') }}">
                                Open terminal
                            </x-filament::button>
                        </div>

                    @else
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                            The terminal opens once your lab is ready.
                        </p>
                    @endif
                </section>
            </div>

            {{-- ---------------- footer ---------------- --}}
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200
                        bg-white px-5 py-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center gap-2 text-sm">
                    <span class="text-gray-500 dark:text-gray-400">Target</span>

                    @if ($session->targetIsDisclosable())
                        <code class="font-mono font-semibold text-gray-950 dark:text-white">{{ $session->victim_ip }}</code>
                    @else
                        {{-- Not "hidden", and not an empty box: the target genuinely
                             cannot be disclosed yet, and saying so is more useful than a
                             blank the student has to interpret. --}}
                        <span class="text-gray-500 dark:text-gray-400">withheld until your lab is ready</span>
                    @endif
                </div>

                <x-filament::button color="gray" size="sm" :icon="Lucide::icon('refresh-cw')"
                                     wire:click="$refresh" wire:loading.attr="disabled">
                    Refresh
                </x-filament::button>
            </div>

            {{-- ---------------- history ---------------- --}}
            @if ($this->history()->count() > 1)
                <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Lab History
                    </h3>

                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="py-2 pr-4 font-medium">Course</th>
                                    <th class="py-2 pr-4 font-medium">Status</th>
                                    <th class="py-2 pr-4 font-medium">Started</th>
                                    <th class="py-2 font-medium">Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->history() as $row)
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td class="py-2 pr-4">{{ $course->title }}</td>
                                        <td class="py-2 pr-4">
                                            <span @class([
                                                'rounded-full px-2 py-0.5 text-xs font-semibold',
                                                'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' => $row->status === LabSessionStatus::Completed,
                                                'bg-amber-500/15 text-amber-700 dark:text-amber-300' => in_array($row->status, [LabSessionStatus::Provisioning, LabSessionStatus::Configuring, LabSessionStatus::Resetting], true),
                                                'bg-rose-500/15 text-rose-700 dark:text-rose-300' => $row->status === LabSessionStatus::Failed,
                                                'bg-gray-500/15 text-gray-700 dark:text-gray-300' => in_array($row->status, [LabSessionStatus::Stopped], true),
                                            ])>{{ $row->status->value }}</span>
                                        </td>
                                        <td class="py-2 pr-4 text-gray-600 dark:text-gray-300">
                                            {{ $row->started_at?->format('d M Y, H:i') ?? '—' }}
                                        </td>
                                        <td class="py-2 tabular-nums text-gray-600 dark:text-gray-300">
                                            {{ $row->progressCount()['completed'] }} / {{ $row->progressCount()['total'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        @endif
    </div>
</x-filament-panels::page>