@php
    $consoleSession = \App\Models\LabSession::query()
        ->where('user_id', auth()->id())
        ->when($this->course(), fn ($q, $course) => $q->where('course_id', $course->id))
        ->latest('id')->first();
    $consoleEvents = $consoleSession
        ? \App\Services\GuidedCtf\ProvisioningTimeline::events((int) $consoleSession->id) : [];
@endphp
@if ($consoleSession)
<section wire:poll.2s style="margin-top:24px;overflow:hidden;border:1px solid #334155;border-radius:12px;background:#050a0e;color:#d1d5db" aria-label="Lab process log">
    <header style="padding:12px 16px;border-bottom:1px solid #334155;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <strong>Lab Process · Session #{{ $consoleSession->id }}</strong>
        <span style="font-size:12px">{{ $consoleSession->status->value }} · Update 2 detik · WIB</span>
    </header>
    <div role="log" aria-live="polite" aria-relevant="additions text"
         style="padding:16px;max-height:360px;overflow:auto;font:13px/1.7 ui-monospace,SFMono-Regular,Consolas,monospace;overflow-wrap:anywhere">
        @forelse ($consoleEvents as $event)
            <div>
                <span style="color:#94a3b8">[{{ $event['time'] }}]</span>
                <span style="color:{{ $event['level'] === 'success' ? '#4ade80' : ($event['level'] === 'error' ? '#fb7185' : '#facc15') }}">[{{ $event['level'] === 'success' ? 'OK' : ($event['level'] === 'error' ? 'FAIL' : 'INFO') }}]</span>
                {{ $event['message'] }}
            </div>
        @empty
            <div style="color:#94a3b8">Belum ada log tersimpan untuk sesi ini. Status database: {{ $consoleSession->status->value }}.</div>
        @endforelse
        @if ($consoleSession->last_error)
            <div style="margin-top:12px;color:#fb7185">[FAIL] {{ $consoleSession->last_error }}</div>
        @endif
        @if ($consoleSession->status === \App\Enums\LabSessionStatus::Deleting)
            <div style="margin-top:12px;color:#facc15">Penghapusan belum selesai. Kuota dilepas setelah kedua container terverifikasi tidak ada.</div>
        @endif
    </div>
</section>
@endif
