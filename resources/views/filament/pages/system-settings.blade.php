<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">
                Application
            </x-slot>

            <x-slot name="description">
                General runtime information for this IMNEHNET management portal.
            </x-slot>

            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($application as $label => $value)
                    <div class="flex items-center justify-between gap-6 py-3 first:pt-0 last:pb-0">
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $label }}
                        </span>

                        <span class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ $value }}
                        </span>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Platform
            </x-slot>

            <x-slot name="description">
                Installed application stack detected by the portal.
            </x-slot>

            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($platform as $label => $value)
                    <div class="flex items-center justify-between gap-6 py-3 first:pt-0 last:pb-0">
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $label }}
                        </span>

                        <span class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ $value }}
                        </span>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section class="lg:col-span-2">
            <x-slot name="heading">
                Configuration Policy
            </x-slot>

            <div class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                Sensitive infrastructure configuration is intentionally not editable
                from this page. Credentials, private keys, tokens, environment secrets,
                and infrastructure access policies remain server-side.
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
