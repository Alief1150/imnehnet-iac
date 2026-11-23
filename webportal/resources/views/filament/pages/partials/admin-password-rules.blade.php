@php
    $checks = [
        'Minimal 8 karakter' => mb_strlen($password) >= 8,
        'Huruf kapital (A-Z)' => preg_match('/[A-Z]/', $password),
        'Huruf kecil (a-z)' => preg_match('/[a-z]/', $password),
        'Angka (0-9)' => preg_match('/[0-9]/', $password),
        'Simbol khusus' => preg_match('/[^A-Za-z0-9]/', $password),
    ];
@endphp

<div class="mt-2 rounded-lg border border-gray-700 p-3 text-xs">
    <p class="mb-2 font-semibold text-gray-200">
        Password Requirements
    </p>

    <div class="space-y-2">
        @foreach ($checks as $label => $valid)
            <div class="flex items-center gap-2">
                <span class="{{ $valid ? 'text-emerald-400' : 'text-gray-400' }}">
                    {{ $valid ? '✓' : '○' }}
                </span>
                <span class="{{ $valid ? 'text-emerald-400' : 'text-gray-400' }}">
                    {{ $label }}
                </span>
            </div>
        @endforeach
    </div>

    <p class="mt-3 text-gray-400">
        Kosongkan password untuk membuat password sementara otomatis.
    </p>
</div>
