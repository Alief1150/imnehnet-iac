<div class="w-full max-w-md">
    <div class="text-center mb-7">
        <p class="text-[11px] font-semibold tracking-[0.35em] text-teal-500/80 uppercase">
            Cybersecurity Infrastructure Control Plane
        </p>
        <h1 class="mt-3 text-2xl font-semibold text-white tracking-tight">Register a student account</h1>
        <p class="mt-2 text-sm leading-relaxed text-gray-400">
            For practising labs. You will be able to create and open your own lab
            environments as soon as you finish.
        </p>
    </div>

    <div class="rounded-2xl bg-[#16202e]/90 border border-white/[0.07] shadow-2xl shadow-black/40 overflow-hidden">
        <div class="h-px bg-gradient-to-r from-transparent via-teal-500/50 to-transparent"></div>

        <form wire:submit="register" class="p-7 space-y-5">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-300">Name</label>
                <input
                    id="name"
                    type="text"
                    wire:model="name"
                    autocomplete="name"
                    required
                    autofocus
                    class="mt-1.5 block w-full rounded-lg border border-white/10 bg-gray-950/60 px-3 py-2.5 text-sm text-gray-100 placeholder-gray-600 transition focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                    placeholder="Your name"
                >
                @error('name')
                    <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-300">Email address</label>
                <input
                    id="email"
                    type="email"
                    wire:model="email"
                    autocomplete="email"
                    required
                    class="mt-1.5 block w-full rounded-lg border border-white/10 bg-gray-950/60 px-3 py-2.5 text-sm text-gray-100 placeholder-gray-600 transition focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                    placeholder="you@example.com"
                >
                @error('email')
                    <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- One Alpine scope for the whole password area: the value drives the
                 checklist and the toggle, so neither needs a round trip to check
                 what has been typed. --}}
            <div x-data="{ value: '', show: false }">
                <label for="password" class="block text-sm font-medium text-gray-300">Password</label>
                <div class="relative mt-1.5">
                    <input
                        id="password"
                        :type="show ? 'text' : 'password'"
                        wire:model="password"
                        @input="value = $event.target.value"
                        autocomplete="new-password"
                        required
                        class="block w-full rounded-lg border border-white/10 bg-gray-950/60 py-2.5 pr-11 pl-3 text-sm text-gray-100 placeholder-gray-600 transition focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                        placeholder="Choose a password"
                    >
                    <button
                        type="button"
                        @click="show = !show"
                        class="absolute inset-y-0 right-0 flex items-center justify-center px-3 text-gray-500 transition hover:text-teal-400 focus:text-teal-400 focus:outline-none"
                        :aria-label="show ? 'Hide password' : 'Show password'"
                        :title="show ? 'Hide password' : 'Show password'"
                    >
                        {{-- Eye, shown while the password is hidden --}}
                        <svg x-show="! show" x-cloak xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        {{-- Eye with a stroke through it, shown while it is visible --}}
                        <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M10.733 5.076a10.744 10.744 0 0 1 3.517 2.453c3.63 3.19 5.833 7.353 5.833 7.353a18.03 18.03 0 0 1-2.782 4.143M6.515 6.515A18.03 18.03 0 0 0 1.667 12s2.203 4.163 5.833 7.353c1.073.96 2.197 1.766 3.36 2.411" />
                            <path d="M2.667 2.667l18.666 18.666" />
                            <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                        </svg>
                    </button>
                </div>

                {{-- The checklist states the four rules the server enforces, so the
                     student can see what is missing instead of submitting and being
                     told only that validation failed. --}}
                <ul class="mt-3 space-y-1.5 text-xs" aria-live="polite">
                    @foreach ([
                        ['length', 'value.length >= 12', 'At least 12 characters'],
                        ['upper', '/[A-Z]/.test(value)', 'One uppercase letter'],
                        ['number', '/[0-9]/.test(value)', 'One number'],
                        ['symbol', '/[^A-Za-z0-9]/.test(value)', 'One symbol, e.g. # or $'],
                    ] as [$key, $test, $label])
                        <li
                            class="flex items-center gap-2 transition-colors duration-150"
                            :class="{{ $test }} ? 'text-teal-400' : 'text-gray-500'"
                        >
                            <svg
                                class="shrink-0 transition-opacity duration-150"
                                :class="{{ $test }} ? 'opacity-100' : 'opacity-40'"
                                xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                            <span>{{ $label }}</span>
                        </li>
                    @endforeach
                </ul>

                @error('password')
                    <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-300">
                    Confirm password
                </label>
                <input
                    id="password_confirmation"
                    type="password"
                    wire:model="password_confirmation"
                    autocomplete="new-password"
                    required
                    class="mt-1.5 block w-full rounded-lg border border-white/10 bg-gray-950/60 px-3 py-2.5 text-sm text-gray-100 placeholder-gray-600 transition focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                    placeholder="Type it again"
                >
                @error('password_confirmation')
                    <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="register"
                class="w-full rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 focus:ring-offset-[#16202e] disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="register">Create account</span>
                <span wire:loading wire:target="register">Creating&hellip;</span>
            </button>
        </form>
    </div>

    <div class="mt-6 space-y-2 text-center text-sm">
        <p class="text-gray-400">
            Already have an account?
            <a href="{{ route('filament.student.auth.login') }}" class="font-medium text-teal-400 transition hover:text-teal-300 hover:underline">
                Sign in
            </a>
        </p>
        <p class="text-xs text-gray-600">
            New accounts are students, and may hold
            {{ config('infrastructure.student_environment_limit', 3) }}
            lab environments at a time.
        </p>
    </div>
</div>