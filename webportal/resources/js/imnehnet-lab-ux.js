import Lenis from 'lenis';

let lenis = null;
let wrapper = null;
let animationStarted = false;

function initLenis() {
    const nextWrapper = document.querySelector('#im-v4-main');

    if (!nextWrapper) {
        if (lenis) lenis.destroy();
        lenis = null;
        wrapper = null;
        return;
    }

    if (wrapper === nextWrapper && lenis) return;

    if (lenis) lenis.destroy();

    wrapper = nextWrapper;

    // Lenis requires a valid scroll container.
    const content = wrapper.firstElementChild;

    if (!content) return;

    lenis = new Lenis({
        wrapper,
        content,
        duration: 1.05,
        smoothWheel: true,
        syncTouch: false,
        prevent: (node) => Boolean(
            node.closest(
                '[data-lenis-prevent], iframe, ' +
                '[role="dialog"], .im-terminal-fullscreen'
            )
        ),
    });
}

function animationLoop(time) {
    if (lenis) lenis.raf(time);
    requestAnimationFrame(animationLoop);
}

function initialize() {
    initLenis();

    if (!animationStarted) {
        animationStarted = true;
        requestAnimationFrame(animationLoop);
    }
}

document.addEventListener('DOMContentLoaded', initialize);
document.addEventListener('livewire:navigated', initialize);

if (document.readyState !== 'loading') initialize();
