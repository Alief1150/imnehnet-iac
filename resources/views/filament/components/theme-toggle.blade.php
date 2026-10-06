<div
    x-data="{
        theme: localStorage.getItem('theme') || 'light',

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
>
    <button
        type="button"
        x-on:click="toggle()"
        class="btn btn-circle btn-text btn-sm"
        x-bind:aria-label="isDark() ? 'Switch to light mode' : 'Switch to dark mode'"
        x-bind:title="isDark() ? 'Light mode' : 'Dark mode'"
    >
        <x-heroicon-o-sun
            x-show="! isDark()"
            x-cloak
            class="size-5"
        />

        <x-heroicon-o-moon
            x-show="isDark()"
            x-cloak
            class="size-5"
        />
    </button>
</div>
