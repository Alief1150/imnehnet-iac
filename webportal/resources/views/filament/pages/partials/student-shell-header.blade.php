{{-- IMNEHNET_STUDENT_SHELL_UNIFIED_20261122 --}}
@php($shItems = \App\Support\StudentNavigation::items())
@php($shUser = auth()->user())
<header class="im-v4-topbar im-sh-topbar">
    {{-- IMNEHNET_SHELL_HOTFIX_20261122 --}}
    <button type="button" class="im-sh-mobile-toggle" data-sh-mobile-toggle aria-label="Open navigation" aria-expanded="false" aria-controls="im-sh-sidebar-menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>

    <button type="button" class="im-v4-search-btn btn" data-im-v4-search aria-label="Open search">{!! \App\Support\StudentNavigation::icon('search') !!}<span>Search anything in IMNEHNET...</span><kbd>Ctrl K</kbd></button>
    <div class="im-v4-top-actions im-sh-actions">
        <div class="im-sh-control">
            <button type="button" class="btn btn-square im-v4-icon-btn im-sh-trigger" data-sh-toggle="theme" aria-label="Theme settings" aria-expanded="false" aria-haspopup="menu">{!! \App\Support\StudentNavigation::icon('sun') !!}</button>
            <div class="im-sh-panel im-sh-theme-panel" data-sh-panel="theme" role="menu" hidden>
                @foreach (['light'=>'Light','dark'=>'Dark','system'=>'System'] as $shKey=>$shText)
                    <button type="button" role="menuitemradio" data-sh-theme="{{ $shKey }}" aria-checked="false">{{ $shText }}</button>
                @endforeach
            </div>
        </div>
        <div class="im-sh-control">
            <button type="button" class="btn btn-square im-v4-icon-btn im-sh-trigger" data-sh-toggle="notifications" aria-label="Notifications" aria-expanded="false" aria-haspopup="menu">{!! \App\Support\StudentNavigation::icon('bell') !!}<span class="im-sh-unread" data-sh-unread hidden></span></button>
            <div class="im-sh-panel im-sh-notification-panel" data-sh-panel="notifications" role="menu" hidden>
                <div class="im-sh-panel-head"><strong>Notifications</strong><button type="button" data-sh-readall>Mark all read</button></div>
                <div class="im-sh-notification-items" data-sh-items><p class="im-sh-empty">Loading...</p></div>
            </div>
        </div>
        <div class="im-sh-control im-sh-profile-wrap">
            <button type="button" class="im-sh-profile-trigger" data-sh-toggle="profile" aria-label="Profile menu" aria-expanded="false" aria-haspopup="menu">
                <span class="im-sh-avatar">@if($shUser?->getFilamentAvatarUrl())<img src="{{ $shUser->getFilamentAvatarUrl() }}" alt="" />@else{{ strtoupper(mb_substr($shUser?->name ?? 'U',0,1)) }}@endif</span>
                <span class="im-sh-user-meta"><strong>{{ $shUser?->name ?? 'Student' }}</strong><small>Student</small></span>{!! \App\Support\StudentNavigation::icon('chevron') !!}
            </button>
            <div class="im-sh-panel im-sh-profile-panel" data-sh-panel="profile" role="menu" hidden>
                <div class="im-sh-profile-heading"><span class="im-sh-avatar">@if($shUser?->getFilamentAvatarUrl())<img src="{{ $shUser->getFilamentAvatarUrl() }}" alt="" />@else{{ strtoupper(mb_substr($shUser?->name ?? 'U',0,1)) }}@endif</span><span><strong>{{ $shUser?->name ?? 'Student' }}</strong><small>{{ $shUser?->email ?? '' }}</small></span></div>
                <nav class="im-sh-profile-links" aria-label="Profile navigation">
                    @foreach ($shItems as $shItem)
                        @if(in_array($shItem['key'], ['dashboard','labs','pentest','profile'], true))
                            <a href="{{ $shItem['url'] }}" @if($shItem['active']) aria-current="page" @endif>{!! \App\Support\StudentNavigation::icon($shItem['icon']) !!}<span>{{ $shItem['menu'] }}</span></a>
                        @endif
                    @endforeach
                </nav>
                <form method="POST" action="{{ filament()->getLogoutUrl() }}">@csrf <button type="submit" class="im-sh-logout">Sign Out</button></form>
            </div>
        </div>
    </div>
    <div class="im-sh-mobile-scrim" data-sh-mobile-scrim hidden></div>
</header>
