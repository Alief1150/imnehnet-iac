<x-filament-panels::page>
@php
    $d = ['completed' => 0, 'objectives' => 0, 'days' => [], 'activity' => [], 'usage' => []];
    $user = auth()->user();
    $completed = (int) $d['completed'];
    $objectives = (int) $d['objectives'];
    $percent = $objectives > 0 ? min(100, round(100 * $completed / $objectives)) : 0;
    $links = [
        ['Dashboard', url('/student'), 'layout-dashboard'],
        ['My Labs', url('/student/my-labs'), 'server'],
        ['Pentest', url('/student/guided-ctf'), 'terminal'],
        ['History', url('/student/history'), 'history'],
        ['Documentation', url('/student/documentation'), 'book-open'],
    ];
    $icons = [
        'logo' => 'box', 'search' => 'search', 'theme' => 'sun', 'notification' => 'bell',
        'total' => 'server', 'active' => 'activity', 'progress' => 'circle-check', 'time' => 'clock',
    ];
    $svg = function (string $name): string {
        $map = [
            'box' => '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="m3 9 9 5 9-5M12 14v7"/>',
            'layout-dashboard' => '<rect x="3" y="3" width="7" height="8" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="15" width="7" height="6" rx="1"/>',
            'server' => '<rect x="3" y="3" width="18" height="7" rx="2"/><rect x="3" y="14" width="18" height="7" rx="2"/><path d="M7 6.5h.01M7 17.5h.01"/>',
            'terminal' => '<path d="m4 6 6 6-6 6M12 18h8"/>',
            'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
            'book-open' => '<path d="M12 7v14M3 18V5a2 2 0 0 1 2-2h3a4 4 0 0 1 4 4 4 4 0 0 1 4-4h3a2 2 0 0 1 2 2v13h-5a4 4 0 0 0-4 3 4 4 0 0 0-4-3z"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
            'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/>',
            'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
            'activity' => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
            'circle-check' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 2"/>',
            'arrow-right' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
            'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'x' => '<path d="M5 5l14 14M19 5 5 19"/>',
        ];
        $path = $map[$name] ?? $map['box'];
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
    };
    $chartPayload = ['days' => $d['days'] ?? [], 'activity' => $d['activity'] ?? [], 'usage' => $d['usage'] ?? [], 'completed' => $completed, 'objectives' => $objectives];
@endphp
<link rel="stylesheet" href="{{ asset('imnehnet-v4-assets/assets/imnehnet-dashboard-v4-DB3fqm2y.css') }}">
<script type="module" src="{{ asset('imnehnet-v4-assets/assets/imnehnet-dashboard-v4-DgQKp-cH.js') }}" defer></script>
<div id="im-v4" class="im-v4" data-theme="dark" data-im-v4-root wire:ignore.self>
    <div class="im-v4-ambient" aria-hidden="true"></div>
    @include('filament.pages.partials.student-shell-sidebar')
    <div class="im-v4-workspace">
        @include('filament.pages.partials.student-shell-header')
        <main class="im-v4-content" id="im-v4-main">
<!-- IMNEHNET_STUDENT_LAYOUT_V4 -->
<div class="im-v4-student-page im-v4-student-enter">
{{-- IMNEHNET_STUDENT_HD_V3 --}}

<div class="imd"><div class="imd-bar"><span>IMNEHNET / DOCS</span><a href="{{ url('/student') }}">Ke Dashboard ↗</a></div><div class="imd-grid">

<aside class="imd-side">

<div class="im-doc-search">

    <svg viewBox="0 0 24 24" width="16" height="16"

         fill="none" stroke="currentColor" stroke-width="2">

        <circle cx="11" cy="11" r="7"/>

        <path d="m20 20-4-4"/>

    </svg>

    <input type="search"

           placeholder="Cari bagian panduan..."

           aria-label="Cari bagian dokumentasi"

           oninput="

             const q=this.value.toLowerCase();

             this.closest('.imd-side')

               .querySelectorAll('a').forEach(a=>{

                 a.hidden=!a.textContent.toLowerCase().includes(q);

               });

           ">

