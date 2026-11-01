(() => {
  'use strict';

  if (!/^\/(student|admin)\/login\/?$/.test(location.pathname)) {
    return;
  }

  const icons = {
    box: '<path d="m12 2 9 5v10l-9 5-9-5V7l9-5Z"/><path d="m3 7 9 5 9-5M12 12v10"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
    boxes: '<path d="m12 2 8 4.5v9L12 20l-8-4.5v-9L12 2Z"/><path d="m4 6.5 8 4.5 8-4.5M12 11v9"/>',
    flag: '<path d="M4 22V4m0 1c4-4 8 4 16 0v12c-8 4-12-4-16 0"/>',
    chart: '<path d="M3 3v18h18M7 16l4-5 3 2 5-7"/>',
    lock: '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
    arrow: '<path d="M5 12h14m-7-7 7 7-7 7"/>'
  };

  const icon = (name, className='') =>
    `<svg class="${className}" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">${icons[name]}</svg>`;

  const slides = [
    {
      heading:'Your next skill<br>starts in the lab.',
      text:'Bangun kemampuan keamanan siber melalui praktik langsung di environment yang aman dan terisolasi.'
    },
    {
      heading:'Learn by doing.<br>Not just reading.',
      text:'Praktikkan teknik keamanan jaringan melalui lingkungan virtual yang disediakan khusus untuk pembelajaran.'
    },
    {
      heading:'Explore safely.<br>Build confidence.',
      text:'Gunakan lab terisolasi untuk mempelajari skenario keamanan tanpa mengganggu sistem lain.'
    },
    {
      heading:'Track progress.<br>Master the challenge.',
      text:'Selesaikan objective secara bertahap dan tinjau progres pembelajaran melalui IMNEHNET.'
    }
  ];

  function init() {
    if (document.body.classList.contains('im-login-v4')) return;

    const layout = document.querySelector('.fi-simple-layout');
    const main = document.querySelector('.fi-simple-main');

    if (!layout || !main) {
      console.error('IMNEHNET Login V4: Filament layout not found');
      return;
    }

    let column = main;
    while (column.parentElement && column.parentElement !== layout) {
      column = column.parentElement;
    }

    if (column.parentElement !== layout) {
      console.error('IMNEHNET Login V4: Unexpected DOM hierarchy');
      return;
    }

    // Native Filament/Livewire form is preserved in its original component.
    column.classList.add('im-login-column');
    document.body.classList.add('im-login-v4');

    const saved = localStorage.getItem('imnehnet-login-theme');
    const dark = saved === 'dark' ||
      (!saved && matchMedia('(prefers-color-scheme:dark)').matches);

    document.body.classList.toggle('im-dark-v4', dark);

    // Remove old design artifacts only. Form is never removed.
    document.querySelectorAll(
      '.im-hero,.im-ascii,.im-role-tabs,.im-secure-note,' +
      '.im-theme-button,.im-react-bits-ascii,' +
      '.im-login-welcome,.im-login-theme'
    ).forEach(el => el.remove());

    const oldAscii = document.getElementById('im-react-bits-ascii');
    if (oldAscii) oldAscii.remove();

    const brand = document.createElement('div');
    brand.className = 'im-brand';
    brand.innerHTML = `
      ${icon('box')}
      <div>
        <div class="im-brand-name">IMNEHNET</div>
        <div class="im-brand-sub">Lab-as-a-Service</div>
      </div>`;

    const title = document.createElement('h1');
    title.className = 'im-login-title';
    title.textContent = 'Sign in';

    const sub = document.createElement('p');
    sub.className = 'im-login-sub';
    sub.textContent = 'Continue your cybersecurity learning journey.';

    const role = location.pathname.startsWith('/admin/')
      ? 'admin' : 'student';

    const tabs = document.createElement('nav');
    tabs.className = 'im-login-tabs';
    tabs.setAttribute('aria-label', 'Login role');
    tabs.innerHTML = `
      <a href="/student/login" ${role==='student'?'aria-current="page"':''}>
        ${icon('user')} Login as User
      </a>
      <a href="/admin/login" ${role==='admin'?'aria-current="page"':''}>
        ${icon('shield')} Login as Admin
      </a>`;

    // Place design elements inside the existing Livewire page.
    main.prepend(tabs);
    main.prepend(sub);
    main.prepend(title);
    main.prepend(brand);

    const note = document.createElement('div');
    note.className = 'im-login-note';
    note.innerHTML =
      icon('lock') +
      '<span>Gunakan email dan password akun IMNEHNET Anda.</span>';
    main.append(note);

    // Improve native form labels without changing input names/bindings.
    const email = main.querySelector('input[type="email"]');
    if (email) email.placeholder = 'you@email.ac.id';

    const hero = document.createElement('aside');
    hero.className = 'im-login-welcome';
    hero.setAttribute('aria-label','IMNEHNET introduction');

    hero.innerHTML = `
      ${icon('box','im-login-watermark')}
      <div class="im-welcome-kicker">IMNEHNET / LAB-AS-A-SERVICE</div>
      <h2 class="im-welcome-heading"></h2>
      <p class="im-welcome-description"></p>
      <div class="im-benefits">
        <h3 class="im-benefits-title">Learn. Explore. Secure.</h3>
        <div class="im-benefit">${icon('boxes')} Lab terisolasi untuk setiap sesi</div>
        <div class="im-benefit">${icon('flag')} Pentest dengan objective terarah</div>
        <div class="im-benefit">${icon('chart')} Pantau progres belajar Anda</div>
      </div>
      <div class="im-carousel-controls">
        <div class="im-carousel-dots" aria-label="Carousel pages">
          ${slides.map((_,i)=>`<button type="button" data-slide="${i}" aria-label="Slide ${i+1}"></button>`).join('')}
        </div>
        <button type="button" class="im-carousel-next" aria-label="Next slide">
          ${icon('arrow')}
        </button>
      </div>`;

    layout.append(hero);

    let current=0;
    function display(index) {
      current=(index+slides.length)%slides.length;
      hero.querySelector('.im-welcome-heading').innerHTML=slides[current].heading;
      hero.querySelector('.im-welcome-description').textContent=slides[current].text;
      hero.querySelectorAll('[data-slide]').forEach((dot,i)=>{
        dot.classList.toggle('active',i===current);
        dot.setAttribute('aria-current', i===current?'true':'false');
      });
    }
    display(0);

    let carouselTimer;
    const reduced=matchMedia('(prefers-reduced-motion:reduce)').matches;

    function restartTimer() {
      clearInterval(carouselTimer);
      if (!reduced) carouselTimer=setInterval(()=>{
        if (!document.hidden) display(current+1);
      },5000);
    }

    hero.querySelector('.im-carousel-next').addEventListener('click',()=>{
      display(current+1);
      restartTimer();
    });

    hero.querySelectorAll('[data-slide]').forEach(dot=>{
      dot.addEventListener('click',()=>{
        display(Number(dot.dataset.slide));
        restartTimer();
      });
    });
    restartTimer();

    const theme=document.createElement('button');
    theme.className='im-login-theme';
    theme.type='button';

    function updateTheme(){
      theme.textContent=document.body.classList.contains('im-dark-v4')
        ?'Light mode':'Dark mode';
    }

    theme.addEventListener('click',()=>{
      const state=document.body.classList.toggle('im-dark-v4');
      localStorage.setItem('imnehnet-login-theme',state?'dark':'light');
      updateTheme();
    });
    updateTheme();
    document.body.append(theme);

    const ascii=document.createElement('div');
    ascii.id='im-react-bits-ascii';
    ascii.setAttribute('aria-hidden','true');
    layout.prepend(ascii);
  }

  if(document.readyState==='loading') {
    document.addEventListener('DOMContentLoaded',init,{once:true});
  } else {
    init();
  }
})();
