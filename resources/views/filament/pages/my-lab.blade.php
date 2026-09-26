<x-filament-panels::page>
    @php($lab = $this->lab())

    @if (! $lab)
        <section class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">No Lab Environment has been provisioned yet</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Your administrator must queue and verify a lab before access details become available.</p>
        </section>
    @else
        <div class="grid gap-4 md:grid-cols-3">
            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs uppercase text-gray-500">Desired state</p>
                <p class="mt-2 text-xl font-semibold">{{ strtoupper($lab->desired_state->value) }}</p>
            </section>
            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs uppercase text-gray-500">Actual state</p>
                <p class="mt-2 text-xl font-semibold">{{ strtoupper($lab->actual_state->value) }}</p>
            </section>
            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs uppercase text-gray-500">Operation</p>
                <p class="mt-2 text-xl font-semibold">{{ strtoupper($lab->operation_state->value) }}</p>
            </section>
        </div>

        @if ($lab->sshCommand())
            <section class="mt-4 rounded-xl border border-teal-700/40 bg-teal-950/20 p-5">
                <p class="text-sm font-medium">Verified SSH access</p>
                <code class="mt-2 block rounded-lg bg-gray-950 p-3 text-sm text-teal-300">{{ $lab->sshCommand() }}</code>
            </section>
        @else
            <p class="mt-4 text-sm text-gray-500">Access details are hidden until independently verified.</p>
        @endif
    @endif
</x-filament-panels::page>
