@php
    $posters = [
        [
            'src' => asset('imnehnet-login/posters/2-imnehnet-poster-si-imut-dan-kawan-kawan.webp'),
            'alt' => 'IMNEHNET Si Imut dan Kawan-Kawan',
        ],
        [
            'src' => asset('imnehnet-login/posters/3-imnehnet-siap-jadi-praktisi-cyber-security.webp'),
            'alt' => 'IMNEHNET Siap Jadi Praktisi Cyber Security',
        ],
        [
            'src' => asset('imnehnet-login/posters/4-imnehnet-poster-web-server-breach.webp'),
            'alt' => 'IMNEHNET Web Server Breach',
        ],
    ];
@endphp

<div class="imn-carousel" data-imn-carousel
     role="region" aria-label="Poster IMNEHNET"
     aria-roledescription="carousel">

    <div class="imn-carousel__viewport">
        @foreach ($posters as $index => $poster)
            <div class="imn-carousel__slide {{ $index === 0 ? 'is-active' : '' }}"
                 data-imn-slide
                 aria-hidden="{{ $index === 0 ? 'false' : 'true' }}">
                <img src="{{ $poster['src'] }}"
                     alt="{{ $poster['alt'] }}"
                     loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                     decoding="async">
            </div>
        @endforeach
    </div>

    <div class="imn-carousel__controls">
        <button type="button"
                class="imn-carousel__arrow"
                data-imn-prev
                aria-label="Poster sebelumnya">
            &#10094;
        </button>

        <div class="imn-carousel__dots" aria-label="Pilih poster">
            @foreach ($posters as $index => $poster)
                <button type="button"
                        class="imn-carousel__dot {{ $index === 0 ? 'is-active' : '' }}"
                        data-imn-dot="{{ $index }}"
                        aria-label="Tampilkan poster {{ $index + 1 }}"
                        aria-current="{{ $index === 0 ? 'true' : 'false' }}">
                </button>
            @endforeach
        </div>

        <button type="button"
                class="imn-carousel__arrow"
                data-imn-next
                aria-label="Poster berikutnya">
            &#10095;
        </button>
    </div>
</div>

<style>
.imn-carousel {
    width: 100%;
    max-width: 100%;
    overflow: hidden;
    border-radius: 24px;
    background: #071a3c;
    padding: 10px;
    box-sizing: border-box;
}

.imn-carousel__viewport {
    position: relative;
    width: 100%;
    aspect-ratio: 2 / 3;
    overflow: hidden;
    border-radius: 18px;
}

.imn-carousel__slide {
    position: absolute;
    inset: 0;
    opacity: 0;
    visibility: hidden;
    transition: opacity 500ms ease, visibility 500ms ease;
}

.imn-carousel__slide.is-active {
    opacity: 1;
    visibility: visible;
}

.imn-carousel__slide img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.imn-carousel__controls {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px 4px;
}

