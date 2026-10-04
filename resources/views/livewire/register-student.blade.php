<div class="w-full max-w-md">
    <div class="text-center mb-6">
        <p class="text-xs font-semibold tracking-[0.3em] text-gray-400 uppercase">IMNEHNET</p>
        <h1 class="mt-2 text-2xl font-semibold text-white">Register a student account</h1>
        <p class="mt-1 text-sm text-gray-400">
            For practising cybersecurity labs. You will be able to create and use your own lab
            environments straight away.
        </p>
    </div>

    <div class="rounded-xl bg-[#16202e] border border-white/5 shadow-2xl p-7">
        <form wire:submit="register" class="space-y-5">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-300">Name</label>
                <input
                    id="name"
                    type="text"
                    wire:model="name"
                    autocomplete="name"
                    required
                    autofocus
                    class="mt-1.5 block w-full rounded-lg border border-white/10 bg-gray-900/70 px-3 py-2 text-gray-100 placeholder-gray-500 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    placeholder="Your name"
                >
                @error('name')
                    <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
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
                    class="mt-1.5 block w-full rounded-lg border border-white/10 bg-gray-900/70 px-3 py-2 text-gray-100 placeholder-gray-500 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    placeholder="you@example.com"
                >
                @error('email')
                    <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-300">Password</label>
                <input
                    id="password"
                    type="password"
                    wire:model="password"
                    autocomplete="new-password"
                    required
                    class="mt-1.5 block w-full rounded-lg border border-white/10 bg-gray-900/70 px-3 py-2 text-gray-100 placeholder-gray-500 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    placeholder="At least 12 characters"
                >
                @error('password')
                    <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
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
                    class="mt-1.5 block w-full rounded-lg border border-white/10 bg-gray-900/70 px-3 py-2 text-gray-100 placeholder-gray-500 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                >
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="register"
                class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-[#16202e] disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="register">Create account</span>
                <span wire:loading wire:target="register">Creating&hellip;</span>
            </button>
        </form>
    </div>

    <p class="mt-5 text-center text-sm text-gray-400">
        Already have an account?
        <a
            href="{{ route('login') }}"
            class="font-medium text-emerald-500 hover:text-emerald-400 hover:underline"
        >Sign in</a>
    </p>

    <p class="mt-4 text-center text-xs text-gray-500">
        New accounts are students, and may hold
        {{ config('infrastructure.student_environment_limit', 3) }}
        lab environments at a time.
    </p>
</div>