</div>



<b>MULAI DI SINI</b>

<a href="{{ url('/student/documentation') }}">Panduan Penggunaan Lab</a>

<a href="{{ url('/student/documentation') }}#about">Mengenal IMNEHNET</a>

<b>PANDUAN LAB</b>

<a href="{{ url('/student/documentation') }}#first">Membuat Lab</a>

<a href="{{ url('/student/documentation') }}#containers">Mengakses Containers</a>

<a href="{{ url('/student/documentation') }}#pentest">Pentest & Submit Flag</a>

<a href="{{ url('/student/documentation') }}#session">Mengelola Sesi Lab</a>

<b>BANTUAN & KEBIJAKAN</b>

<a href="{{ url('/student/documentation') }}#troubleshooting">Troubleshooting</a>

<a href="{{ url('/student/documentation/terms') }}">Ketentuan Penggunaan</a>

</aside>

<main class="imd-content">

<div class="imd-breadcrumb">DOKUMENTASI / KEBIJAKAN</div>

<h1>Ketentuan Penggunaan</h1>

<p class="imd-lead">Belajar dengan aman, bertanggung jawab, dan tetap berada dalam ruang lingkup yang diizinkan.</p>

<div class="imd-note imd-warning"><strong>Hanya uji target yang diizinkan</strong><div>Akses ke lab bukan izin menyerang sistem publik, pengguna lain, atau infrastruktur platform.</div></div>

<section id="about"><h2>01 Tentang ketentuan</h2><p>IMNEHNET adalah platform latihan keamanan siber untuk pembelajaran dan simulasi yang sah. Ikuti petunjuk course dan pengelola.</p></section>

<section id="scope"><h2>02 Ruang lingkup pengujian</h2><p>Pengujian hanya boleh dilakukan pada IP dan container yang ditetapkan sebagai target sesi Anda. Verifikasi target sebelum menjalankan pengujian.</p></section>

<section id="prohibited"><h2>03 Penggunaan yang dilarang</h2><p>Dilarang menyerang jaringan di luar lab, mengakses sesi pengguna lain, menyalahgunakan sumber daya, atau menyebarkan malware di luar skenario latihan.</p></section>

<section id="security"><h2>04 Keamanan akun</h2><p>Jaga kerahasiaan kata sandi dan jangan berbagi akun. Hindari menyimpan informasi pribadi atau rahasia di container latihan.</p></section>

<section id="data"><h2>05 Sesi, kuota & data lab</h2><p>Setiap akun mengikuti batas kuota yang ditetapkan pengelola. Reset atau penghapusan lab dapat menghilangkan perubahan dan data sesi.</p></section>

<section id="availability"><h2>06 Ketersediaan layanan</h2><p>Layanan dapat mengalami pemeliharaan atau pembatasan akses untuk menjaga stabilitas dan keamanan.</p></section>

<section id="help"><h2>07 Bantuan & pelaporan keamanan</h2><p>Catat waktu kejadian, Lab ID, Container ID, dan pesan error. Laporkan melalui saluran resmi pengelola tanpa mempublikasikan data sensitif.</p></section>

<a class="imd-next" href="{{ url('/student/documentation') }}">← Panduan Penggunaan Lab</a>

</main><aside class="imd-toc"><b>DI HALAMAN INI</b><a href="#about">Tentang ketentuan</a><a href="#scope">Ruang lingkup</a><a href="#prohibited">Penggunaan dilarang</a><a href="#security">Keamanan akun</a><a href="#data">Sesi & data lab</a><a href="#availability">Ketersediaan</a><a href="#help">Bantuan</a></aside></div></div>

<style>

