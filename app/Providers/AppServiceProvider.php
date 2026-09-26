<?php

namespace App\Providers;

use App\Contracts\LabProvisioner;
use App\Infrastructure\Provision\IncusLabProvisioner;
use App\Listeners\RecordPortalLogin;
use App\Models\User;
use App\Observers\UserObserver;
use App\Services\LabPortAllocationService;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LabPortAllocationService::class);

        $this->app->bind(
            LabProvisioner::class,
            fn ($app): LabProvisioner => new IncusLabProvisioner(
                ports: $app->make(LabPortAllocationService::class),
                remote: (string) config('infrastructure.incus_remote', 'lab-env:'),
                network: (string) config('infrastructure.incus_network', 'incusbr0'),
            ),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Signing in is an audited event and also stamps last_login_at.
        Event::listen(Login::class, RecordPortalLogin::class);

        // Role and status changes are audited on the model, so the entry is
        // written whichever surface made the change.
        User::observe(UserObserver::class);
    }
}
