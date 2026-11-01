{{--
    Which terminal this person gets.

    Asked of the role's capability rather than of the panel being rendered. isAdmin() was
    wrong here twice over: a lecturer signed into the admin panel would have fallen
    through to the student view, where the target list is the lab containers they do not
    own and there is no shell button at all. canOperateInfrastructure() is the same
    capability AdminTerminalService checks, so the page and the service cannot disagree
    about who gets a shell.

    The student view stays exactly as it was: a student's own container, and nothing else.
--}}
@if (auth()->user()?->canOperateInfrastructure())
    @include('filament.pages.terminal-admin')
@else
    @include('filament.pages.terminal-student')
@endif
