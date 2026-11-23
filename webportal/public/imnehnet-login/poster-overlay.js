(() => {
  function init() {
    document.querySelectorAll('.im-v5-welcome').forEach(panel => {
      const overlay = panel.querySelector('.im-poster-overlay');
      const image = overlay?.querySelector('img');
      if (!overlay || !image) return;
      const reveal = () => { overlay.hidden = false; };
      const fallback = () => { overlay.hidden = true; };
      image.addEventListener('load', reveal, { once: true });
      image.addEventListener('error', fallback, { once: true });
      if (image.complete) image.naturalWidth > 0 ? reveal() : fallback();
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',init,{once:true});
  else init();
  document.addEventListener('livewire:navigated',init);
})();
