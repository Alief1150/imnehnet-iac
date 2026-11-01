/* IMNEHNET_LOGIN_EXPORT_V1 · no credential handling, no login transport changes */
(() => {
  const path = location.pathname.replace(/\/+$/, '');
  if (!/^\/(student|admin)\/login$/.test(path)) return;
  const initialize = () => {
    if (document.body.classList.contains('im-login')) return;
    const shell = document.querySelector('.fi-simple-layout');
    const main = document.querySelector('.fi-simple-main');
    if (!shell || !main) return;
    document.body.classList.add('im-login');
    const panel = path.startsWith('/admin/') ? 'admin' : 'student';
    const saved = localStorage.getItem('imnehnet-login-theme');
    if (saved === 'light' || (!saved && matchMedia('(prefers-color-scheme:light)').matches)) document.body.classList.add('im-light');
    const ns='http://www.w3.org/2000/svg';
    const icon = (name) => { const paths={user:'<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',shield:'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>',terminal:'<rect x="3" y="4" width="18" height="16" rx="2"/><path d="m7 9 3 3-3 3m6 0h4"/>',check:'<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',chart:'<path d="M3 3v18h18M7 16l4-5 3 2 5-7"/>'};return `<svg viewBox="0 0 24 24" aria-hidden="true">${paths[name]}</svg>`; };
    const tabs = document.createElement('nav');
    tabs.className='im-role-tabs';tabs.setAttribute('aria-label','Login role');
    tabs.innerHTML=`<a href="/student/login" ${panel==='student'?'aria-current="page"':''}>${icon('user')}Login as User</a><a href="/admin/login" ${panel==='admin'?'aria-current="page"':''}>${icon('shield')}Login as Admin</a>`;
    const header=main.querySelector('.fi-simple-header') || main.firstElementChild;
    if(header) header.insertAdjacentElement('afterend',tabs); else main.prepend(tabs);
    const note=document.createElement('p');note.className='im-secure-note';note.textContent='Gunakan email dan password akun IMNEHNET Anda.';main.append(note);
    const hero=document.createElement('aside');hero.className='im-hero';hero.setAttribute('aria-label','IMNEHNET introduction');
    hero.innerHTML=`<div class="im-hero-kicker">IMNEHNET / LAB-AS-A-SERVICE</div><h2>Your next skill starts in the lab.</h2><p>Bangun kemampuan keamanan siber melalui praktik langsung di environment yang aman dan terisolasi.</p><p style="font-weight:700;color:var(--im-highlight)">Learn. Explore. Secure.</p><div class="im-features"><div class="im-feature">${icon('shield')}Lab terisolasi untuk setiap sesi</div><div class="im-feature">${icon('terminal')}Pentest dengan objective terarah</div><div class="im-feature">${icon('chart')}Pantau progres belajar Anda</div></div>`;
    shell.append(hero);
    const button=document.createElement('button');button.type='button';button.className='im-theme-button';button.textContent=document.body.classList.contains('im-light')?'Dark mode':'Light mode';button.setAttribute('aria-label','Switch login theme');
    button.onclick=()=>{document.body.classList.toggle('im-light');const light=document.body.classList.contains('im-light');localStorage.setItem('imnehnet-login-theme',light?'light':'dark');button.textContent=light?'Dark mode':'Light mode';};document.body.append(button);
    const bg=document.createElement('div');bg.className='im-ascii';bg.setAttribute('aria-hidden','true');const canvas=document.createElement('canvas');bg.append(canvas);shell.prepend(bg);
    const ctx=canvas.getContext('2d');if(!ctx)return;
    const reduced=matchMedia('(prefers-reduced-motion: reduce)').matches;
    let width=0,height=0,tick=0,raf=0,last=0;
    const glyphs=' =+xX0S8#@';
    const resize=()=>{const rect=bg.getBoundingClientRect();width=Math.max(1,rect.width);height=Math.max(1,rect.height);const dpr=Math.min(devicePixelRatio||1,1.5);canvas.width=Math.floor(width*dpr);canvas.height=Math.floor(height*dpr);ctx.setTransform(dpr,0,0,dpr,0,0);};
    const draw=(time)=>{if(document.hidden)return;const step=18,cols=Math.ceil(width/step),rows=Math.ceil(height/step);ctx.clearRect(0,0,width,height);const light=document.body.classList.contains('im-light');for(let y=0;y<rows;y++){for(let x=0;x<cols;x++){const u=x/cols-.5,v=y/rows-.5;const band=Math.sin(u*5+time*.00022)*.09;const horizon=Math.exp(-Math.pow((v-band+.08)*3.4,2));const bloom=Math.exp(-((u-.09)**2*2+(v+.24)**2*3));const intensity=Math.max(0,Math.min(1,horizon*.68+bloom*.48));const noise=(Math.sin(x*127.1+y*311.7)*43758.5453)%1;const rand=Math.abs(noise);if(rand>.35+intensity*.58)continue;const index=Math.max(1,Math.min(glyphs.length-1,Math.floor(intensity*8+rand*2)));const a=Math.min(.66,.08+intensity*.55);ctx.fillStyle=light?`rgba(38,93,182,${a})`:`rgba(${Math.floor(105+110*intensity)},${Math.floor(138+95*intensity)},255,${a})`;ctx.font='13px monospace';ctx.fillText(glyphs[index],x*step,y*step);}}};
    const frame=(time)=>{if(!document.body.classList.contains('im-login'))return;if(!reduced&&time-last>=70){draw(time);last=time;}if(!reduced)raf=requestAnimationFrame(frame);};
    resize();draw(0);if(!reduced)raf=requestAnimationFrame(frame);window.addEventListener('resize',resize,{passive:true});
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)draw(performance.now());});
  };
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initialize,{once:true});else initialize();
})();
