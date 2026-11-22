<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>IMNEHNET — Environment Pair Terminal</title>
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0;
        background: #0b1020;
        color: #e5e7eb;
        font: 14px system-ui, sans-serif;
    }
    header {
        padding: 20px 24px;
        border-bottom: 1px solid #334155;
    }
    h1 { font-size: 22px; margin: 12px 0 6px; }
    a { color: #93c5fd; text-decoration: none; }
    small, .muted { color: #94a3b8; }
    main { padding: 24px; }
    .actions {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
    }
    button {
        padding: 11px 20px;
        border: none;
        border-radius: 8px;
        font-weight: 700;
        color: white;
        cursor: pointer;
    }
    .open { background: #15803d; }
    .close { background: #b91c1c; }
    .grid {
        display: grid;
        grid-template-columns: minmax(0,1fr) minmax(0,1fr);
        gap: 16px;
    }
    .panel {
        min-width: 0;
        border: 1px solid #334155;
        background: #111827;
        border-radius: 10px;
        overflow: hidden;
    }
    .panel h2 {
        font-size: 15px;
        margin: 0;
        padding: 14px;
        border-bottom: 1px solid #334155;
    }
    iframe {
        display: block;
        width: 100%;
        height: 68vh;
        border: 0;
        background: #050816;
    }
    .empty {
        height: 68vh;
        display: grid;
        place-items: center;
        color: #64748b;
    }
    .notice {
        padding: 12px 16px;
        background: #1e293b;
        margin-bottom: 16px;
        border-radius: 8px;
    }
    .error { background: #7f1d1d; }
    .muted { margin-top: 16px; }
    @media (max-width: 900px) {
        .grid { grid-template-columns: 1fr; }
        iframe, .empty { height: 48vh; }
    }

    /* IMNEHNET_PAIR_FIX_V3 — Light theme */
    html[data-imnehnet-theme="light"] body {
        background: #f8fafc;
        color: #0f172a;
    }

    html[data-imnehnet-theme="light"] header {
        background: #ffffff;
        border-bottom-color: #e2e8f0;
    }

    html[data-imnehnet-theme="light"] a {
        color: #2563eb;
    }

    html[data-imnehnet-theme="light"] small,
    html[data-imnehnet-theme="light"] .muted {
        color: #64748b;
    }

    html[data-imnehnet-theme="light"] .panel {
        background: #ffffff;
        border-color: #cbd5e1;
    }

    html[data-imnehnet-theme="light"] .panel h2 {
        border-bottom-color: #e2e8f0;
    }

    html[data-imnehnet-theme="light"] .empty {
        color: #64748b;
    }

    html[data-imnehnet-theme="light"] .notice {
        background: #dbeafe;
        color: #1e3a8a;
    }

    html[data-imnehnet-theme="light"] .notice.error {
        background: #fee2e2;
        color: #991b1b;
    }

    /* Terminals themselves intentionally retain dark backgrounds. */
    html[data-imnehnet-theme="light"] iframe {
        background: #0b1020;
    }

    .actions form { margin: 0; }

    .actions button:hover {
        filter: brightness(1.12);
    }

    .actions button:focus-visible {
        outline: 3px solid #60a5fa;
        outline-offset: 3px;
    }

</style>

<script>
/* IMNEHNET_PAIR_FIX_V3 — standalone Filament theme synchronisation */
(function () {
    function preferredTheme() {
        var saved = null;
        try {
            saved = localStorage.getItem('theme')
                || localStorage.getItem('filament-theme');
        } catch (e) {}

        if (saved === 'dark' || saved === 'light') {
            return saved;
        }

        return window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';
    }

    function updateTheme() {
        document.documentElement.dataset.imnehnetTheme = preferredTheme();
        document.documentElement.style.colorScheme = preferredTheme();
    }

    updateTheme();

    window.addEventListener('storage', updateTheme);

    window.matchMedia('(prefers-color-scheme: dark)')
        .addEventListener('change', updateTheme);
})();
</script>

</head>
<body>
<header>
    <a href="/admin/environments">← Environments</a>
    @php
    // IMNEHNET_TERMINAL_TITLE_V3
    $imnehnetRouteEnvironment = request()->route('environment');

    $imnehnetEnvironmentId = is_object($imnehnetRouteEnvironment)
        ? (int) $imnehnetRouteEnvironment->getKey()
        : (int) $imnehnetRouteEnvironment;

    $imnehnetPair = $imnehnetEnvironmentId > 0
        ? \App\Models\AdminEnvironmentPair::forEnvironment(
            $imnehnetEnvironmentId
        )
        : null;

    $imnehnetPairTitle = trim((string) ($imnehnetPair?->name ?? ''));
@endphp
<h1>{{ $imnehnetPairTitle !== '' ? $imnehnetPairTitle : 'Environment Pair Terminal' }}</h1>
    <small>
        {{ $owner?->name ?? 'Unknown owner' }}
        · Admin Pair #{{ $lab->getKey() }}
    </small>
</header>

<main>
    @if(session('success'))
        <div class="notice">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="notice error">{{ session('error') }}</div>
    @endif

    <div class="actions">
        @if(!$pairOpen)
            <form method="POST"
                  action="{{ route('admin.standalone-pair.open', $environmentId) }}">
                @csrf
                <button class="open" type="submit">Open Pair</button>
            </form>
        @endif

        <form method="POST"
              action="{{ route('admin.standalone-pair.close', $environmentId) }}">
            @csrf
            <button class="close" type="submit">Close All</button>
        </form>
    </div>

    <div class="grid">
        @foreach([
            'attacker' => $attacker,
            'victim' => $victim,
        ] as $role => $container)
            <section class="panel">
                <h2>
                    {{ strtoupper($role) }}
                    <small>— {{ $container->container_name }}</small>
                </h2>

                @if($pairOpen && $sessions[$role])
                    <iframe
                        title="{{ ucfirst($role) }} terminal"
                        src="{{ $sessions[$role]['url'] }}"
                        allow="clipboard-read; clipboard-write">
                    </iframe>
                @else
                    <div class="empty">Terminal not connected</div>
                @endif
            </section>
        @endforeach
    </div>

    <p class="muted">
        Close All closes administrator-owned terminal sessions only.
        The attacker and victim containers remain running.
    </p>
</main>
</body>
</html>
