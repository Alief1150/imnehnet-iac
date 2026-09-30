<?php

namespace App\Providers;

use App\Contracts\LabProvisioner;
use App\Contracts\LifecycleDriver;
use App\Drivers\LiveLifecycleDriver;
use App\Infrastructure\Provision\IncusLabProvisioner;
use App\Services\AnsibleService;
use App\Services\LabPortAllocationService;
use App\Services\OpenTofuService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LabPortAllocationService::class);

        // Built from configuration rather than injected field by field, so the
        // working directory and the lock file live in exactly one place.
        $this->app->singleton(OpenTofuService::class, fn (): OpenTofuService => OpenTofuService::fromConfig());
        $this->app->singleton(AnsibleService::class, fn (): AnsibleService => AnsibleService::fromConfig());

        // The orchestration talks to this contract only, so the live driver can
        // be swapped for a double in a test without touching the lifecycle.
        $this->app->singleton(LifecycleDriver::class, fn ($app): LifecycleDriver => new LiveLifecycleDriver(
            tofu: $app->make(OpenTofuService::class),
            ansible: $app->make(AnsibleService::class),
        ));

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
