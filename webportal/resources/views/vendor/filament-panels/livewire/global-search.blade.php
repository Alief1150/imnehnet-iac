<div
    class="im-search-trigger"
    x-data="{
        open: false,
        show() {
            this.open = true;
            this.$nextTick(() => this.$refs.searchInput?.focus());
        },
        close() {
            this.open = false;
        },
        navigate(direction) {
            const links = Array.from(
                this.$refs.dialog?.querySelectorAll('.im-search-link') ?? []
            ).filter(link => link.offsetParent !== null);

            if (! links.length) return;

            const index = links.indexOf(document.activeElement);
            const next = direction > 0
                ? (index + 1) % links.length
                : (index <= 0 ? links.length - 1 : index - 1);

            links[next]?.focus();
        },
        selectFirst() {
            this.$refs.dialog
                ?.querySelector('.im-search-link')
                ?.click();
        }
    }"
    x-on:imnehnet:search-open.window="show()"
    x-on:keydown.window="
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            this.show();
        }
        if (event.key === 'Escape' && this.open) {
            event.preventDefault();
            this.close();
        }
    "
>
    <button
        type="button"
        class="im-search-trigger-button"
        x-on:click="show()"
        aria-label="Open global search"
        aria-haspopup="dialog"
        x-bind:aria-expanded="open.toString()"
    >
        <span class="im-search-trigger-icon" aria-hidden="true">⌕</span>
        <span class="im-search-trigger-label">Search anything...</span>
        <kbd class="im-search-shortcut">Ctrl K</kbd>
    </button>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            class="im-search-overlay"
            x-on:click.self="close()"
            role="presentation"
        >
            <section
                x-ref="dialog"
                class="im-search-dialog"
                role="dialog"
                aria-modal="true"
                aria-label="Global search"
                x-on:click.stop
            >
                <div class="im-search-input-row">
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        width="21"
                        height="21"
                    >
                        <circle cx="10.8" cy="10.8" r="6.8"/>
                        <path d="m16 16 5 5"/>
                    </svg>

                    <input
                        x-ref="searchInput"
                        type="search"
                        autocomplete="off"
                        maxlength="250"
                        placeholder="Search anything in IMNEHNET..."
                        aria-label="Search IMNEHNET"
                        wire:model.live.debounce.300ms="search"
                        x-on:keydown.down.prevent="navigate(1)"
                        x-on:keydown.up.prevent="navigate(-1)"
                        x-on:keydown.enter.prevent="selectFirst()"
                        class="im-search-input"
                    />

                    <button
                        type="button"
                        class="im-search-close"
                        x-on:click="close()"
                        aria-label="Close search"
                    >✕</button>
                </div>

                <div class="im-search-tabs" aria-label="Search categories">
                    <span class="im-search-tab-active">All Results</span>
                    <span class="im-search-tab-hint">Pages · Environments · Users · Activity</span>
                </div>

                <div
                    class="im-search-results"
                    data-lenis-prevent
                    x-on:keydown.down.prevent="navigate(1)"
                    x-on:keydown.up.prevent="navigate(-1)"
                >
                    @if ($results === null)
                        <div class="im-search-group-title">QUICK ACCESS</div>

                        @foreach (\Filament\Facades\Filament::getPages() as $page)
                            @php
                                // A page whose route was never registered cannot
                                // produce a URL, and asking it for one would take
                                // down every page that carries this search. Such a
                                // page is skipped instead.
                                $pageUrl = rescue(fn (): ?string => $page::getUrl(), null, false);
                            @endphp
                            @if ($page::canAccess() && filled($pageUrl))
                                <a
                                    href="{{ $pageUrl }}"
                                    class="im-search-link"
                                    x-on:click="close()"
                                >
                                    <span class="im-search-result-icon">↗</span>
                                    <span class="im-search-result-content">
                                        <strong>{{ $page::getNavigationLabel() }}</strong>
                                        <small>Page</small>
                                    </span>
                                    <span class="im-search-result-arrow">↗</span>
                                </a>
                            @endif
                        @endforeach
                    @elseif ($results->getCategories()->isEmpty())
                        <div class="im-search-empty">
                            No results found. Try another keyword.
                        </div>
                    @else
                        @foreach ($results->getCategories() as $group => $groupedResults)
                            <div class="im-search-group">
                                <div class="im-search-group-title">
                                    {{ strtoupper($group) }}
                                </div>

                                @foreach ($groupedResults as $result)
                                    <a
                                        {{ \Filament\Support\generate_href_html($result->url) }}
                                        class="im-search-link"
                                        x-on:click="close()"
                                    >
                                        <span class="im-search-result-icon">↗</span>

                                        <span class="im-search-result-content">
                                            <strong>{{ $result->title }}</strong>

                                            @foreach ($result->details as $label => $value)
                                                <small>{{ $label }}: {{ $value }}</small>
                                            @endforeach
                                        </span>

                                        <span class="im-search-result-arrow">↗</span>
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    @endif
                </div>

                <footer class="im-search-footer">
                    <span><kbd>Esc</kbd> Close</span>
                    <span><kbd>↑</kbd> <kbd>↓</kbd> Navigate</span>
                    <span><kbd>Enter</kbd> Select</span>
                </footer>
            </section>
        </div>
    </template>
</div>
