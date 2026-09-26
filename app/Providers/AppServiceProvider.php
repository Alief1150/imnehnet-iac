<?php

namespace App\Providers;

use App\Contracts\LabMutationProvider;
use App\Contracts\LabProvisioner;
use App\Infrastructure\Mutation\UnavailableLabMutationProvider;
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

        $this->app->bind(LabMutationProvider::class, UnavailableLabMutationProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
