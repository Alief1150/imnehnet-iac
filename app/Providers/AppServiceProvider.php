<?php

namespace App\Providers;

use App\Contracts\LabProvisioner;
use App\Infrastructure\Provision\IncusLabProvisioner;
use App\Services\LabPortAllocationService;
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
     *
     * Nothing is registered here on purpose. The portal login listener is
     * discovered from app/Listeners, and the user observer is declared on the
     * model with #[ObservedBy]. Registering either manually from a provider
     * risks double registration, which would write two identical audit rows
     * for a single change.
     */
    public function boot(): void
    {
        //
    }
}