.imd{color:#23395d;padding:8px 0 40px}

.imd-bar{display:flex;justify-content:space-between;gap:12px;margin-bottom:26px;font-size:11px;font-weight:700;color:#7c98bc}

.imd-bar a{color:#098be4;text-decoration:none}

.imd-grid{display:grid;grid-template-columns:190px minmax(0,1fr) 155px;gap:30px}

.imd-side{display:flex;flex-direction:column;gap:6px;align-self:start;position:sticky;top:20px}

.imd-side b,.imd-toc b{font-size:10px;color:#96a6bf;letter-spacing:.5px;margin:10px 0}

.imd-side a{padding:11px 12px;background:#ffffff91;border-radius:8px;color:#6680a9;text-decoration:none;font-size:11px}

.imd-side a:hover,.imd-side a.selected{background:#e9f4ff;color:#0088e3}

.imd-content{min-width:0}

.imd-breadcrumb{font-size:10px;font-weight:800;color:#008be7;letter-spacing:.5px;margin:8px 0 17px}

.imd h1{font-size:30px;font-weight:800;margin:0 0 15px}

.imd-lead{font-size:14px;color:#7b91b4;line-height:1.8;margin-bottom:24px}

.imd-note{padding:18px;border:1px solid #dce7fc;background:#ffffff91;border-radius:10px;color:#4778ad;font-size:13px;line-height:1.8}

.imd-warning{border-color:#f3d7a6;color:#9d6b22}

.imd section{margin:30px 0;scroll-margin-top:25px}

.imd h2{font-size:19px;font-weight:750;margin-bottom:10px}

.imd section p,.imd li{font-size:13px;color:#7b91b2;line-height:1.85}

.imd ol{padding-left:23px}

.imd li{margin:12px 0}

.imd li strong{color:#344e78}

.imd-cards{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:15px}

.imd-cards div{padding:18px;border:1px solid #e2eafa;background:#ffffffa6;border-radius:10px}

.imd-cards strong{font-size:12px}

.imd-cards p{font-size:12px;margin-top:6px}

.imd-code{padding:14px;border-radius:9px;background:#ffffffa8;border:1px solid #e0e9f9;color:#4a7eb7;font-family:monospace;font-size:12px;overflow-x:auto}

.imd-next{display:flex;justify-content:space-between;padding:18px;background:#ffffff83;border:1px solid #e2eafa;border-radius:10px;text-decoration:none;color:#0888e2;font-size:13px}

.imd-toc{display:flex;flex-direction:column;gap:15px;align-self:start;position:sticky;top:20px;border-left:1px solid #ccdcf4;padding-left:15px}

.imd-toc a{font-size:11px;color:#91a3bc;text-decoration:none}

.imd-toc a:hover{color:#0989e4}

@media(max-width:1150px){.imd-grid{grid-template-columns:160px minmax(0,1fr)}.imd-toc{display:none}}

@media(max-width:750px){.imd-grid{display:block}.imd-side{position:static;flex-direction:row;overflow-x:auto;margin-bottom:25px}.imd-side b{display:none}.imd-side a{white-space:nowrap}.imd h1{font-size:25px}.imd-cards{grid-template-columns:1fr}}

</style>

<script>

(function () {

    const root = document.querySelector('.im-v4-student-page');

    if (!root) return;



    const links = root.querySelectorAll('.imd-side a');

    const current = window.location.pathname;



    links.forEach(link => {

        const url = new URL(link.href, window.location.href);

        if (url.pathname === current && !url.hash) {

            link.classList.add('selected');

        }

    });



    const toc = [...root.querySelectorAll('.imd-toc a')];

    const sections = toc.map(a =>

        document.getElementById(a.getAttribute('href').slice(1))

    );



    if ('IntersectionObserver' in window) {

        const observer = new IntersectionObserver(entries => {

            entries.forEach(entry => {

                if (!entry.isIntersecting) return;

                toc.forEach(a => a.classList.remove('im-doc-current'));

                const active = toc.find(a =>

                    a.getAttribute('href') === '#' + entry.target.id

                );

                if (active) active.classList.add('im-doc-current');

            });

        }, { rootMargin: '-12% 0px -72% 0px' });



        sections.filter(Boolean).forEach(s => observer.observe(s));

    }

})();

</script>


</div>


<style>

/* IMNEHNET Student Layout V4 */

.im-v4-student-page {

    width: 100%;

    min-width: 0;

    padding-bottom: 35px;

}



.im-v4-student-enter {

    animation: imStudentEnter .42s ease-out both;

}



@keyframes imStudentEnter {

    from { opacity: 0; transform: translateY(12px); }

    to { opacity: 1; transform: translateY(0); }

}



.im-v4-student-page .imh,

.im-v4-student-page .imd {

    background: rgba(245, 249, 255, .97);

    border: 1px solid rgba(255, 255, 255, .65);

    border-radius: 20px;

    padding: clamp(18px, 2.5vw, 35px);

    color: #243c61;

    box-shadow: 0 20px 60px rgba(7, 26, 54, .10);

}



.im-v4-student-page .imh {

    min-height: 65vh;

}



.im-v4-student-page .imd {

    min-height: 80vh;

}



.im-v4-student-page .imh-tablebox {

    transition: box-shadow .25s, transform .25s;

}



.im-v4-student-page .imh-tablebox:hover {

    box-shadow: 0 20px 50px rgba(33, 80, 150, .10);

}



.im-v4-student-page .imh tbody tr {

    transition: background .2s;

}



.im-v4-student-page .imh tbody tr:hover {

    background: rgba(208, 229, 255, .35);

}



.im-v4-student-page .imd-grid {

    grid-template-columns: 205px minmax(0, 1fr) 155px;

    gap: clamp(15px, 2vw, 30px);

}



.im-v4-student-page .imd-side {

    max-height: calc(100vh - 110px);

    overflow-y: auto;

    scrollbar-width: thin;

}



.im-v4-student-page .imd-side a,

.im-v4-student-page .imd-toc a {

    transition: color .2s, background .2s, transform .2s;

}



.im-v4-student-page .imd-side a:hover {

    transform: translateX(3px);

}



.im-v4-student-page .imd-toc a.im-doc-current {

    color: #0289e5;

    font-weight: 750;

}



.im-doc-search {

    display: flex;

    align-items: center;

    gap: 8px;

    padding: 10px;

    background: #fff;

    border: 1px solid #dae7f7;

    border-radius: 9px;

    color: #7e98b8;

    margin-bottom: 12px;

}



.im-doc-search input {

    width: 100%;

    min-width: 0;

    outline: none;

    border: 0;

    background: transparent;

    color: #294a73;

    font-size: 11px;

}



.im-doc-search input::placeholder {

    color: #91a4bc;

}



.im-v4-student-page .imd-side a[hidden] {

    display: none !important;

}



.im-v4-student-page .imd-content {

    line-height: 1.75;

}



.im-v4-student-page .imd-content section {

    scroll-margin-top: 35px;

}



.im-v4-student-page .imd-cards > div {

    transition: transform .2s, box-shadow .2s;

}



.im-v4-student-page .imd-cards > div:hover {

    transform: translateY(-3px);

    box-shadow: 0 10px 24px rgba(35, 87, 149, .08);

}



html {

    scroll-behavior: smooth;

}



@media (max-width: 1150px) {

    .im-v4-student-page .imd-grid {

        grid-template-columns: 175px minmax(0, 1fr);

    }

}



@media (max-width: 750px) {

    .im-v4-student-page .imd-grid {

        display: block;

    }

    .im-v4-student-page .imd-side {

        position: static;

        max-height: none;

        margin-bottom: 24px;

    }

    .im-doc-search {

        min-width: 175px;

    }

    .im-v4-student-page .imh,

    .im-v4-student-page .imd {

        padding: 16px;

        border-radius: 13px;

    }

}



@media (prefers-reduced-motion: reduce) {

    .im-v4-student-enter {

        animation: none;

    }

    html {

        scroll-behavior: auto;

    }

}

</style>


</main>
    </div>
    <nav class="im-v4-bottom-nav" aria-label="Mobile navigation">
        @foreach (\App\Support\StudentNavigation::items() as $bottomItem)
            @if (in_array($bottomItem['key'], ['dashboard', 'labs', 'pentest', 'history', 'documentation'], true))
                <a href="{{ $bottomItem['url'] }}" @if($bottomItem['active']) aria-current="page" class="is-active" @endif>{!! \App\Support\StudentNavigation::icon($bottomItem['icon']) !!}<span>{{ $bottomItem['label'] }}</span></a>
            @endif
        @endforeach
    </nav>
    <div class="im-v4-search-dialog" data-im-v4-search-dialog hidden role="dialog" aria-modal="true" aria-label="Search IMNEHNET"><div class="im-v4-search-panel card"><div class="im-v4-search-input-row">{!! $svg('search') !!}<input class="input" type="search" placeholder="Search pages..." data-im-v4-search-input><button class="btn btn-square" type="button" data-im-v4-search-close aria-label="Close">{!! $svg('x') !!}</button></div><nav class="im-v4-search-links">@foreach($links as $link)<a href="{{ $link[1] }}" data-im-v4-search-item>{!! $svg($link[2]) !!}{{ $link[0] }}</a>@endforeach</nav><p class="im-v4-search-hint">Quick navigation · Esc to close</p></div></div>
    
</div>
@include('filament.pages.partials.student-shell-global')
</x-filament-panels::page>



































<style>

/* IMNEHNET_STUDENT_THEME_V41 */

#im-v4[data-theme="dark"] .im-v4-student-page {

    --sp-bg: #151f35;

    --sp-surface: #1c2a43;

    --sp-surface-hover: #243652;

    --sp-border: rgba(139, 169, 218, .18);

    --sp-text: #eef5ff;

    --sp-muted: #a8b9d4;

    --sp-accent: #66d9e4;

    --sp-shadow: rgba(0, 0, 0, .16);

}



#im-v4[data-theme="dark"] .im-v4-student-page .imh,

#im-v4[data-theme="dark"] .im-v4-student-page .imd {

    background: var(--sp-bg);

    border-color: var(--sp-border);

    color: var(--sp-text);

    box-shadow: 0 20px 55px var(--sp-shadow);

}



#im-v4[data-theme="dark"] .im-v4-student-page h1,

#im-v4[data-theme="dark"] .im-v4-student-page h2,

#im-v4[data-theme="dark"] .im-v4-student-page h3,

#im-v4[data-theme="dark"] .im-v4-student-page .imh h1,

#im-v4[data-theme="dark"] .im-v4-student-page .imd h1,

#im-v4[data-theme="dark"] .im-v4-student-page .imd h2,

#im-v4[data-theme="dark"] .im-v4-student-page .imd-cards strong,

#im-v4[data-theme="dark"] .im-v4-student-page .imd li strong {

    color: var(--sp-text);

}



#im-v4[data-theme="dark"] .im-v4-student-page p,

#im-v4[data-theme="dark"] .im-v4-student-page .imh-head p,

#im-v4[data-theme="dark"] .im-v4-student-page .imd-lead,

#im-v4[data-theme="dark"] .im-v4-student-page .imd section p,

#im-v4[data-theme="dark"] .im-v4-student-page .imd li,

#im-v4[data-theme="dark"] .im-v4-student-page .imh-footer {

    color: var(--sp-muted);

}



#im-v4[data-theme="dark"] .imh-tablebox,

#im-v4[data-theme="dark"] .imd-cards > div,

#im-v4[data-theme="dark"] .imd-note,

#im-v4[data-theme="dark"] .imd-next,

#im-v4[data-theme="dark"] .imd-code,

#im-v4[data-theme="dark"] .imd-side a,

#im-v4[data-theme="dark"] .im-doc-search,

#im-v4[data-theme="dark"] .imh-filter,

#im-v4[data-theme="dark"] .imh-pagination a {

    background: var(--sp-surface);

    border-color: var(--sp-border);

    color: var(--sp-muted);

}



#im-v4[data-theme="dark"] .imh th,

#im-v4[data-theme="dark"] .imh td {

    color: var(--sp-muted);

    border-bottom-color: var(--sp-border);

}



#im-v4[data-theme="dark"] .imh td strong {

    color: var(--sp-text);

}



#im-v4[data-theme="dark"] .imh tbody tr:hover {

    background: var(--sp-surface-hover);

}



#im-v4[data-theme="dark"] .imh-filter select,

#im-v4[data-theme="dark"] .im-doc-search input {

    color: var(--sp-text);

    background: transparent;

}



#im-v4[data-theme="dark"] .imh-filter select option {

    color: #17243b;

    background: #f5f8ff;

}



#im-v4[data-theme="dark"] .imd-side a:hover,

#im-v4[data-theme="dark"] .imd-side a.selected {

    background: var(--sp-surface-hover);

    color: var(--sp-accent);

}



#im-v4[data-theme="dark"] .imd-toc a.im-doc-current,

#im-v4[data-theme="dark"] .imd-breadcrumb,

#im-v4[data-theme="dark"] .imd-bar a,

#im-v4[data-theme="dark"] .imd-next {

    color: var(--sp-accent);

}



#im-v4[data-theme="dark"] .imd-toc {

    border-left-color: var(--sp-border);

}



#im-v4[data-theme="dark"] .imd-note.imd-warning {

    background: rgba(171, 113, 30, .12);

    border-color: rgba(243, 179, 74, .35);

    color: #f3c985;

}



#im-v4[data-theme="dark"] .imh-status.imh-running {

    background: rgba(40, 147, 226, .17);

    color: #7ac7ff;

}



#im-v4[data-theme="dark"] .imh-status.imh-completed {

    background: rgba(34, 197, 142, .16);

    color: #75e6bd;

}



#im-v4[data-theme="dark"] .imh-status.imh-stopped,

#im-v4[data-theme="dark"] .imh-status.imh-failed {

    background: rgba(239, 91, 115, .14);

    color: #ff9eae;

}



#im-v4[data-theme="dark"] .imh-pagination a.active {

    background: #168fd4;

    color: white;

}



