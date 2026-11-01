<x-filament-panels::page.simple>
    <link rel="stylesheet" href="/imnehnet-login/login-v5.css?v=5.2">

    <div class="im-v5" data-im-login>
        <div id="im-react-bits-ascii" aria-hidden="true"></div>
        @vite('resources/js/imnehnet-login-ascii.jsx')

        <div class="im-v5-grid">
            <section class="im-v5-card" aria-label="Sign in to IMNEHNET">

                <div class="im-v5-brand">
                    <i data-lucide="box" aria-hidden="true"></i>
                    <div>
                        <strong>IMNEHNET</strong>
                        <span>Lab-as-a-Service</span>
                    </div>
                </div>

                <div class="im-v5-heading">
                    <h1>Sign in</h1>
                    <p>Continue your cybersecurity learning journey.</p>
                </div>

                <nav class="im-v5-tabs" aria-label="Login role">
                    <a href="/student/login"
                       @class(['active' => filament()->getCurrentPanel()?->getId() === 'student'])
                       @if(filament()->getCurrentPanel()?->getId() === 'student') aria-current="page" @endif>
                        <i data-lucide="user-round" aria-hidden="true"></i>
                        Login as User
                    </a>

                    <a href="/admin/login"
                       @class(['active' => filament()->getCurrentPanel()?->getId() === 'admin'])
                       @if(filament()->getCurrentPanel()?->getId() === 'admin') aria-current="page" @endif>
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

                @if(\Illuminate\Support\Facades\Route::has('register'))
                    <p class="im-v5-register">
                        Don't have an account?
                        <a href="{{ route('register') }}">Register as a student</a>
                    </p>
                @endif

            </section>

            <aside class="im-v5-welcome" aria-label="Welcome to IMNEHNET">
                <i data-lucide="box" class="im-v5-watermark" aria-hidden="true"></i>

                <div class="im-v5-welcome-content">
                    <span class="im-v5-kicker">
                        IMNEHNET / LAB-AS-A-SERVICE
                    </span>

                    <h2>Your next skill<br>starts in the lab.</h2>

                    <p class="im-v5-description">
                        Bangun kemampuan keamanan siber melalui praktik
                        langsung di environment yang aman dan terisolasi.
                    </p>

                    <div class="im-v5-benefits">
                        <h3>Learn. Explore. Secure.</h3>

                        <div>
                            <i data-lucide="boxes" aria-hidden="true"></i>
                            <span>Lab terisolasi untuk setiap sesi</span>
                        </div>

                        <div>
                            <i data-lucide="flag" aria-hidden="true"></i>
                            <span>Pentest dengan objective terarah</span>
                        </div>

                        <div>
                            <i data-lucide="chart-no-axes-column-increasing" aria-hidden="true"></i>
                            <span>Pantau progres belajar Anda</span>
                        </div>
                    </div>

                    <div class="im-v5-carousel">
                        <div class="im-v5-dots" aria-label="Carousel slide">
                            <button class="active" type="button"
                                    aria-label="Slide 1" aria-current="true"></button>
                        </div>
                        <button type="button" class="im-v5-next"
                                aria-label="Next slide">
                            <i data-lucide="arrow-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </aside>
        </div>

        <button type="button" class="im-v5-theme"
                aria-label="Toggle light and dark mode">
            <i data-lucide="sun-moon" aria-hidden="true"></i>
        </button>
    </div>

    <script src="/imnehnet-login/login-v5.js?v=5" defer></script>
</x-filament-panels::page.simple>
