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
.im-sh-notification-items{max-height:360px;overflow-y:auto}
.im-sh-notification-item{display:block;text-decoration:none!important;color:inherit!important;padding:11px 13px;border-bottom:1px solid #9ca3af22}
.im-sh-notification-item:hover{background:#7eafc31c}
.im-sh-notification-item.unread{border-left:3px solid #35c8ba}
.im-sh-notification-item strong{display:block;font-size:12px}
.im-sh-notification-item p{font-size:11px;line-height:1.5;white-space:normal;overflow-wrap:anywhere;margin:5px 0;opacity:.8}
.im-sh-notification-item small{font-size:10px;opacity:.55}
.im-sh-empty{text-align:center;font-size:12px;padding:18px;opacity:.7}
#im-v4[data-theme=dark]~.im-sh-panel,.im-sh-panel.im-sh-dark{background:#1c2942;color:#eaf0ff;border-color:#384969;box-shadow:0 20px 50px #0008}
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
.im-sh-panel.im-sh-dark{background:#1c2942;color:#eaf0ff;border-color:#384969}
.im-sh-panel:not(.im-sh-dark){background:#fff;color:#26334d;border-color:#dae2ee}
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
</style>
<script data-imnehnet-student-shell="20261122">
(function(){
 const KEY='imnehnet-student-theme-choice';
 const media=window.matchMedia('(prefers-color-scheme: dark)');
 const getChoice=()=>{try{return localStorage.getItem(KEY)||'system'}catch(e){return 'system'}};
 const setChoice=v=>{try{localStorage.setItem(KEY,v)}catch(e){}};
 const root=()=>document.getElementById('im-v4');
 const panel=n=>document.querySelector('[data-sh-panel="'+n+'"]');
 const btn=n=>document.querySelector('[data-sh-toggle="'+n+'"]');
 function theme(){const choice=['light','dark','system'].includes(getChoice())?getChoice():'system';const effective=choice==='system'?(media.matches?'dark':'light'):choice;root()?.setAttribute('data-theme',effective);document.querySelectorAll('[data-sh-theme]').forEach(b=>b.setAttribute('aria-checked',String(b.dataset.shTheme===choice)));document.querySelectorAll('.im-sh-panel').forEach(p=>p.classList.toggle('im-sh-dark',effective==='dark'));}
 function close(){document.querySelectorAll('.im-sh-panel').forEach(p=>p.hidden=true);document.querySelectorAll('[data-sh-toggle]').forEach(b=>b.setAttribute('aria-expanded','false'));}
 function position(p,b){const r=b.getBoundingClientRect();const w=Math.min(p.offsetWidth,innerWidth-24);p.style.left=Math.max(12,Math.min(r.right-w,innerWidth-w-12))+'px';p.style.top=(r.bottom+10+p.offsetHeight<=innerHeight-12?r.bottom+10:Math.max(12,r.top-p.offsetHeight-10))+'px';}
 function ensurePortals(){document.querySelectorAll('.im-sh-panel').forEach(p=>{if(p.parentElement!==document.body)document.body.appendChild(p)});}
 function bind(){if(!root())return;ensurePortals();document.querySelectorAll('[data-sh-toggle]').forEach(b=>{if(b.dataset.shBound)return;b.dataset.shBound='1';b.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();const name=b.dataset.shToggle,p=panel(name),willOpen=!!p?.hidden;close();if(willOpen&&p){p.hidden=false;position(p,b);b.setAttribute('aria-expanded','true');if(name==='notifications')loadNotifications()}})});document.querySelectorAll('[data-sh-theme]').forEach(b=>{if(b.dataset.shBound)return;b.dataset.shBound='1';b.addEventListener('click',e=>{e.stopPropagation();setChoice(b.dataset.shTheme);theme();close()})});theme();}
 const api='/student/ui-notifications';
 async function send(path){let token=document.querySelector('meta[name=csrf-token]')?.content||document.querySelector('[data-sh-csrf]')?.dataset.shCsrf||'';const r=await fetch(api+path,{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','X-CSRF-TOKEN':token}});if(!r.ok)throw Error('HTTP '+r.status);}
 function itemNode(n){const a=document.createElement('a');a.className='im-sh-notification-item'+(n.read?'':' unread');let url='/student/my-labs';try{const u=new URL(n.url||url,location.origin);if(u.origin===location.origin&&(u.pathname==='/student'||u.pathname.startsWith('/student/')))url=u.pathname+u.search}catch(e){}a.href=url;const title=document.createElement('strong');title.textContent=n.title||'IMNEHNET';const msg=document.createElement('p');msg.textContent=n.message||'';const when=document.createElement('small');when.textContent=n.when||'';a.append(title,msg,when);a.addEventListener('click',async e=>{if(n.read)return;e.preventDefault();try{await send('/'+encodeURIComponent(n.id)+'/read')}catch(err){}location.assign(url)});return a;}
 let fetching=false;
 async function loadNotifications(){if(fetching||!root())return;fetching=true;try{const r=await fetch(api,{credentials:'same-origin',headers:{'Accept':'application/json'}});if(!r.ok)throw Error('HTTP '+r.status);const data=await r.json();const count=Number(data.unread)||0;document.querySelectorAll('[data-sh-unread]').forEach(b=>b.hidden=count===0);const host=document.querySelector('[data-sh-items]');if(host){host.replaceChildren();for(const n of (data.notifications||[]))host.appendChild(itemNode(n));if(!host.children.length){const p=document.createElement('p');p.className='im-sh-empty';p.textContent='Belum ada notifikasi';host.appendChild(p)}}}catch(e){const host=document.querySelector('[data-sh-items]');if(host&&!host.children.length){host.textContent='Notifikasi belum bisa dimuat'}}finally{fetching=false}}

 function toggleMobile(open){
   const r=root(),scrim=document.querySelector('[data-sh-mobile-scrim]'),button=document.querySelector('[data-sh-mobile-toggle]');
   if(!r||!scrim||!button)return;
   r.classList.toggle('im-sh-menu-open',open);scrim.hidden=!open;
   button.setAttribute('aria-expanded',String(open));document.body.style.overflow=open?'hidden':'';
 }
 function bindMobile(){
   const b=document.querySelector('[data-sh-mobile-toggle]'),scrim=document.querySelector('[data-sh-mobile-scrim]');
   if(b&&!b.dataset.shBound){b.dataset.shBound='1';b.addEventListener('click',e=>{e.stopPropagation();toggleMobile(!root()?.classList.contains('im-sh-menu-open'))})}
   if(scrim&&!scrim.dataset.shBound){scrim.dataset.shBound='1';scrim.addEventListener('click',()=>toggleMobile(false))}
   document.querySelectorAll('#im-v4 .im-sh-sidebar .im-sh-nav-link').forEach(a=>{
      if(!a.dataset.shBound){a.dataset.shBound='1';a.addEventListener('click',()=>toggleMobile(false))}
   });
 }

 function init(){bind();bindMobile();loadNotifications();const r=document.querySelector('[data-sh-readall]');if(r&&!r.dataset.shBound){r.dataset.shBound='1';r.addEventListener('click',async e=>{e.preventDefault();try{await send('/read-all');await loadNotifications()}catch(err){}})}}
 document.addEventListener('click',e=>{if(!e.target.closest('[data-sh-toggle],.im-sh-panel'))close()});
 document.addEventListener('keydown',e=>{if(e.key==='Escape'){close();toggleMobile(false)}});
 window.addEventListener('resize',()=>{close();if(innerWidth>900)toggleMobile(false)});window.addEventListener('scroll',()=>close(),true);
 media.addEventListener?.('change',theme);
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
 document.addEventListener('livewire:navigated',init);
 setInterval(()=>{if(root())loadNotifications()},30000);
})();
</script>
<span data-sh-csrf="{{ csrf_token() }}" hidden></span>
