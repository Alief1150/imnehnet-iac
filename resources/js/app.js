import ApexCharts from 'apexcharts';
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


/* ============================================================
 * IMNEHNET Dashboard Charts
 * FlyonUI visual system + ApexCharts
 * ============================================================ */

window.ApexCharts = ApexCharts;

function imnehnetIsDark() {
    return document.documentElement.classList.contains('dark');
}

function syncFlyonTheme() {
    document.documentElement.dataset.theme = imnehnetIsDark() ? 'dark' : 'light';
}

syncFlyonTheme();

window.addEventListener('theme-changed', () => {
    requestAnimationFrame(syncFlyonTheme);
});

const themeObserver = new MutationObserver(syncFlyonTheme);

themeObserver.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class'],
});

function chartPalette() {
    const dark = imnehnetIsDark();

    return {
        mode: dark ? 'dark' : 'light',
        text: dark ? '#94a3b8' : '#64748b',
        grid: dark ? '#273244' : '#e2e8f0',
        surface: dark ? '#111827' : '#ffffff',
        primary: '#0ea5a4',
        info: '#3b82f6',
        warning: '#f59e0b',
    };
}

window.createImnehnetLabChart = (element, labels, values) => {
    const palette = chartPalette();

    const chart = new ApexCharts(element, {
        chart: {
            type: 'area',
            height: 260,
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 500,
            },
            fontFamily: 'Instrument Sans, sans-serif',
            background: 'transparent',
        },
        series: [{
            name: 'Lab Sessions',
            data: labels.map((label, index) => ({
                x: label,
                y: values[index] ?? 0,
            })),
        }],
        xaxis: {
            type: 'category',
            tickAmount: labels.length <= 7 ? labels.length - 1 : 4,
            labels: {
                rotate: 0,
                hideOverlappingLabels: true,
                formatter: (value, timestamp, opts) => {
                    const index = opts?.i ?? -1;
                    const last = labels.length - 1;

                    let indexes;

                    if (labels.length <= 7) {
                        indexes = labels.map((_, i) => i);
                    } else {
                        indexes = [
                            0,
                            Math.round(last * 0.25),
                            Math.round(last * 0.50),
                            Math.round(last * 0.75),
                            last,
                        ];
                    }

                    return indexes.includes(index)
                        ? (labels[index] ?? value)
                        : '';
                },
                style: {
                    colors: palette.text,
                    fontSize: '11px',
                },
            },
            axisBorder: { show: false },
            axisTicks: { show: false },
            tooltip: { enabled: false },
        },
        yaxis: {
            min: 0,
            forceNiceScale: true,
            labels: {
                formatter: (value) => Math.round(value),
                style: {
                    colors: [palette.text],
                    fontSize: '11px',
                },
            },
        },
        stroke: {
            curve: 'smooth',
            width: 2.5,
        },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.38,
                opacityTo: 0.05,
                stops: [0, 85, 100],
            },
        },
        colors: [palette.primary],
        dataLabels: { enabled: false },
        markers: {
            size: 0,
            hover: { size: 5 },
        },
        grid: {
            borderColor: palette.grid,
            strokeDashArray: 3,
            padding: {
                left: 6,
                right: 10,
            },
        },
        tooltip: {
            theme: palette.mode,
            x: { show: true },
        },
        theme: {
            mode: palette.mode,
        },
    });

    chart.render();

    const updateTheme = () => {
        requestAnimationFrame(() => {
            const next = chartPalette();

            chart.updateOptions({
                theme: { mode: next.mode },
                grid: { borderColor: next.grid },
                xaxis: {
                    labels: {
                        style: {
                            colors: next.text,
                            fontSize: '11px',
                        },
                    },
                },
                yaxis: {
                    labels: {
                        style: {
                            colors: [next.text],
                            fontSize: '11px',
                        },
                    },
                },
                tooltip: { theme: next.mode },
            }, false, true);
        });
    };

    window.addEventListener('theme-changed', updateTheme);

    return {
        destroy() {
            window.removeEventListener('theme-changed', updateTheme);
            chart.destroy();
        },
    };
};

window.createImnehnetCourseChart = (element, values) => {
    const palette = chartPalette();

    const chart = new ApexCharts(element, {
        chart: {
            type: 'donut',
            height: 250,
            fontFamily: 'Instrument Sans, sans-serif',
            background: 'transparent',
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 500,
            },
        },
        series: values,
        labels: ['Completed', 'In Progress', 'Not Started'],
        colors: [
            palette.primary,
            palette.info,
            palette.warning,
        ],
        stroke: {
            width: 0,
        },
        dataLabels: {
            enabled: false,
        },
        legend: {
            show: false,
        },
        tooltip: {
            theme: palette.mode,
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '72%',
                },
            },
        },
        states: {
            hover: {
                filter: {
                    type: 'lighten',
                    value: 0.04,
                },
            },
        },
        theme: {
            mode: palette.mode,
        },
    });

    chart.render();

    const updateTheme = () => {
        requestAnimationFrame(() => {
            const next = chartPalette();

            chart.updateOptions({
                theme: { mode: next.mode },
                tooltip: { theme: next.mode },
            }, false, true);
        });
    };

    window.addEventListener('theme-changed', updateTheme);

    return {
        destroy() {
            window.removeEventListener('theme-changed', updateTheme);
            chart.destroy();
        },
    };
};