#im-v4[data-theme="dark"] .imh-error {

    background: rgba(180, 100, 30, .15);

    color: #f3c985;

}

</style>




<style>

/* IMNEHNET_STUDENT_V42 */



/* Documentation navigation icons */

.imd-side a {

    display: flex;

    align-items: center;

    gap: 10px;

}



.imd-side a::before {

    content: "";

    width: 15px;

    height: 15px;

    flex: 0 0 15px;

    background-color: currentColor;

    opacity: .8;

    mask-repeat: no-repeat;

    mask-position: center;

    mask-size: contain;

    mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 7v14M3 18V5a2 2 0 0 1 2-2h3a4 4 0 0 1 4 4 4 4 0 0 1 4-4h3a2 2 0 0 1 2 2v13h-5a4 4 0 0 0-4 3 4 4 0 0 0-4-3z'/%3E%3C/svg%3E");

}



.imd-side a[href*="#first"]::before,

.imd-side a[href*="#containers"]::before,

.imd-side a[href*="#session"]::before {

    mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='3' width='18' height='7' rx='2'/%3E%3Crect x='3' y='14' width='18' height='7' rx='2'/%3E%3C/svg%3E");

}



.imd-side a[href*="#pentest"]::before {

    mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m4 6 6 6-6 6M12 18h8'/%3E%3C/svg%3E");

}



