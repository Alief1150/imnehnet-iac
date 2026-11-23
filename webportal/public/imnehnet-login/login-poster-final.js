(() => {
 function init() {
  document.querySelectorAll('.im-v5-welcome').forEach(panel => {
   const poster=panel.querySelector('.im-final-poster');
   const img=poster?.querySelector('img');
   if (!poster || !img) return;
   const show=()=>{ poster.hidden=false; panel.classList.add('im-final-has-poster'); };
   const hide=()=>{ poster.hidden=true; panel.classList.remove('im-final-has-poster'); };
   img.addEventListener('load',show);
   img.addEventListener('error',hide);
   if(img.complete) img.naturalWidth>0?show():hide();
  });
 }
 if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init,{once:true}); else init();
 document.addEventListener('livewire:navigated',init);
})();
