<x-filament-panels::page>
    @php
        $course = $this->course();
        $session = $this->currentSession();
        $used = $session ? 1 : 0;
        $status = $session?->status?->value ?? 'available';
    @endphp

    <div class="space-y-6" wire:poll.10s>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-950 dark:text-white">
                    Explore Your Labs
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Pilih course untuk menjalankan isolated cybersecurity lab.
                </p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white px-5 py-3 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
                    Paired Lab Usage
                </div>
                <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                    {{ $used }}/1
                </div>
                <div class="mt-2 h-1.5 w-28 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                    <div
                        class="h-full rounded-full {{ $used ? 'bg-amber-500' : 'bg-teal-500' }}"
                        style="width: {{ $used ? '100%' : '0%' }}"
                    ></div>
                </div>
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-3">
            <section class="overflow-hidden rounded-2xl border border-teal-200 bg-white shadow-sm dark:border-teal-900 dark:bg-gray-900 lg:col-span-2">
                <div class="border-b border-gray-100 bg-gradient-to-r from-teal-50 to-sky-50 p-6 dark:border-white/10 dark:from-teal-950/40 dark:to-sky-950/20">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="rounded-full bg-teal-100 px-3 py-1 text-xs font-semibold text-teal-800 dark:bg-teal-900 dark:text-teal-200">
                            GUIDED CTF
                        </span>

                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $session ? 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200' }}">
                            {{ $session ? strtoupper(str_replace('_', ' ', $status)) : 'AVAILABLE' }}
                        </span>
                    </div>

                    <h3 class="mt-5 text-2xl font-bold text-gray-950 dark:text-white">
                        IMNEHNET Web Server Breach
                    </h3>

                    <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                        Latihan cybersecurity dengan dua container terisolasi:
                        Kali Linux sebagai attacker dan web server sebagai victim.
                        Selesaikan enam objective secara berurutan.
                    </p>
                </div>

                <div class="space-y-5 p-6">
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded-lg bg-gray-100 px-3 py-2 text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            Beginner
                        </span>
                        <span class="rounded-lg bg-gray-100 px-3 py-2 text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            6 Objectives
                        </span>
                        <span class="rounded-lg bg-gray-100 px-3 py-2 text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            LAB-ATK + LAB-ENV
                        </span>
                        <span class="rounded-lg bg-gray-100 px-3 py-2 text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            Real Terminal
                        </span>
                    </div>

                    @if ($session)
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                            <strong>Lab aktif (1/1).</strong>
                            Kamu sudah mencapai batas satu paired lab.
                            Selesaikan atau hapus lab sebelumnya sebelum membuat lab baru.
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                                <div class="text-xs text-gray-500">Attacker Container</div>
                                <div class="mt-1 font-mono text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $session->attacker_container }}
                                </div>
                                <div class="mt-1 text-xs text-gray-500">LAB-ATK</div>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                                <div class="text-xs text-gray-500">Victim Container</div>
                                <div class="mt-1 font-mono text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $session->victim_container }}
                                </div>
                                <div class="mt-1 text-xs text-gray-500">LAB-ENV</div>
                            </div>
                        </div>

                        @if ($session->status === \App\Enums\LabSessionStatus::Deleting)
                            <div class="rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200">
                                <strong>Deleting lab...</strong>
                                Kuota tetap 1/1 sampai victim dan attacker terverifikasi terhapus.
                                @if ($session->last_error)
                                    <p class="mt-2">{{ $session->last_error }}</p>
                                @endif
                            </div>
                        @endif

                        <div class="flex flex-wrap gap-3">
                        @if ($session->status->allowsTerminal())
                        <x-filament::button
                            size="lg"
                            color="primary"
                            icon="heroicon-o-command-line"
                            wire:click="openWorkspace"
                            wire:loading.attr="disabled"
                            wire:target="openWorkspace"
                        >
                            Open Lab Workspace
                        </x-filament::button>
                        @endif


                        @if (in_array($session->status, [
                            \App\Enums\LabSessionStatus::Ready,
                            \App\Enums\LabSessionStatus::Running,
                        ], true))
                            <x-filament::button color="warning" icon="heroicon-o-pause"
                                wire:click="requestStop">
                                Stop Lab
                            </x-filament::button>
                        @endif

                        @if ($session->status === \App\Enums\LabSessionStatus::Stopped)
                            <x-filament::button color="success" icon="heroicon-o-play"
                                wire:click="resumeLab" wire:loading.attr="disabled"
                                wire:target="resumeLab">
                                Resume Lab
                            </x-filament::button>
                        @endif

                        @if (in_array($session->status, [
                            \App\Enums\LabSessionStatus::Ready,
                            \App\Enums\LabSessionStatus::Running,
                            \App\Enums\LabSessionStatus::Completed,
                        ], true))
                            <x-filament::button color="gray" icon="heroicon-o-arrow-path"
                                wire:click="requestReset">
                                Reset Lab
                            </x-filament::button>
                        @endif

                        <x-filament::button
                            size="lg"
                            color="danger"
                            icon="heroicon-o-trash"
                            wire:click="requestDelete"
                            wire:loading.attr="disabled"
                            wire:target="requestDelete"
                        >
                            {{ $session->status === \App\Enums\LabSessionStatus::Deleting ? 'Retry Delete' : 'Delete Lab' }}
                        </x-filament::button>
                        </div>
                    @elseif ($course)
                        <x-filament::button
                            size="lg"
                            color="primary"
                            icon="heroicon-o-play"
                            wire:click="startLab"
                            wire:loading.attr="disabled"
                            wire:target="startLab"
                        >
                            Start Lab
                        </x-filament::button>
                    @else
                        <p class="text-sm text-rose-600">
                            Course belum tersedia. Hubungi administrator.
                        </p>
                    @endif
                </div>
            </section>

            <section class="flex min-h-64 flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center dark:border-gray-700 dark:bg-gray-900/60">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-200 text-2xl dark:bg-gray-800">
                    {!! \App\Support\Lucide::html('lock-keyhole') !!}
                </div>

                <h3 class="font-semibold text-gray-900 dark:text-white">
                    More Courses
                </h3>

                <p class="mt-2 max-w-xs text-sm text-gray-500 dark:text-gray-400">
                    Course cybersecurity lainnya akan tersedia pada pengembangan berikutnya.
                </p>

                <span class="mt-5 rounded-full bg-gray-200 px-4 py-1.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    LOCKED
                </span>
            </section>
        </div>
    </div>

    @if ($showDeleteConfirmation)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/65 p-4 backdrop-blur-md">
            <div role="dialog" aria-modal="true" aria-labelledby="delete-lab-title"
                 class="w-full max-w-md rounded-2xl border border-white/10 bg-gray-900 p-6 text-white shadow-2xl">
                <div class="mb-4 text-rose-400">
                    {!! \App\Support\Lucide::html('triangle-alert') !!}
                </div>
                <h2 id="delete-lab-title" class="text-xl font-bold">Delete Lab?</h2>
                <p class="mt-3 text-sm leading-6 text-gray-300">
                    Kedua container (victim dan attacker) akan dihapus permanen.
                    Riwayat objective tetap tersimpan. Kuota baru tersedia setelah
                    penghapusan infrastruktur berhasil diverifikasi.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelDelete"
                            class="rounded-lg bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-rose-700">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmDelete"
                            wire:loading.attr="disabled" wire:target="confirmDelete"
                            class="rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-gray-950 hover:bg-gray-200">
                        Confirm Delete
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($showStopConfirmation)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/65 p-4 backdrop-blur-md">
            <div role="dialog" aria-modal="true" aria-labelledby="stop-lab-title"
                 class="w-full max-w-md rounded-2xl border border-white/10 bg-gray-900 p-6 text-white shadow-2xl">
                <h2 id="stop-lab-title" class="text-xl font-bold">Stop Lab?</h2>
                <p class="mt-3 text-sm leading-6 text-gray-300">
                    Akses jaringan lab akan dinonaktifkan sementara.
                    Container tetap berjalan dan progres objective dipertahankan.
                    Gunakan Resume untuk melanjutkan.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelStop"
                        class="rounded-lg bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white">
                        Cancel
                    </button>
                    <button type="button" wire:click="stopLab"
                        wire:loading.attr="disabled" wire:target="stopLab"
                        class="rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-gray-950">
                        Confirm Stop
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($showResetConfirmation)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/65 p-4 backdrop-blur-md">
            <div role="dialog" aria-modal="true" aria-labelledby="reset-lab-title"
                 class="w-full max-w-md rounded-2xl border border-white/10 bg-gray-900 p-6 text-white shadow-2xl">
                <h2 id="reset-lab-title" class="text-xl font-bold">Reset Lab?</h2>
                <p class="mt-3 text-sm leading-6 text-gray-300">
                    Skenario akan dipulihkan dan progres objective kembali menjadi 0/6.
                    Evidence penyelesaian sebelumnya akan dibersihkan.
                    Kedua container tidak dihapus oleh aksi ini.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelReset"
                        class="rounded-lg bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmReset"
                        wire:loading.attr="disabled" wire:target="confirmReset"
                        class="rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-gray-950">
                        Confirm Reset
                    </button>
                </div>
            </div>
        </div>
    @endif

</x-filament-panels::page>