.imd-side a[href*="#troubleshooting"]::before {

    mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='9'/%3E%3Cpath d='M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4M12 17h.01'/%3E%3C/svg%3E");

}



.imd-side a[href*="/terms"]::before {

    mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z'/%3E%3Cpath d='m9 12 2 2 4-4'/%3E%3C/svg%3E");

}



/* Manual Book download */

.im-doc-manual {

    margin: 22px 0;

    padding: 18px;

    border: 1px solid #d7e6f7;

    border-radius: 13px;

    background: rgba(255,255,255,.72);

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 18px;

}



.im-doc-manual strong {

    display: block;

    font-size: 13px;

    color: #254469;

}



.im-doc-manual p {

    font-size: 11px;

    margin: 5px 0 0;

    color: #7a90af;

}



.im-doc-manual a,

.im-doc-manual .im-doc-unavailable {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding: 10px 13px;

    border-radius: 8px;

    font-size: 11px;

    font-weight: 700;

    white-space: nowrap;

    text-decoration: none;

}



.im-doc-manual a {

    background: #0a94d9;

    color: white;

}



.im-doc-manual .im-doc-unavailable {

    background: #e9eef5;

    color: #8191a8;

}



#im-v4[data-theme="dark"] .im-doc-manual {

    background: #1c2a43;

    border-color: rgba(139,169,218,.18);

}



