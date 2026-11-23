{{-- IMNEHNET_STUDENT_SHELL_UNIFIED_20261122: CSS/JS applied once per page --}}
<style id="imnehnet-student-shell-css">
#im-v4 .im-sh-brand{display:flex;align-items:center;gap:11px;text-decoration:none;min-width:0}
#im-v4 .im-sh-logo{width:45px;height:45px;object-fit:contain;flex:none}
#im-v4 .im-sh-brand-text{display:flex;flex-direction:column;min-width:0;gap:2px}
#im-v4 .im-sh-brand-text strong{font-size:18px;letter-spacing:.025em;font-weight:800;line-height:1.15}
#im-v4 .im-sh-brand-text small{font-size:10px;opacity:.7}
#im-v4 .im-sh-mobile-brand{display:flex;align-items:center;gap:8px;text-decoration:none}
#im-v4 .im-sh-mobile-brand img{width:31px;height:31px;object-fit:contain}
#im-v4 .im-sh-actions{display:flex;align-items:center;gap:10px}
#im-v4 .im-sh-control{position:relative;display:inline-flex;align-items:center}
#im-v4 .im-sh-trigger{position:relative;display:grid;place-items:center}
#im-v4 .im-sh-trigger svg{width:19px;height:19px;fill:none!important;stroke:currentColor!important}
#im-v4 .im-sh-unread{position:absolute;right:2px;top:1px;width:8px;height:8px;border-radius:50%;background:#f25e72;border:1px solid currentColor}
#im-v4 .im-sh-unread[hidden],.im-sh-panel[hidden]{display:none!important}
.im-sh-panel{position:fixed;z-index:2147483000;width:min(330px,calc(100vw - 24px));max-height:70vh;overflow:auto;border-radius:14px;border:1px solid #dce4ef;background:#fff;color:#26334d;box-shadow:0 20px 48px #0003;padding:7px}
.im-sh-panel[data-sh-panel=theme]{width:170px}
.im-sh-panel button{cursor:pointer}
.im-sh-theme-panel button{width:100%;background:transparent;border:0;text-align:left;border-radius:8px;padding:10px 12px;font-size:13px;color:inherit}
.im-sh-theme-panel button[aria-checked=true],.im-sh-theme-panel button:hover{background:#e2eefb;color:#196cac}
.im-sh-profile-trigger{display:flex;gap:9px;align-items:center;border:0;background:transparent;color:inherit;cursor:pointer;font:inherit;text-align:left}
.im-sh-profile-trigger>svg{width:13px;height:13px;opacity:.65}
.im-sh-avatar{height:35px;width:35px;display:grid;place-items:center;border-radius:50%;overflow:hidden;background:linear-gradient(135deg,#41d3ce,#5983e1);color:#fff;font-size:13px;font-weight:750;flex-shrink:0}
.im-sh-avatar img{height:100%;width:100%;object-fit:cover}
.im-sh-user-meta{display:flex;flex-direction:column;gap:2px;max-width:145px}
.im-sh-user-meta strong{font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.im-sh-user-meta small{font-size:10px;opacity:.65}
.im-sh-profile-heading{display:flex;align-items:center;gap:12px;border-bottom:1px solid #9ca3af33;padding:13px}
.im-sh-profile-heading strong,.im-sh-profile-heading small{display:block;font-size:12px}
.im-sh-profile-heading small{margin-top:4px;font-size:10px;opacity:.65;overflow-wrap:anywhere}
.im-sh-profile-links{display:grid;gap:2px;padding:7px}
.im-sh-profile-links a{display:flex;align-items:center;gap:11px;padding:10px 11px;text-decoration:none;color:inherit;border-radius:8px;font-size:12px;font-weight:600}
.im-sh-profile-links a:hover,.im-sh-profile-links a[aria-current=page]{background:#7eafc329}
.im-sh-profile-links svg{height:17px;width:17px}
.im-sh-profile-panel form{padding:9px;border-top:1px solid #9ca3af33}
.im-sh-logout{width:100%;border:0;border-radius:9px;background:#f2577121;color:#e66578;font-weight:700;font-size:12px;padding:11px}
.im-sh-panel-head{display:flex;justify-content:space-between;align-items:center;gap:6px;padding:11px;border-bottom:1px solid #9ca3af33}
.im-sh-panel-head strong{font-size:13px}.im-sh-panel-head button{background:transparent;border:0;color:#26a5aa;font-size:11px;font-weight:650}
.im-sh-empty{text-align:center;font-size:12px;padding:18px;opacity:.7}
.im-sh-panel.im-sh-dark .im-sh-theme-panel button:hover,.im-sh-panel.im-sh-dark .im-sh-theme-panel button[aria-checked=true]{background:#334967}
#im-v4 .im-sh-sidebar .im-sh-nav-link{display:flex;align-items:center;gap:13px;text-decoration:none}
#im-v4 .im-sh-sidebar .im-sh-nav-link svg{flex:none;width:18px;height:18px}
@media(max-width:720px){#im-v4 .im-sh-user-meta{display:none}#im-v4 .im-sh-brand-text strong{font-size:15px}#im-v4 .im-sh-logo{height:35px;width:35px}}


/* IMNEHNET_SHELL_CLEANUP_20261122: only new navigation component */
#im-v4 .im-sh-sidebar .im-sh-nav-link{display:flex;align-items:center;gap:13px;padding:12px 13px;border:1px solid transparent;border-radius:11px;text-decoration:none;color:inherit;opacity:.85;transition:background-color .16s,color .16s,border-color .16s}
#im-v4 .im-sh-sidebar .im-sh-nav-link svg{width:18px;height:18px;flex:none}
#im-v4 .im-sh-sidebar .im-sh-nav-link:hover{opacity:1;background:rgba(50,190,182,.09)}
#im-v4 .im-sh-sidebar .im-sh-nav-link[aria-current="page"]{opacity:1;background:rgba(25,184,177,.19);color:#49d7d0;border-color:rgba(52,211,204,.35);font-weight:750}
#im-v4[data-theme="light"] .im-sh-sidebar .im-sh-nav-link[aria-current="page"]{background:#c7f0f4;color:#087d88;border-color:#8ddde4}
#im-v4 .im-sh-topbar .im-sh-trigger[aria-expanded="true"],#im-v4 .im-sh-topbar .im-sh-profile-trigger[aria-expanded="true"]{border-radius:9px;background:rgba(73,147,210,.16)}
#im-v4 .im-sh-mobile-toggle{display:none;align-items:center;justify-content:center;width:38px;height:38px;background:transparent;border:0;border-radius:9px;color:inherit;cursor:pointer;flex:none}
#im-v4 .im-sh-mobile-toggle svg{height:20px;width:20px}
.im-sh-mobile-scrim{display:none}
@media(max-width:900px){
#im-v4 .im-sh-mobile-toggle{display:inline-flex}
#im-v4 .im-v4-sidebar.im-sh-sidebar{display:flex;position:fixed;top:0;left:0;height:100dvh;width:min(285px,85vw);z-index:2147482001;transform:translateX(-105%);transition:transform .2s ease;overflow-y:auto}
#im-v4.im-sh-menu-open .im-v4-sidebar.im-sh-sidebar{transform:translateX(0)}
#im-v4 .im-v4-workspace{width:100%;margin-left:0;min-width:0}
#im-v4 .im-sh-topbar{display:flex;align-items:center;gap:9px;padding-inline:12px}
#im-v4 .im-sh-topbar .im-v4-search-btn{min-width:0;flex:1 1 auto}
#im-v4 .im-sh-topbar .im-sh-actions{display:flex;align-items:center;gap:5px;flex-shrink:0}
#im-v4 .im-sh-topbar .im-sh-user-meta{display:none}
.im-sh-mobile-scrim{display:block;position:fixed;inset:0;background:rgba(0,0,0,.57);z-index:2147482000}
.im-sh-mobile-scrim[hidden]{display:none}
}
@media(max-width:520px){
#im-v4 .im-sh-topbar .im-v4-search-btn span,#im-v4 .im-sh-topbar .im-v4-search-btn kbd{display:none}
#im-v4 .im-sh-topbar .im-v4-search-btn{flex:1 1 32px;justify-content:center;max-width:50px}
.im-sh-panel{max-width:calc(100vw - 24px);max-height:calc(100dvh - 24px)}
}
@media(prefers-reduced-motion:reduce){#im-v4 .im-v4-sidebar.im-sh-sidebar{transition:none}}
/* IMNEHNET_UI_REVISION_V5: canonical notification styling, no legacy item rules */
.im-sh-notification-panel{box-sizing:border-box;display:flex;flex-direction:column;width:min(360px,calc(100vw - 24px));max-height:min(72dvh,540px);padding:0;overflow:hidden}
.im-sh-panel[hidden],.im-sh-notification-panel[hidden]{display:none!important}
.im-sh-notification-panel .im-sh-panel-head{flex:none;gap:12px;padding:12px 14px}
.im-sh-notification-panel [data-sh-readall]{position:relative;z-index:1;cursor:pointer;min-height:32px;padding:4px 6px;color:#39c7be}
.im-sh-notification-panel [data-sh-readall]:disabled{opacity:.45;cursor:default}
.im-sh-notification-items{flex:1 1 auto;min-height:0;overflow-y:auto;overscroll-behavior:contain;touch-action:pan-y;-webkit-overflow-scrolling:touch}
.im-sh-notification-item{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;text-decoration:none;color:inherit;border-bottom:1px solid #9ca3af22}
.im-sh-notification-item:hover{background:#7eafc31c}
.im-sh-notification-item.unread{border-left:3px solid #35c8ba}
.im-sh-notif-icon{display:grid;place-items:center;flex:none;width:27px;height:27px;border-radius:8px;background:#21b4ab20;color:#4ed5ce;font-weight:800}
.im-sh-notif-body{display:block;min-width:0;flex:1}
.im-sh-notif-body strong{display:block;font-size:12px;line-height:1.5;overflow-wrap:anywhere}
.im-sh-notif-body small{display:block;font-size:10px;opacity:.65;margin-top:4px}
@media(max-width:520px){.im-sh-notification-panel{width:calc(100vw - 24px);max-height:70dvh}}
#im-v4 .im-student-pentest:has(.im-sh-pentest-empty){min-height:calc(100dvh - 170px);display:flex;justify-content:center;align-items:center}
#im-v4 .im-sh-pentest-empty{width:min(100%,650px);text-align:center;padding:34px 25px;border-radius:18px}
#im-v4 .im-sh-pentest-empty h2{font-size:23px;margin-bottom:12px}
#im-v4 .im-sh-pentest-empty p{line-height:1.6;margin:0 auto 20px;max-width:500px}
#im-v4 .im-sh-pentest-empty .pc-primary{display:inline-flex;align-items:center;justify-content:center;min-height:44px;text-decoration:none}

/* IMNEHNET_PANEL_THEME_CANONICAL_V7: one theme authority for detached dropdowns. */
html[data-imnehnet-student-theme="dark"] .im-sh-panel {
  background:#1c2942 !important; color:#eaf0ff !important;
  border-color:#384969 !important; color-scheme:dark;
}
html[data-imnehnet-student-theme="light"] .im-sh-panel {
  background:#ffffff !important; color:#26334d !important;
  border-color:#dae2ee !important; color-scheme:light;
}
html[data-imnehnet-student-theme="light"] .im-sh-panel .im-sh-profile-links a,
html[data-imnehnet-student-theme="light"] .im-sh-panel .im-sh-theme-panel button,
html[data-imnehnet-student-theme="light"] .im-sh-panel .im-sh-profile-heading,
html[data-imnehnet-student-theme="light"] .im-sh-panel .im-sh-notification-item {
  color:#26334d !important;
}
html[data-imnehnet-student-theme="light"] .im-sh-panel .im-sh-profile-links a:hover,
html[data-imnehnet-student-theme="light"] .im-sh-panel .im-sh-profile-links a[aria-current="page"],
html[data-imnehnet-student-theme="light"] .im-sh-panel .im-sh-theme-panel button[aria-checked="true"] {
  background:#e8f4f7 !important; color:#087d88 !important;
}
html[data-imnehnet-student-theme="dark"] .im-sh-panel .im-sh-profile-links a,
html[data-imnehnet-student-theme="dark"] .im-sh-panel .im-sh-theme-panel button,
html[data-imnehnet-student-theme="dark"] .im-sh-panel .im-sh-notification-item {
  color:#eaf0ff !important;
}
</style>
<script data-imnehnet-student-shell="20261122">
/* IMNEHNET_UI_REVISION_V5 - single shared theme/menu/notification controller */
(function () {
 const KEY = 'imnehnet-student-theme-choice';
 const media = matchMedia('(prefers-color-scheme: dark)');
 const root = () => document.getElementById('im-v4');
 const panel = name => document.querySelector('[data-sh-panel="'+name+'"]');
 const trigger = name => document.querySelector('[data-sh-toggle="'+name+'"]');
 function choice(){try {const v=localStorage.getItem(KEY);return ['light','dark','system'].includes(v)?v:'system'}catch(_){return 'system'}}
 function store(v){try{localStorage.setItem(KEY,v)}catch(_){}}
 let applyingTheme=false;
 function applyTheme(){const r=root();if(!r)return;const mode=choice()==='system'?(media.matches?'dark':'light'):choice();
   if(r.dataset.theme!==mode){applyingTheme=true;r.dataset.theme=mode;applyingTheme=false}
   document.documentElement.dataset.imnehnetStudentTheme=mode;
   document.querySelectorAll('.im-sh-panel').forEach(p=>p.classList.toggle('im-sh-dark',mode==='dark'));
   document.querySelectorAll('[data-sh-theme]').forEach(b=>b.setAttribute('aria-checked',String(b.dataset.shTheme===choice())));
 }
 const watcher=new MutationObserver(ms=>{if(!applyingTheme&&ms.some(m=>m.attributeName==='data-theme'))applyTheme()});
 let watched=null;
 function watch(){const r=root();if(r&&r!==watched){watcher.disconnect();watcher.observe(r,{attributes:true,attributeFilter:['data-theme']});watched=r}applyTheme()}
 function close(){document.querySelectorAll('.im-sh-panel').forEach(p=>p.hidden=true);document.querySelectorAll('[data-sh-toggle]').forEach(b=>b.setAttribute('aria-expanded','false'))}
 let portalOwner=null;
 function portals(){
   const header=document.querySelector('#im-v4 .im-sh-topbar');
   if(header!==portalOwner){
     document.querySelectorAll('body > .im-sh-panel').forEach(p=>p.remove());
     portalOwner=header;
   }
   if(!header)return;
   header.querySelectorAll('.im-sh-panel').forEach(p=>document.body.appendChild(p));
 }
 function place(p,b){const r=b.getBoundingClientRect(),w=Math.min(p.offsetWidth,innerWidth-24),h=p.offsetHeight;
   p.style.left=Math.max(12,Math.min(r.right-w,innerWidth-w-12))+'px';
   p.style.top=(r.bottom+8+h<=innerHeight-12?r.bottom+8:Math.max(12,r.top-h-8))+'px';
 }
 function mobile(open){const r=root(),scrim=document.querySelector('[data-sh-mobile-scrim]'),button=document.querySelector('[data-sh-mobile-toggle]');if(!r||!scrim||!button)return;
   r.classList.toggle('im-sh-menu-open',open);scrim.hidden=!open;button.setAttribute('aria-expanded',String(open));document.body.style.overflow=open?'hidden':'';
 }
 const api='/student/ui-notifications';let fetching=false;
 async function send(path){const token=document.querySelector('meta[name="csrf-token"]')?.content||document.querySelector('[data-sh-csrf]')?.dataset.shCsrf||'';
   const res=await fetch(api+path,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':token,'X-Requested-With':'XMLHttpRequest'}});
   if(!res.ok)throw new Error('HTTP '+res.status);
 }
 function summary(n){const title=String(n.title||'').trim(),msg=String(n.message||'').trim(),type=String(n.type||'').toLowerCase(),s=(title+' '+msg).toLowerCase();
   if(type==='lab_ready'||/lab siap/.test(s))return ['✓','Lab siap digunakan'];
   if(/deleted by an administrator|dihapus oleh administrator/.test(s))return ['×','Lab dihapus oleh administrator'];
   if(/hapus|deleted|dihapus/.test(s))return ['×','Lab berhasil dihapus'];
   if(/fail|gagal|error/.test(s))return ['!','Operasi lab gagal'];
   if(/stopp|dihentikan/.test(s))return ['Ⅱ','Lab dihentikan'];
   if(/complet|selesai/.test(s))return ['✓','CTF berhasil diselesaikan'];
   const text=(title||msg||'Informasi lab terbaru').split(/[.!?]\s/)[0];return ['•',text.length>65?text.slice(0,62).trimEnd()+'…':text];
 }
 function whenText(n){return String(n.when||'').replace(/(\d+) (seconds?|minutes?|hours?|days?) ago/gi,(_,count,unit)=>{
   if(unit.startsWith('second'))return 'baru saja';return count+' '+({minute:'menit',hour:'jam',day:'hari'}[unit.replace(/s$/,'')]||unit)+' lalu';
 })}
 function notificationNode(n){const a=document.createElement('a');a.className='im-sh-notification-item'+(n.read?'':' unread');
   let href='/student/my-labs';try{const u=new URL(n.url||href,location.origin);if(u.origin===location.origin&&(u.pathname==='/student'||u.pathname.startsWith('/student/')))href=u.pathname+u.search}catch(_){}
   a.href=href;const [glyph,label]=summary(n);
   const ico=document.createElement('span');ico.className='im-sh-notif-icon';ico.setAttribute('aria-hidden','true');ico.textContent=glyph;
   const body=document.createElement('span');body.className='im-sh-notif-body';const name=document.createElement('strong');name.textContent=label;
   const when=document.createElement('small');when.textContent=whenText(n);body.append(name,when);a.append(ico,body);
   a.addEventListener('click',async e=>{if(n.read)return;e.preventDefault();try{await send('/'+encodeURIComponent(n.id)+'/read')}catch(_){}location.assign(href)});
   return a;
 }
 async function load(){if(fetching||!root())return;fetching=true;
   try{const res=await fetch(api,{credentials:'same-origin',headers:{Accept:'application/json'}});if(!res.ok)throw Error('HTTP '+res.status);
     const data=await res.json(),count=Number(data.unread)||0;
     document.querySelectorAll('[data-sh-unread]').forEach(el=>el.hidden=count===0);
     const host=document.querySelector('[data-sh-items]');if(host){host.replaceChildren();for(const n of (data.notifications||[]))host.append(notificationNode(n));
       if(!host.children.length){const empty=document.createElement('p');empty.className='im-sh-empty';empty.textContent='Belum ada notifikasi';host.append(empty)} }
     const readall=document.querySelector('[data-sh-readall]');if(readall)readall.disabled=count===0;
   }catch(_){const host=document.querySelector('[data-sh-items]');if(host&&!host.children.length)host.textContent='Notifikasi belum bisa dimuat'}finally{fetching=false}
 }
 function bind(){if(!root())return;portals();watch();
   document.querySelectorAll('[data-sh-toggle]').forEach(b=>{if(b.dataset.shBound)return;b.dataset.shBound='1';b.addEventListener('click',e=>{
     e.preventDefault();e.stopPropagation();const p=panel(b.dataset.shToggle),open=!!p?.hidden;close();if(open&&p){p.hidden=false;place(p,b);b.setAttribute('aria-expanded','true');if(b.dataset.shToggle==='notifications')load()}
   })});
   document.querySelectorAll('[data-sh-theme]').forEach(b=>{if(b.dataset.shBound)return;b.dataset.shBound='1';b.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();store(b.dataset.shTheme);applyTheme();close()})});
   const readall=document.querySelector('[data-sh-readall]');if(readall&&!readall.dataset.shBound){readall.dataset.shBound='1';readall.addEventListener('click',async e=>{
     e.preventDefault();e.stopPropagation();if(readall.disabled)return;readall.disabled=true;
     try{await send('/read-all');await load()}catch(_){readall.textContent='Coba lagi'}finally{if(readall.textContent!=='Tandai semua dibaca')readall.textContent='Tandai semua dibaca';readall.disabled=false}
   })}
   const mt=document.querySelector('[data-sh-mobile-toggle]'),scrim=document.querySelector('[data-sh-mobile-scrim]');
   if(mt&&!mt.dataset.shBound){mt.dataset.shBound='1';mt.addEventListener('click',e=>{e.stopPropagation();mobile(!root()?.classList.contains('im-sh-menu-open'))})}
   if(scrim&&!scrim.dataset.shBound){scrim.dataset.shBound='1';scrim.addEventListener('click',()=>mobile(false))}
   document.querySelectorAll('#im-v4 .im-sh-sidebar .im-sh-nav-link').forEach(a=>{if(!a.dataset.shBound){a.dataset.shBound='1';a.addEventListener('click',()=>mobile(false))}});
 }
 document.addEventListener('click',e=>{if(!e.target.closest('[data-sh-toggle],.im-sh-panel'))close()});
 document.addEventListener('keydown',e=>{if(e.key==='Escape'){close();mobile(false)}});
 window.addEventListener('resize',()=>{close();if(innerWidth>900)mobile(false)});
 window.addEventListener('scroll',e=>{if(e.target?.closest?.('.im-sh-panel,[data-sh-items]'))return;close()},true);
 media.addEventListener?.('change',()=>{if(choice()==='system')applyTheme()});window.addEventListener('storage',e=>{if(e.key===KEY)applyTheme()});
 function init(){bind();load()}
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
 document.addEventListener('livewire:navigated',init);
 setInterval(()=>{if(root()){watch();load()}},30000);
})();
</script>
<span data-sh-csrf="{{ csrf_token() }}" hidden></span>
