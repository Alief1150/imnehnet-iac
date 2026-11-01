<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- Registration is a public endpoint on an internal portal. It is not
             something to let a search engine index. --}}
        <meta name="robots" content="noindex">

        <title>Register &middot; IMNEHNET</title>

        @fonts

        {{-- The panel theme as well as app.css. The registration form and the
             sign-in form are the same product reached two ways, and they share
             one stylesheet for exactly that reason: the colour tokens live in the
             panel theme and are not defined in app.css. --}}
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/filament/admin/theme.css'])
        @endif

        <style>
            /* Alpine's x-show leaves an element visible until it initialises, which
               would show both password icons for a moment on every page load. */
            [x-cloak] {
                display: none !important;
            }
        </style>
    </head>

    <body class="imnehnet-auth-page">
        <div class="imnehnet-auth-glow" aria-hidden="true"></div>

        <main class="imnehnet-auth-shell">
            @livewire('register-student')
        </main>
    </body>
</html>