#im-v4[data-theme="dark"] .im-doc-manual strong {

    color: #edf5ff;

}



#im-v4[data-theme="dark"] .im-doc-manual p {

    color: #a8b9d4;

}



#im-v4[data-theme="dark"] .im-doc-unavailable {

    background: #263650;

    color: #a8b9d4;

}



/* Header V6 dropdown theme compatibility */

#im-v4[data-theme="dark"] .im-student-menu,

#im-v4[data-theme="dark"] .im-student-dropdown-menu,

#im-v4[data-theme="dark"] [data-im-student-notifications] .im-student-menu {

    background: #1d2a43 !important;

    border-color: rgba(139,169,218,.20) !important;

    color: #edf5ff !important;

    box-shadow: 0 20px 55px rgba(0,0,0,.3) !important;

}



#im-v4[data-theme="dark"] .im-student-menu a,

#im-v4[data-theme="dark"] .im-student-menu button,

#im-v4[data-theme="dark"] .im-student-menu strong,

#im-v4[data-theme="dark"] .im-student-dropdown-menu a,

#im-v4[data-theme="dark"] .im-student-dropdown-menu button {

    color: #e5efff;

}



#im-v4[data-theme="dark"] .im-student-menu a:hover,

#im-v4[data-theme="dark"] .im-student-dropdown-menu a:hover {

    background: #293a58 !important;

}



