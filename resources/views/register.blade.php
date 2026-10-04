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

        <style>
            /* Alpine's x-show leaves an element visible until it initialises, which
               shows both password icons for a moment on every page load. */
            [x-cloak] {
                display: none !important;
            }
        </style>
    </head>

    <body class="bg-gray-950 text-gray-100 min-h-screen flex items-center justify-center px-4 py-10">
        {{-- A faint teal wash in the corner, so the page reads as part of the same
             product as the panel it leads into rather than a form bolted on. --}}
        <div
            class="pointer-events-none fixed inset-0 bg-[radial-gradient(60rem_40rem_at_top_left,rgba(13,148,136,0.10),transparent_60%)]"
            aria-hidden="true"
        ></div>

        <div class="relative w-full max-w-md">
            <p class="mb-6 text-center text-sm font-semibold tracking-[0.4em] text-gray-200 uppercase">
                IMNEHNET
            </p>

            @livewire('register-student')
        </div>
    </body>
</html>