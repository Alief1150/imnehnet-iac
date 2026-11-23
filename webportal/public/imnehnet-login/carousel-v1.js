(() => {
  'use strict';
  const mount = () => {
    document.querySelectorAll('[data-im-poster-slider]').forEach(slider => {
      if (slider.dataset.initialized === '1') return;
      slider.dataset.initialized = '1';
      const slides = [...slider.querySelectorAll('.im-poster-slide')];
      const dots = [...slider.querySelectorAll('.im-poster-dots button')];
      const next = slider.querySelector('.im-poster-next');
      let index = 0;
      let timer = null;
      const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      slides.forEach(slide => {
        const filename = slide.dataset.poster;
        const img = new Image();
        img.onload = () => {slide.querySelector('.im-poster-visual').style.backgroundImage = `url("/imnehnet-login/posters/${encodeURIComponent(filename)}")`;};
        img.src = `/imnehnet-login/posters/${encodeURIComponent(filename)}`;
      });
      const show = n => {
        index = (n + slides.length) % slides.length;
        slides.forEach((slide, i) => {
          const active = i === index;
          slide.classList.toggle('is-active', active);
          slide.setAttribute('aria-hidden', String(!active));
          slide.inert = !active;
          dots[i].classList.toggle('is-active', active);
          if (active) dots[i].setAttribute('aria-current','true'); else dots[i].removeAttribute('aria-current');
        });
      };
      const stop = () => {if (timer) {clearInterval(timer);timer=null;}};
      const start = () => {stop();if (!reduceMotion && !document.hidden) timer=setInterval(() => show(index+1), 6500);};
      next.addEventListener('click', () => {show(index+1);start();});
      dots.forEach((dot,i) => dot.addEventListener('click', () => {show(i);start();}));
      slider.addEventListener('mouseenter',stop);
      slider.addEventListener('mouseleave',start);
      slider.addEventListener('focusin',stop);
      slider.addEventListener('focusout',e => {if (!slider.contains(e.relatedTarget)) start();});
      document.addEventListener('visibilitychange', () => {if (document.hidden) stop(); else start();});
      show(0);start();
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',mount,{once:true}); else mount();
  document.addEventListener('livewire:navigated',mount);
})();
