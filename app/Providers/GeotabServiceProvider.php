<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Geotab\API; // La clase correcta es API, no MyGeotab

class GeotabServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(API::class, function ($app) {
            return new API(
                env('GEOTAB_USERNAME'),
                env('GEOTAB_PASSWORD'),
                env('GEOTAB_DATABASE'),
                env('GEOTAB_SERVER', 'my.geotab.com')
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
