<?php

namespace App\Providers;

use App\Contracts\LabMutationProvider;
use App\Infrastructure\Mutation\UnavailableLabMutationProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
