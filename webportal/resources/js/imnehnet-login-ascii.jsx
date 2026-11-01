import React from 'react';
import { createRoot } from 'react-dom/client';
import GradientASCII from './components/GradientASCII';

const ROOT_ID = 'im-react-bits-ascii';

const gradientProps = {
    colors: ['#1e3fd8', '#f6f8ff', '#a5d8ff', '#38bdf8'],
    shape: 'horizon',
    characters: ' =+xX0S8#@',
    cellSize: 18,
    density: 0.8,
    glyphOpacity: 0.85,
    gradientOpacity: 0.9,
    glow: 0.2,
    depthOfField: 0,
    flicker: 0.2,
    softness: 0.5,
    scale: 1,
    flow: 0.5,
    speed: 1,
    grain: 0.08,
    variation: 0,
    mouseInteraction: true,
    scrambleRadius: 0.1,
    clickEffect: 'wave',
    intro: true,
    lightMode: false,
    dpr: 2,
};

let reactRoot = null;
let mountedElement = null;

function mountGradientASCII() {
    const element = document.getElementById(ROOT_ID);

    if (!element) {
        if (reactRoot) {
            reactRoot.unmount();
            reactRoot = null;
            mountedElement = null;
        }

        return;
    }

    if (reactRoot && mountedElement === element) {
        return;
    }

    if (reactRoot) {
        reactRoot.unmount();
    }

    reactRoot = createRoot(element);
    mountedElement = element;

    reactRoot.render(
        <GradientASCII {...gradientProps} />
    );

    console.info(
        '[IMNEHNET] React Bits GradientASCII mounted.'
    );
}

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        mountGradientASCII,
        { once: true }
    );
} else {
    mountGradientASCII();
}

document.addEventListener(
    'livewire:navigated',
    mountGradientASCII
);
