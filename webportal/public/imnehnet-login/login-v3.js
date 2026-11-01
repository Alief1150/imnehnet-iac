/* IMNEHNET_LOGIN_V3: presentation only, no auth mutation. */

(() => {
    'use strict';

    if (!/^\/(student|admin)\/login\/?$/.test(location.pathname)) {
        return;
    }

    function fixLayout() {
        const shell = document.querySelector('.fi-simple-layout');
        const main = document.querySelector('.fi-simple-main');

        if (!shell || !main) return;

        document.body.classList.add('im-login');

        // Identify the DIRECT child of Filament's layout
        // containing the login form.
        let column = main;

        while (
            column.parentElement &&
            column.parentElement !== shell
        ) {
            column = column.parentElement;
        }

        if (column === shell || column.parentElement !== shell) {
            console.warn(
                'IMNEHNET: Unexpected Filament login layout.'
            );
            return;
        }

        column.classList.add('im-form-column');

        // Previous V1 hero remains intact, but move it into
        // the same layout root as the form column.
        const hero = document.querySelector('.im-hero');

        if (hero && hero.parentElement !== shell) {
            shell.append(hero);
        }

        // Retire Canvas V1 and mount React Bits in its place.
        document.querySelectorAll('.im-ascii').forEach(node => {
            node.remove();
        });

        if (!document.getElementById('im-react-bits-ascii')) {
            const ascii = document.createElement('div');

            ascii.id = 'im-react-bits-ascii';
            ascii.setAttribute('aria-hidden', 'true');

            document.body.prepend(ascii);
        }
    }

    function initialize() {
        fixLayout();

        // React/Livewire can replace some page DOM.
        // Reapply only if the Filament layout was replaced.
        document.addEventListener('livewire:navigated', fixLayout);
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initialize,
            { once:true }
        );
    } else {
        initialize();
    }
})();
