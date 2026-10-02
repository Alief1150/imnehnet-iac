/**
 * Smooth scrolling for the main content area only.
 *
 * The specification is explicit about what this must not touch: the terminal,
 * table-internal scrolling, the sidebar, modals, dropdowns, code blocks and
 * textareas. All of those are surfaces where smooth scrolling is not merely
 * unwanted but actively harmful — in a shell it makes it impossible to find
 * the line you just scrolled past, and in a dropdown it fights the menu's own
 * positioning.
 *
 * Two mechanisms keep it safe:
 *
 * 1. Elements marked data-lenis-prevent are excluded by Lenis itself. Anything
 *    that scrolls independently carries that attribute, so this needs no
 *    per-surface JavaScript and cannot be forgotten silently.
 *
 * 2. The instance is stopped while the pointer is over an independently
 *    scrollable region or a text field is focused, and started again after.
 *    Belt and braces, because a nested scroller that merely has the attribute
 *    can still capture the wheel event before Lenis sees it.
 */
import Lenis from 'lenis';

const REDUCED_MOTION = window.matchMedia('(prefers-reduced-motion: reduce)');

let lenis = null;

/**
 * True when the event happened inside something that manages its own
 * scrolling or captures the keyboard.
 */
function isExcluded(target) {
    if (!(target instanceof Element)) {
        return false;
    }

    if (target.closest('[data-lenis-prevent]')) {
        return true;
    }

    if (target.closest('textarea, [contenteditable="true"], input[type="text"]')) {
        return true;
    }

    // A dropdown or a modal owns its own scroll position while it is open.
    if (target.closest('.fi-modal-window, .fi-dropdown-panel')) {
        return true;
    }

    return false;
}

function destroy() {
    lenis?.destroy();
    lenis = null;
}

function start() {
    if (REDUCED_MOTION.matches) {
        // Honouring the preference means not starting at all, rather than
        // starting and then stopping: a stopped instance still measures and
        // still installs listeners.
        destroy();
        return;
    }

    if (lenis !== null) {
        return;
    }

    lenis = new Lenis({
        // Smoothness without lag. Anything longer makes the interface feel
        // like it is lagging behind the pointer.
        duration: 0.28,
        // Wheel input is not hijacked, so a trackpad still feels native.
        smoothWheel: true,
        syncTouch: false,
        autoRaf: false,
    });

    const raf = (time) => {
        lenis?.raf(time);
        requestAnimationFrame(raf);
    };

    requestAnimationFrame(raf);

    document.addEventListener('wheel', (event) => {
        if (isExcluded(event.target)) {
            lenis?.stop();
        }
    }, { passive: true });

    // Scrolling resumes once the pointer leaves the excluded region.
    document.addEventListener('mouseout', (event) => {
        if (event.relatedTarget instanceof Node && isExcluded(event.relatedTarget)) {
            return;
        }

        lenis?.start();
    }, { passive: true });

    document.addEventListener('focusin', (event) => {
        if (event.target instanceof Element && isExcluded(event.target)) {
            lenis?.stop();
        }
    });

    document.addEventListener('focusout', () => {
        lenis?.start();
    });

    // A preference change at runtime is honoured without a reload.
    REDUCED_MOTION.addEventListener('change', () => {
        if (REDUCED_MOTION.matches) {
            destroy();
        } else {
            start();
        }
    });
}

/**
 * Filament and Livewire replace parts of the page without a full load, so the
 * scroll position is restored rather than reset when navigation happens. Without
 * this, moving between pages leaves the previous page's offset in place and the
 * new page appears mid-way down.
 */
document.addEventListener('livewire:navigated', () => {
    lenis?.scrollTo(0, { immediate: true });
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
} else {
    start();
}

window.imnehnetLenis = {
    /** Exposed so a surface can opt out deliberately rather than by accident. */
    stop: () => lenis?.stop(),
    start: () => lenis?.start(),
    isRunning: () => lenis !== null,
};
