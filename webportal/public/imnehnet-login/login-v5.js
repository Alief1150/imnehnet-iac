(() => {
    'use strict';

    function initialize() {
        const root = document.querySelector('.im-v5');
        if (!root) return;

        const theme = root.querySelector('.im-v5-theme');
        const stored = localStorage.getItem('imnehnet-login-theme');

        root.dataset.theme = stored ||
            (matchMedia('(prefers-color-scheme:dark)').matches
                ? 'dark' : 'light');

        if (theme && !theme.dataset.ready) {
            theme.dataset.ready = '1';
            theme.addEventListener('click', () => {
                const next = root.dataset.theme === 'dark'
                    ? 'light' : 'dark';
                root.dataset.theme = next;
                localStorage.setItem('imnehnet-login-theme', next);
            });
        }

        // Lucide icons: load only for this custom login page.
        if (!window.lucide) {
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js';
            script.onload = () => window.lucide?.createIcons({
                attrs: { 'stroke-width': 1.8 }
            });
            document.head.appendChild(script);
        } else {
            window.lucide.createIcons({
                attrs: { 'stroke-width': 1.8 }
            });
        }

        const next = root.querySelector('.im-v5-next');

        // Only one real poster is available in the handoff.
        // Do not fabricate additional posters.
        if (next) {
            next.disabled = true;
            next.setAttribute('aria-disabled','true');
            next.title = 'Additional posters coming soon';
            next.style.opacity = '.65';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded',initialize,{once:true});
    } else {
        initialize();
    }

    document.addEventListener('livewire:navigated',initialize);
})();
