{{--
    The guided lab workspace.

    Two states, one page. Before the lab exists it is a course card with one button,
    which is all the specification asks for given there is a single course. After it
    exists it is the workspace: objectives on the left, the shell on the right, the
    target and the session controls along the bottom.

    Two things are deliberately not here. The target's service list, because the student
    is meant to find it. And the evidence value of any objective, because
    Objective::toPublicArray withholds it while the objective is locked and this view is
    given only what that method returns.

    The shell is a link rather than an embed. The terminal page already owns opening a
    session, resolving the shell uid and rendering the frame; reproducing that here
    would be a second copy of it, and two copies of terminal handling are two places
    for them to disagree about who a session belongs to.
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
            {{-- ---------------- course card (§2) ---------------- --}}
            <div class="mx-auto max-w-xl rounded-2xl border border-gray-200 bg-white p-8 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                    {{ $course->title }}
                </h2>

                <dl class="mt-4 flex gap-6 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Difficulty</dt>
                        <dd class="font-semibold text-gray-900 dark:text-gray-100">{{ $course->difficulty }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Objectives</dt>
                        <dd class="font-semibold text-gray-900 dark:text-gray-100">{{ $course->objectiveCount() }}</dd>
                    </div>
                </dl>

                <p class="mt-4 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                    {{ $course->summary }}
                </p>

                <div class="mt-6">
                    <x-filament::button size="lg" icon="lucide-play"
                                         wire:click="startLab" wire:loading.attr="disabled">
                        Start lab
                    </x-filament::button>
                </div>

                <p class="mt-4 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                    Starting the lab builds two containers — an attacker for you to work in and a target to
                    attack — and permits the route between them. It takes about a minute.
                </p>
            </div>

        @else
            {{-- ---------------- workspace (§3) ---------------- --}}
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                        {{ $course->title }}
                    </h2>

                    <div class="mt-2 flex items-center gap-3">
                        <div class="h-2 w-full max-w-xs overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                            <div class="h-full rounded-full bg-teal-500 transition-all"
                                 style="width: {{ $percent }}%"></div>
                        </div>
                        <span class="shrink-0 text-sm font-semibold tabular-nums text-gray-600 dark:text-gray-300">
                            {{ $progress['completed'] }} / {{ $progress['total'] }}
                        </span>
                    </div>
                </div>

                <span @class([
                    'shrink-0 rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide',
                    'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' => $session->status->allowsTerminal(),
                    'bg-amber-500/15 text-amber-700 dark:text-amber-300' => in_array($session->status, [LabSessionStatus::Provisioning, LabSessionStatus::Configuring, LabSessionStatus::Resetting], true),
                    'bg-rose-500/15 text-rose-700 dark:text-rose-300' => $session->status === LabSessionStatus::Failed,
                    'bg-gray-500/15 text-gray-700 dark:text-gray-300' => in_array($session->status, [LabSessionStatus::Stopped, LabSessionStatus::Completed], true),
                ])>
                    {{ $this->headline($session) }}
                </span>
            </div>

            @unless ($this->canWork($session))
                {{-- Said once, and said accurately. Provisioning is not an error and a
                     student should not have to guess which it is. --}}
                <div class="rounded-xl border border-amber-500/40 bg-amber-500/5 p-4 dark:bg-amber-500/10">
                    <p class="text-sm text-amber-900 dark:text-amber-200">
                        {{ match ($session->status) {
                            LabSessionStatus::Failed => 'Your lab could not be built. An administrator has the reason in the activity log.',
                            LabSessionStatus::Stopped => 'This lab is finished. Start a new one to work through the course again.',
                            LabSessionStatus::Resetting => 'Your lab is being returned to its starting state.',
                            default => 'Your lab is being built. This page will show the objectives and the terminal when it is ready.',
                        } }}
                    </p>

                    @if ($session->status === LabSessionStatus::Failed)
                        <div class="mt-3">
                            <x-filament::button size="sm" color="danger" wire:click="startLab">
                                Try again
                            </x-filament::button>
                        </div>
                    @endif
                </div>
            @endunless

            <div class="grid gap-4 lg:grid-cols-2">

                {{-- ---------------- objectives ---------------- --}}
                <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Objectives
                    </h3>

                    <ol class="mt-4 space-y-3">
                        @foreach ($objectives as $row)
                            @php
                                $payload = $row['payload'];
                                $done = $row['status'] === ObjectiveStatus::Completed;
                                $current = $row['status'] === ObjectiveStatus::Active;
                            @endphp

                            <li @class([
                                'rounded-lg border p-3',
                                'border-teal-500/40 bg-teal-500/5' => $current,
                                'border-gray-200 dark:border-gray-800' => ! $current,
                                'opacity-60' => $row['status'] === ObjectiveStatus::Locked,
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

                                    <div class="min-w-0">
                                        <p @class([
                                            'text-sm font-semibold',
                                            'text-gray-950 dark:text-white' => ! $row['status'] === ObjectiveStatus::Locked,
                                            'text-gray-500 dark:text-gray-400' => $row['status'] === ObjectiveStatus::Locked,
                                        ])>
                                            {{ $row['objective']->numberedTitle() }}
                                        </p>

                                        {{-- A locked objective shows its title and nothing
                                             else. Not a blank, and not a teaser: the
                                             description is withheld by the model, because
                                             objective four's description would otherwise
                                             hand over the hidden directory. --}}
                                        @if ($payload['description'])
                                            <p class="mt-1.5 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                                                {{ $payload['description'] }}
                                            </p>

                                            @if ($payload['hint'])
                                                {{-- A hint, never an instruction. Naming the
                                                     command would make this a tutorial and
                                                     would stop the objective testing
                                                     anything. --}}
                                                <p class="mt-2 rounded-md bg-gray-100 px-2.5 py-1.5 text-xs
                                                          text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                                    <span class="font-semibold uppercase tracking-wide">Hint</span>
                                                    {{ $payload['hint'] }}
                                                </p>
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
                                Submit the flag
                            </label>

                            <input id="ctf-flag" type="text" wire:model="flag"
                                   placeholder="the flag you retrieved"
                                   autocomplete="off" spellcheck="false"
                                   class="mt-2 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950" />

                            <div class="mt-3">
                                <x-filament::button wire:click="submitFlag" wire:loading.attr="disabled">
                                    Submit flag
                                </x-filament::button>
                            </div>
                        </div>

                    @elseif ($this->canWork($session))
                        {{-- The student asks when they want to know. Reading the victim's
                             logs on a timer would mean doing it constantly, for every
                             open page, for a verdict that rarely changes. --}}
                        <div class="mt-5 border-t border-gray-200 pt-4 dark:border-gray-800">
                            <x-filament::button color="gray" size="sm" icon="lucide-scan-search"
                                                 wire:click="checkProgress" wire:loading.attr="disabled">
                                Check my progress
                            </x-filament::button>
                        </div>
                    @endif
                </section>

                {{-- ---------------- terminal ---------------- --}}
                <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Real terminal
                    </h3>

                    @if ($this->canWork($session) && $attacker)
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                            Your shell runs inside the attacker container as
                            <code class="font-mono">{{ config('infrastructure.terminal.shell_user') }}</code>,
                            not in the browser. What you type executes there and the real
                            output comes back.
                        </p>

                        <div class="mt-4">
                            <x-filament::button tag="a" href="{{ \App\Filament\Pages\Terminal::getUrl(panel: 'student') }}"
                                                 icon="lucide-terminal">
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

                <div class="flex gap-2">
                    <x-filament::button color="gray" size="sm" icon="lucide-rotate-cw"
                                         wire:click="$refresh" wire:loading.attr="disabled">
                        Refresh
                    </x-filament::button>

                    <x-filament::button color="danger" size="sm" icon="lucide-square"
                                         wire:click="finishLab" wire:loading.attr="disabled"
                                         wire:confirm="Finish this lab? Your progress is kept and the containers are released.">
                        Stop lab
                    </x-filament::button>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>