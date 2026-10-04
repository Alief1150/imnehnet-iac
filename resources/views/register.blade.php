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

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>

    <body class="bg-gray-950 text-gray-100 min-h-screen flex items-center justify-center px-4 py-10">
        @livewire('register-student')
    </body>
</html>