#im-v4[data-theme="dark"] .im-student-menu small,

#im-v4[data-theme="dark"] .im-student-menu p,

#im-v4[data-theme="dark"] .im-student-menu span {

    color: #aebfda;

}



@media(max-width:600px) {

    .im-doc-manual {

        align-items: flex-start;

        flex-direction: column;

    }

}

</style>




<style>

/* IMNEHNET_DROPDOWN_DARK_V43 */



/* Dark mode: profile & notification dropdown */

#im-v4[data-theme="dark"] .im-student-dropdown,

#im-v4[data-theme="dark"] .im-student-menu,

#im-v4[data-theme="dark"] .im-student-profile-menu,

#im-v4[data-theme="dark"] .im-student-notification-menu,

#im-v4[data-theme="dark"] [data-im-student-profile] [role="menu"],

#im-v4[data-theme="dark"] [data-im-student-notifications] [role="menu"] {

    background-color: #1b2942 !important;

    border-color: #344561 !important;

    color: #edf4ff !important;

}



#im-v4[data-theme="dark"] .im-student-dropdown a,

#im-v4[data-theme="dark"] .im-student-dropdown button,

#im-v4[data-theme="dark"] .im-student-dropdown strong,

#im-v4[data-theme="dark"] .im-student-menu a,

#im-v4[data-theme="dark"] .im-student-menu button {

    color: #edf4ff !important;

}



#im-v4[data-theme="dark"] .im-student-dropdown small,

#im-v4[data-theme="dark"] .im-student-dropdown p,

#im-v4[data-theme="dark"] .im-student-dropdown span {

    color: #aebed6 !important;

}



#im-v4[data-theme="dark"] .im-student-dropdown a:hover,

#im-v4[data-theme="dark"] .im-student-menu a:hover {

    background-color: #293b59 !important;

}



/* Keep sign-out button recognizable */

#im-v4[data-theme="dark"] .im-student-dropdown a[href*="logout"],

#im-v4[data-theme="dark"] .im-student-dropdown button[type="submit"] {

    background-color: rgba(239, 68, 68, .13) !important;

    color: #ff8f9c !important;

}

</style>