.imn-carousel__dots {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.imn-carousel__dot {
    width: 9px;
    height: 9px;
    padding: 0;
    border: 0;
    border-radius: 999px;
    background: #7794be;
    cursor: pointer;
    transition: width 250ms ease, background 250ms ease;
}

.imn-carousel__dot.is-active {
    width: 26px;
    background: #27b6ff;
}

.imn-carousel__arrow {
    width: 36px;
    height: 36px;
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 50%;
    color: white;
    background: rgba(18,86,170,.35);
    cursor: pointer;
    font-size: 17px;
}

.imn-carousel__arrow:hover {
    background: #087efa;
}

.imn-carousel button:focus-visible {
    outline: 2px solid #fff;
    outline-offset: 3px;
}

@media (prefers-reduced-motion: reduce) {
    .imn-carousel__slide,
    .imn-carousel__dot {
        transition: none;
    }
}

/* IMNEHNET CAROUSEL FIT FIX START */

/* Fit carousel inside the login card */
.imn-carousel {
    position: relative !important;
    width: 100% !important;
    height: min(790px, calc(100dvh - 140px)) !important;
    max-height: calc(100dvh - 140px) !important;
    min-height: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
    overflow: hidden !important;
    box-sizing: border-box !important;
    border-radius: 24px !important;
}

/* Give the poster the full available height */
.imn-carousel__viewport {
    position: relative !important;
    width: 100% !important;
    height: 100% !important;
    aspect-ratio: auto !important;
    overflow: hidden !important;
    border-radius: inherit !important;
}

/* Prevent poster cropping */
.imn-carousel__slide {
    position: absolute !important;
    inset: 0 !important;
    width: 100% !important;
    height: 100% !important;
}

.imn-carousel__slide img {
    display: block !important;
    width: 100% !important;
    height: 100% !important;
    object-fit: contain !important;
    object-position: center center !important;
}

/* Overlay navigation at the bottom, without extra height */
.imn-carousel__controls {
    position: absolute !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    z-index: 20 !important;

    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;

    padding: 12px 18px !important;
    margin: 0 !important;

    background: linear-gradient(
        to top,
        rgba(4, 20, 57, .90),
        rgba(4, 20, 57, .45),
        transparent
    );
}

.imn-carousel__dots {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 10px !important;
}

.imn-carousel__arrow {
    flex-shrink: 0 !important;
    width: 36px !important;
    height: 36px !important;
}

/* Smaller screens */
@media (max-height: 700px) {
    .imn-carousel {
        height: calc(100dvh - 100px) !important;
        max-height: calc(100dvh - 100px) !important;
    }
}

@media (max-width: 768px) {
    .imn-carousel {
        height: min(70dvh, 680px) !important;
        max-height: 70dvh !important;
    }
}

/* IMNEHNET CAROUSEL FIT FIX END */


/* IMNEHNET THEME PATCH START */

/* Default: Dark Mode */
.imn-carousel {
    background: #071a3c !important;
    border: 1px solid rgba(255,255,255,.10) !important;
    transition: background 250ms ease, border-color 250ms ease;
}

.imn-carousel__controls {
    background: linear-gradient(
        to top,
        rgba(4,20,57,.90),
        rgba(4,20,57,.45),
        transparent
    ) !important;
}

.imn-carousel__arrow {
    background: rgba(18,86,170,.45) !important;
    color: #fff !important;
    border-color: rgba(255,255,255,.20) !important;
}

.imn-carousel__dot {
    background: #7794be !important;
}

.imn-carousel__dot.is-active {
    background: #27b6ff !important;
}

/* Light Mode */
html.light .imn-carousel,
html[data-theme="light"] .imn-carousel,
body.light .imn-carousel,
body[data-theme="light"] .imn-carousel {
    background: #f4f9ff !important;
    border-color: #c8dafa !important;
}

html.light .imn-carousel__controls,
html[data-theme="light"] .imn-carousel__controls,
body.light .imn-carousel__controls,
body[data-theme="light"] .imn-carousel__controls {
    background: linear-gradient(
        to top,
        rgba(221,237,255,.93),
        rgba(221,237,255,.35),
        transparent
    ) !important;
}

html.light .imn-carousel__arrow,
html[data-theme="light"] .imn-carousel__arrow,
body.light .imn-carousel__arrow,
body[data-theme="light"] .imn-carousel__arrow {
    background: #e5f2ff !important;
    color: #0865c7 !important;
    border-color: #a9cef5 !important;
}

html.light .imn-carousel__dot,
html[data-theme="light"] .imn-carousel__dot,
body.light .imn-carousel__dot,
body[data-theme="light"] .imn-carousel__dot {
    background: #b4c9e2 !important;
}

html.light .imn-carousel__dot.is-active,
html[data-theme="light"] .imn-carousel__dot.is-active,
body.light .imn-carousel__dot.is-active,
body[data-theme="light"] .imn-carousel__dot.is-active {
    background: #087efa !important;
}

/* IMNEHNET THEME PATCH END */


/* IMNEHNET FLYONUI THEME FIX START */

/* Light theme: outer poster card */
.im-v5[data-theme="light"] .im-v5-welcome,
.im-v5[data-theme="light"] .im-final-poster {
    background: #f2f7ff !important;
    border-color: #cbdcf5 !important;
}

/* Light theme: carousel surface */
.im-v5[data-theme="light"] .imn-carousel {
    background: #eaf4ff !important;
    border-color: #cbdcf5 !important;
}

/* Light theme: navigation overlay */
.im-v5[data-theme="light"] .imn-carousel__controls {
    background: linear-gradient(
        to top,
        rgba(224, 239, 255, .92),
        rgba(224, 239, 255, .40),
        transparent
    ) !important;
}

/* Light theme: arrows */
.im-v5[data-theme="light"] .imn-carousel__arrow {
    background: rgba(255,255,255,.9) !important;
    color: #0759b8 !important;
    border-color: #a8cef5 !important;
}

/* Light theme: pagination dots */
.im-v5[data-theme="light"] .imn-carousel__dot {
    background: #9ebcdd !important;
}

.im-v5[data-theme="light"] .imn-carousel__dot.is-active {
    background: #087efa !important;
}

/* Dark theme: outer poster card */
.im-v5[data-theme="dark"] .im-v5-welcome,
.im-v5[data-theme="dark"] .im-final-poster {
    background: #192740 !important;
    border-color: rgba(255,255,255,.12) !important;
}

/* Dark theme: carousel */
.im-v5[data-theme="dark"] .imn-carousel {
    background: #071a3c !important;
    border-color: rgba(255,255,255,.10) !important;
}

/* Smooth transition */
.im-v5-welcome,
.im-final-poster,
.imn-carousel,
.imn-carousel__arrow,
.imn-carousel__dot {
    transition:
        background-color 250ms ease,
        border-color 250ms ease,
        color 250ms ease;
}

/* IMNEHNET FLYONUI THEME FIX END */

</style>

<script>
(function () {
    function initCarousel(root) {
        if (root.dataset.initialized === '1') return;
        root.dataset.initialized = '1';

        const slides = [...root.querySelectorAll('[data-imn-slide]')];
        const dots = [...root.querySelectorAll('[data-imn-dot]')];

        if (slides.length < 2) return;

        let current = 0;
        let timer = null;

        function show(index) {
            current = (index + slides.length) % slides.length;

            slides.forEach((slide, i) => {
                const active = i === current;
                slide.classList.toggle('is-active', active);
                slide.setAttribute('aria-hidden', String(!active));
            });

            dots.forEach((dot, i) => {
                const active = i === current;
                dot.classList.toggle('is-active', active);
                dot.setAttribute('aria-current', String(active));
            });
        }

        function start() {
            clearInterval(timer);
            timer = setInterval(() => {
                if (document.hidden || !root.isConnected) {
                    if (!root.isConnected) clearInterval(timer);
                    return;
                }
                show(current + 1);
            }, 5000);
        }

        function navigate(index) {
            show(index);
            start();
        }

        root.querySelector('[data-imn-prev]')
            .addEventListener('click', () => navigate(current - 1));

        root.querySelector('[data-imn-next]')
            .addEventListener('click', () => navigate(current + 1));

        dots.forEach((dot, i) => {
            dot.addEventListener('click', () => navigate(i));
        });

        show(0);
        start();
    }

    function boot() {
        document.querySelectorAll('[data-imn-carousel]')
            .forEach(initCarousel);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
