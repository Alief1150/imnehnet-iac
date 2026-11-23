{{--
    The registration form.

    Built from the same classes as the sign-in form, in the same order, with the
    same wordmark and the same card. They were styled separately once and drifted
    into two different products: one in the panel palette, one in whatever utility
    classes were to hand.

    One root element only. Livewire rejects a component with more than one, and
    says so as an exception rather than a warning.
--}}
<div>
    <div class="imnehnet-auth-card">
    <div class="imnehnet-auth-edge"></div>

    <div class="imnehnet-auth-head">
        <div class="imnehnet-auth-wordmark">{!! \App\Support\Wordmark::make() !!}</div>
        <h1 class="imnehnet-auth-title">Register a student account</h1>
        {{-- The same three lines the sign-in form carries: wordmark, title, then
             the tagline. What registering actually gets you is said once, under
             the form, rather than in the header where the sign-in form has
             something else to put there. --}}
        <p class="imnehnet-auth-lede">{{ \App\Support\Wordmark::tagline() }}</p>
    </div>

    <form wire:submit="register">
        <div class="imnehnet-auth-field">
            <label for="name" class="imnehnet-auth-label">Name</label>
            <input
                id="name"
                type="text"
                wire:model="name"
                autocomplete="name"
                required
                autofocus
                class="imnehnet-auth-input"
                placeholder="Your name"
            >
            @error('name')
                <p class="imnehnet-auth-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="imnehnet-auth-field">
            <label for="email" class="imnehnet-auth-label">Email address</label>
            <input
                id="email"
                type="email"
                wire:model="email"
                autocomplete="email"
                required
                class="imnehnet-auth-input"
                placeholder="you@example.com"
            >
            @error('email')
                <p class="imnehnet-auth-error">{{ $message }}</p>
            @enderror
        </div>

        {{-- One Alpine scope for the whole password area: the value drives the
             checklist and the reveal toggle, so neither needs a round trip just to
             look at what has been typed. --}}
        <div x-data="{ value: '', show: false }" class="imnehnet-auth-field">
            <label for="password" class="imnehnet-auth-label">Password</label>
            <div class="imnehnet-auth-reveal-wrap">
                <input
                    id="password"
                    :type="show ? 'text' : 'password'"
                    wire:model="password"
                    @input="value = $event.target.value"
                    autocomplete="new-password"
                    required
                    class="imnehnet-auth-input imnehnet-auth-input-has-reveal"
                    placeholder="Choose a password"
                >
                <button
                    type="button"
                    @click="show = !show"
                    class="imnehnet-auth-reveal"
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

            {{-- The four rules the server enforces, so a student can see what is
                 missing instead of submitting and being told only that validation
                 failed. data-met drives the colour in the stylesheet. --}}
            <ul class="imnehnet-auth-checks" x-show="value.length > 0" x-cloak aria-live="polite">
                @foreach ([
                    ['value.length >= 8', 'At least 8 characters'],
                    ['/[A-Z]/.test(value)', 'One uppercase letter'],
                    ['/[a-z]/.test(value)', 'One lowercase letter'],
                    ['/[0-9]/.test(value)', 'One number'],
                    ['/[^A-Za-z0-9]/.test(value)', 'One symbol, e.g. # or $'],
                ] as [$test, $label])
                    <li class="imnehnet-auth-check" :data-met="{{ $test }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 6 9 17l-5-5" />
                        </svg>
                        <span>{{ $label }}</span>
                    </li>
                @endforeach
            </ul>

            @error('password')
                <p class="imnehnet-auth-error">{{ $message }}</p>
            @enderror
        </div>

        <div x-data="{ show: false }" class="imnehnet-auth-field">

    <label for="password_confirmation" class="imnehnet-auth-label">Confirm password</label>

    <div class="imnehnet-auth-reveal-wrap">

        <input

            id="password_confirmation"

            :type="show ? 'text' : 'password'"

            wire:model="password_confirmation"

            autocomplete="new-password"

            required

            class="imnehnet-auth-input imnehnet-auth-input-has-reveal"

            placeholder="Repeat your password"

        >

        <button type="button"

                class="imnehnet-auth-reveal"

                @click="show = !show"

                :aria-label="show ? 'Hide confirmation password' : 'Show confirmation password'"

                :title="show ? 'Hide password' : 'Show password'">

            <svg x-show="!show" x-cloak width="19" height="19"

                 viewBox="0 0 24 24" fill="none" stroke="currentColor"

                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round"

                 aria-hidden="true">

                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>

                <circle cx="12" cy="12" r="3"/>

            </svg>

            <svg x-show="show" x-cloak width="19" height="19"

                 viewBox="0 0 24 24" fill="none" stroke="currentColor"

                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round"

                 aria-hidden="true">

                <path d="M3 3l18 18M10.6 5.1A10 10 0 0 1 12 5c7 0 10 7 10 7a14 14 0 0 1-3.2 4.2M6.3 6.3C3.5 8.3 2 12 2 12s3 7 10 7a10 10 0 0 0 4.3-1"/>

                <path d="M10.5 10.5a2.1 2.1 0 0 0 3 3"/>

            </svg>

        </button>

    </div>

    @error('password_confirmation')

        <p class="imnehnet-auth-error">{{ $message }}</p>

    @enderror

</div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="register"
            class="imnehnet-auth-button"
        >
            <span wire:loading.remove wire:target="register">Create account</span>
            <span wire:loading wire:target="register">Creating&hellip;</span>
        </button>
    </form>
    </div>

    <p class="imnehnet-auth-foot">
        Already have an account?
        <a href="{{ route('filament.student.auth.login') }}" class="imnehnet-auth-link">Sign in</a>
    </p>

    <p class="imnehnet-auth-note">
        New accounts are students, for practising labs. You will be able to create
        and open your own lab environments as soon as you finish, and may hold
        {{ config('infrastructure.student_environment_limit', 3) }}
        at a time.
    </p>
</div>