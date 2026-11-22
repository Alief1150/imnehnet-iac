
<style>
/* IMNEHNET_CONTRAST_FIX_V1 */

/* Progress heading: light mode */
#im-v4 .pc-progress-head h2 {
    color: #172b60 !important;
}

/* Progress heading: dark mode */
#im-v4[data-theme="dark"] .pc-progress-head h2 {
    color: #ffffff !important;
}

/* Objective cards: clean white surface */
#im-v4 .pc-progress-item {
    background: #ffffff !important;
    color: #172b60 !important;
    border: 1px solid #dce6f5 !important;
    border-radius: 12px;
}

#im-v4 .pc-progress-item strong {
    color: #172b60 !important;
}

#im-v4 .pc-progress-item small {
    color: #52688d !important;
}

/* Active objective */
#im-v4 .pc-progress-item.is-current {
    background: #ffffff !important;
    border-color: #3999ff !important;
    box-shadow: inset 3px 0 0 #168dff;
}

/* Status badges */
#im-v4 .pc-progress-item .pc-progress-tag {
    background: #eaf2ff !important;
    color: #1554a0 !important;
    border-radius: 6px;
    padding: 6px 9px;
}

#im-v4 .pc-progress-item.is-current .pc-progress-tag {
    background: #dcecff !important;
    color: #075cc2 !important;
}

/* Completed objective */
#im-v4 .pc-progress-item:has(
    .pc-progress-icon:first-child
) {
    border-color: #dce6f5;
}

/* Next objective banner */
#im-v4 .pc-progress-next {
    background: #ffffff !important;
    color: #172b60 !important;
    border: 1px solid #dce6f5 !important;
    border-radius: 12px;
}

#im-v4 .pc-progress-next strong {
    color: #172b60 !important;
}

#im-v4 .pc-progress-next small {
    color: #52688d !important;
}

/* Keep text legible on light surfaces in dark mode */
#im-v4[data-theme="dark"] .pc-progress-item,
#im-v4[data-theme="dark"] .pc-progress-next {
    background: #ffffff !important;
    color: #172b60 !important;
}

#im-v4[data-theme="dark"] .pc-progress-item strong,
#im-v4[data-theme="dark"] .pc-progress-next strong {
    color: #172b60 !important;
}

#im-v4[data-theme="dark"] .pc-progress-item small,
#im-v4[data-theme="dark"] .pc-progress-next small {
    color: #52688d !important;
}

/* Preserve contrast on progress metrics */
#im-v4[data-theme="dark"] .pc-progress-head p,
#im-v4[data-theme="dark"] .pc-counters span {
    color: #aebfdf !important;
}

/* Mobile: allow text and badges to wrap */
@media (max-width: 600px) {
    #im-v4 .pc-progress-item {
        min-width: 0;
        gap: 10px;
    }

    #im-v4 .pc-progress-item > div {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    #im-v4 .pc-progress-next {
        gap: 12px;
    }
}
</style>

{{-- IMNEHNET_PROGRESS_V1 --}}
@php
 $rows=$session->course->objectives()->orderBy('sequence')->get();
 $progressRows=$session->progress()->get()->keyBy('objective_id');
 $completedCount=0;
 foreach($rows as $objective){if($progressRows->get($objective->id)?->status === \App\Enums\ObjectiveStatus::Completed){$completedCount++;}}
 $totalCount=$rows->count();
 $nextObjective=$rows->first(fn($objective)=>$progressRows->get($objective->id)?->status === \App\Enums\ObjectiveStatus::Active);
 $pct=$totalCount ? round($completedCount/$totalCount*100) : 0;
@endphp
<div class="la-body pc-progress">
 <div class="pc-progress-head"><div><h2>Progress CTF</h2><p>{{ $course?->title }} · {{ $completedCount }} dari {{ $totalCount }} objective selesai</p></div><div class="pc-counters"><span><strong>{{ $completedCount }}</strong>Selesai</span><span><strong>{{ $nextObjective ? 1 : 0 }}</strong>Sedang dikerjakan</span><span><strong>{{ max(0,$totalCount-$completedCount-($nextObjective ? 1 : 0)) }}</strong>Terkunci</span></div></div>
 <div class="pc-progress-bar"><div style="width:{{ $pct }}%"></div></div><div class="pc-progress-percent">{{ $pct }}%</div>
 <div class="pc-progress-list">
 @foreach($rows as $objective)
 @php $status=$progressRows->get($objective->id)?->status ?? \App\Enums\ObjectiveStatus::Locked; $done=$status === \App\Enums\ObjectiveStatus::Completed; $current=$status === \App\Enums\ObjectiveStatus::Active; @endphp
 <div class="pc-progress-item {{ $current ? 'is-current' : '' }}"><span class="pc-progress-icon">{{ $done ? '✓' : ($current ? '◉' : '♙') }}</span><div><strong>{{ $objective->numberedTitle() }}</strong>@if($done || $current)<small>{{ $objective->description }}</small>@endif</div><span class="pc-progress-tag">{{ $done ? 'Selesai' : ($current ? 'Sedang dikerjakan' : 'Terkunci') }}</span></div>
 @endforeach
 </div>
 <div class="pc-progress-next"><span>⚑</span><div><strong>{{ $nextObjective ? 'Lanjutkan Objective '.sprintf('%02d',$nextObjective->sequence).' · '.$nextObjective->title : ($completedCount === $totalCount && $totalCount > 0 ? 'Semua objective selesai' : 'Belum ada objective aktif') }}</strong><small>{{ $nextObjective ? 'Objective berikutnya akan terbuka setelah tahap ini selesai.' : 'Lihat hasil dan status melalui halaman Pentest.' }}</small></div><button type="button" wire:click="openWorkspace" wire:loading.attr="disabled" wire:target="openWorkspace" @disabled(! $session->status->allowsTerminal())>Lanjutkan Pentest →</button></div>
</div>
