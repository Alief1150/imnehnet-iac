<x-filament-panels::page.simple>
    <link rel="stylesheet" href="/imnehnet-login/login-v5.css?v=mobile-20261118-051342">

    <div class="im-v5" data-im-login data-theme="dark">
        <div id="im-react-bits-ascii" aria-hidden="true"></div>
        @vite('resources/js/imnehnet-login-ascii.jsx')

        <div class="im-v5-grid">
            <section class="im-v5-card" aria-label="Sign in to IMNEHNET">

                @include('components.imnehnet-auth-brand')

                <div class="im-v5-heading">
                    <h1>Sign in</h1>
                    <p>{{ filament()->getCurrentPanel()?->getId() === 'admin' ? 'Sign in to the administration panel.' : 'Continue your cybersecurity learning journey.' }}</p>
                </div>

                {{-- IMNEHNET_AUTH_TABS_V7: tabs navigate to distinct panel login routes. --}}
                @php($imLoginPanel = filament()->getCurrentPanel()?->getId())
                <nav class="im-v5-tabs" aria-label="Login role">
                    <a href="{{ route('filament.student.auth.login') }}"
                       class="{{ $imLoginPanel === 'student' ? 'active' : '' }}"
                       @if($imLoginPanel === 'student') aria-current="page" @endif>
                        <i data-lucide="user-round" aria-hidden="true"></i>
                        Login as Student
                    </a>
                    <a href="{{ route('filament.admin.auth.login') }}"
                       class="{{ $imLoginPanel === 'admin' ? 'active' : '' }}"
                       @if($imLoginPanel === 'admin') aria-current="page" @endif>
                        <i data-lucide="shield-check" aria-hidden="true"></i>
                        Login as Admin
                    </a>
                </nav>

                <form wire:submit="authenticate"
                      class="im-v5-form">

                    {{ $this->form }}

                    <button type="submit"
                            class="im-v5-submit"
                            wire:loading.attr="disabled"
                            wire:target="authenticate">
                        <span wire:loading.remove wire:target="authenticate">
                            Sign in
                        </span>
                        <span wire:loading wire:target="authenticate">
                            Signing in...
                        </span>
                    </button>
                </form>

                <p class="im-v5-secure">
                    <i data-lucide="lock-keyhole" aria-hidden="true"></i>
                    Gunakan email dan password akun IMNEHNET Anda.
                </p>

                @if(filament()->getCurrentPanel()?->getId() === 'student' && \Illuminate\Support\Facades\Route::has('register'))
                    <p class="im-v5-register">
                        Don't have an account?
                        <a href="{{ route('register') }}">Register as a student</a>
                    </p>
                @endif

            </section>

            <aside class="im-v5-welcome" aria-label="Welcome to IMNEHNET">
  <div class="im-v5-welcome-content im-final-fallback">
    <span class="im-v5-kicker">IMNEHNET / LAB-AS-A-SERVICE</span>
    <h2>Your next skill<br>starts in the lab.</h2>
    <p class="im-v5-description">Bangun kemampuan keamanan siber melalui praktik langsung di environment yang aman dan terisolasi.</p>
    <div class="im-v5-benefits">
      <h3>Learn. Explore. Secure.</h3>
      <div><span>Lab terisolasi untuk setiap sesi</span></div>
      <div><span>Pentest dengan objective terarah</span></div>
      <div><span>Pantau progres belajar Anda</span></div>
    </div>
  </div>
  <div class="im-final-poster" hidden>
    @include('components.imnehnet-login-carousel')
  </div>
</aside>
        </div>

        <button type="button" class="im-v5-theme"
                aria-label="Toggle light and dark mode">

<svg class="im-icon-sun" viewBox="0 0 24 24"
     aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
  <circle cx="12" cy="12" r="4"/>
  <path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42
           m11.3 11.3 1.42 1.42M2 12h2m16 0h2
           M4.93 19.07l1.42-1.42
           M17.65 6.35l1.42-1.42"/>
</svg>
<svg class="im-icon-moon" viewBox="0 0 24 24"
     aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
  <path d="M20.9 13A9 9 0 0 1 11 3.1
           9 9 0 1 0 20.9 13Z"/>
</svg>

        </button>
    </div>

    {{-- IMNEHNET poster carousel script --}}
    <script defer src="/imnehnet-login/carousel-v1.js?v=1"></script>
    {{-- IMNEHNET_THEME_SAFE_V2 --}}
    <script defer src="/imnehnet-login/theme-safe.js?v=2"></script>
    <script defer src="/imnehnet-login/login-poster-final.js?v=20261118b"></script>
</x-filament-panels::page.simple>
