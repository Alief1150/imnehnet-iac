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
     */
    public function boot(): void
    {
        // The environment inventory normally lives in the infrastructure
        // database. The suite cannot use it: two in-memory SQLite connections
        // are two different databases, so a record created through the
        // infrastructure connection would be invisible to the migrations and
        // invisible to the transaction that rolls the test back. Pointing the
        // inventory at the suite's own database keeps one database, so the
        // schema, the factory and the rollback all agree.
        if ($this->app->runningUnitTests()) {
            config([
                'infrastructure.environment_connection' => config('database.default'),
            ]);
        }
    }
}
