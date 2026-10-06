<div
    x-data="{
        theme: localStorage.getItem('theme') || '{{ filament()->getDefaultThemeMode()->value }}',

        isDark() {
            if (this.theme === 'system') {
                return window.matchMedia('(prefers-color-scheme: dark)').matches
            }

            return this.theme === 'dark'
        },

        toggle() {
            this.theme = this.isDark() ? 'light' : 'dark'
            localStorage.setItem('theme', this.theme)
            this.$dispatch('theme-changed', this.theme)
        },
    }"
    x-on:theme-changed.window="theme = $event.detail"
    class="im-theme-toggle"
>
    <button
        type="button"
        x-on:click="toggle()"
        class="im-theme-toggle-button"
        x-bind:aria-label="isDark() ? 'Switch to light theme' : 'Switch to dark theme'"
        x-bind:title="isDark() ? 'Light mode' : 'Dark mode'"
    >
        <svg
            x-show="! isDark()"
            x-cloak
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
            class="im-theme-icon"
        >
            <circle cx="12" cy="12" r="4"></circle>
            <path d="M12 2v2"></path>
            <path d="M12 20v2"></path>
            <path d="m4.93 4.93 1.42 1.42"></path>
            <path d="m17.66 17.66 1.41 1.41"></path>
            <path d="M2 12h2"></path>
            <path d="M20 12h2"></path>
            <path d="m6.34 17.66-1.41 1.41"></path>
            <path d="m19.07 4.93-1.41 1.41"></path>
        </svg>

        <svg
            x-show="isDark()"
            x-cloak
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
            class="im-theme-icon"
        >
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
        </svg>
    </button>
</div>
