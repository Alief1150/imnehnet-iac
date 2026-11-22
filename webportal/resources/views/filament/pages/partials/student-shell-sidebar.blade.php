{{-- IMNEHNET_SHELL_CLEANUP_20261122 --}}
{{-- IMNEHNET_STUDENT_SHELL_UNIFIED_20261122 --}}
@php($shItems = \App\Support\StudentNavigation::items())
<aside class="im-v4-sidebar im-sh-sidebar" aria-label="Student sidebar">
    <a class="im-v4-brand im-sh-brand" href="{{ url('/student') }}" aria-label="IMNEHNET Dashboard">
        <img src="{{ \App\Support\StudentNavigation::logoUrl() }}" alt="" class="im-sh-logo" />
        <span class="im-sh-brand-text"><strong>IMNEHNET</strong><small>Lab-as-a-Service</small></span>
    </a>
    <nav class="im-v4-menu menu" aria-label="Main navigation">
        @foreach ($shItems as $shItem)
            <a class="im-sh-nav-link {{ $shItem['active'] ? 'is-active' : '' }}" href="{{ $shItem['url'] }}" @if($shItem['active']) aria-current="page" @endif>
                {!! \App\Support\StudentNavigation::icon($shItem['icon']) !!}<span>{{ $shItem['label'] }}</span>
            </a>
        @endforeach
    </nav>
    <div class="im-v4-sidebar-note card"><strong>Learn. Explore. Secure.</strong><p>“Above all, don't lie to yourself.” — Fyodor Dostoyevsky</p></div>
</aside>
