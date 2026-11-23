<!DOCTYPE html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="robots" content="noindex">

    <title>Register · IMNEHNET</title>

    @fonts

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))

        @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/filament/admin/theme.css'])

    @endif

    <link rel="stylesheet" href="/imnehnet-login/login-v5.css?v=5.2">

    <link rel="stylesheet" href="/imnehnet-login/register-v1.css?v=1">

    <style>[x-cloak]{display:none!important}</style>

</head>

<body class="imnehnet-register-body">

    <div class="im-v5 im-register" data-theme="dark">

        <div id="im-react-bits-ascii" aria-hidden="true"></div>

        <main class="im-register-shell">

            @livewire('register-student')

        </main>

    </div>

    @vite('resources/js/imnehnet-login-ascii.jsx')

</body>

</html>
