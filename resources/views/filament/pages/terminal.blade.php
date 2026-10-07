@if (auth()->user()?->isAdmin())
    @include('filament.pages.terminal-admin')
@else
    @include('filament.pages.terminal-student')
@endif
