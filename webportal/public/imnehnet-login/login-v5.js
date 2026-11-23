(() => {
  'use strict';
  const SELECTOR = '.im-v5';
  const KEY = 'imnehnet-login-theme';
  let iconLoading = false;
  let observer = null;
  let scheduled = false;
  function root() { return document.querySelector(SELECTOR); }
  function readTheme() {
    try { const t = localStorage.getItem(KEY); if (t === 'dark' || t === 'light') return t; } catch (_) {}
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  function setTheme(el, value, persist) {
    el.dataset.theme = value;
    if (persist) { try { localStorage.setItem(KEY, value); } catch (_) {} }
  }
  function icons() {
    if (!root()) return;
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons({attrs:{'stroke-width':1.8}});
      return;
    }
    if (iconLoading || document.querySelector('script[data-imnehnet-lucide]')) return;
    iconLoading = true;
    const script = document.createElement('script');
    script.dataset.imnehnetLucide = '1';
    script.src = 'https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js';
    script.onload = () => { iconLoading = false; icons(); };
    script.onerror = () => { iconLoading = false; script.remove(); };
    document.head.appendChild(script);
  }
  function initialize() {
    const el = root();
    if (!el) { if (observer) {observer.disconnect(); observer=null;} return; }
    if (el.dataset.theme !== 'dark' && el.dataset.theme !== 'light') setTheme(el,readTheme(),false);
    const toggle = el.querySelector('.im-v5-theme');
    if (toggle && toggle.dataset.imnehnetV8 !== '1') {
      toggle.dataset.imnehnetV8 = '1';
      toggle.addEventListener('click', () => setTheme(el, el.dataset.theme === 'dark' ? 'light' : 'dark', true));
    }
    const next = el.querySelector('.im-v5-next');
    if (next) {
      next.disabled=true; next.setAttribute('aria-disabled','true');
      next.title='Additional posters coming soon'; next.style.opacity='.65';
    }
    icons();
    if (!observer) {
      observer = new MutationObserver(records => {
        if (!root()) { observer.disconnect(); observer=null; return; }
        const changed = records.some(r => Array.from(r.addedNodes).some(n => n.nodeType === 1 && (n.matches?.('[data-lucide], .fi-fo-field-wrp, .fi-input-wrp') || n.querySelector?.('[data-lucide]'))));
        if (changed && !scheduled) {
          scheduled=true;
          queueMicrotask(() => { scheduled=false; icons(); });
        }
      });
      observer.observe(el,{subtree:true,childList:true});
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',initialize,{once:true});
  else initialize();
  document.addEventListener('livewire:navigated',initialize);
  document.addEventListener('livewire:initialized',initialize);
  document.addEventListener('livewire:commit', () => queueMicrotask(initialize));
})();
