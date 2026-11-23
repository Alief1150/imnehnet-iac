/* IMNEHNET_THEME_SAFE_V2 */
(() => {
    'use strict';

    const KEY = 'imnehnet-login-theme';

    function getTheme() {
        try {
            const saved = localStorage.getItem(KEY);
            if (saved === 'dark' || saved === 'light') {
                return saved;
            }
        } catch (_) {}

        return window.matchMedia &&
            window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';
    }

    function applyTheme(root, theme) {
        if (!root) return;

        root.dataset.theme = theme;

        const toggle = root.querySelector('.im-v5-theme');

        if (toggle) {
            toggle.setAttribute(
                'aria-label',
                theme === 'dark'
                    ? 'Switch to light mode'
                    : 'Switch to dark mode'
            );

            toggle.setAttribute(
                'title',
                theme === 'dark'
                    ? 'Light mode'
                    : 'Dark mode'
            );
        }
    }

    function init() {
        const root = document.querySelector('.im-v5');

        if (!root) return;

        applyTheme(root, getTheme());

        const toggle = root.querySelector('.im-v5-theme');

        if (!toggle || toggle.dataset.imThemeSafeBound === '1') {
            return;
        }

        toggle.dataset.imThemeSafeBound = '1';

        toggle.addEventListener('click', () => {
            const next =
                root.dataset.theme === 'dark' ? 'light' : 'dark';

            applyTheme(root, next);

            try {
                localStorage.setItem(KEY, next);
            } catch (_) {}
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {
            once: true
        });
    } else {
        init();
    }

    document.addEventListener('livewire:navigated', init);
})();
