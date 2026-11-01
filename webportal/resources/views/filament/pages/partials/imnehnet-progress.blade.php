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
