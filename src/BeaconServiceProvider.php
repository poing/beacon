<?php

namespace Poing\Beacon;

use Illuminate\Support\ServiceProvider;

class BeaconServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/beacon.php' => config_path('beacon.php'),
        ], 'config');
    }

    public function register()
    {
        // Merge default config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/beacon.php',
            'beacon'
        );

        // Bind Beacon
        $this->app->singleton(Beacon::class, fn($app) => new Beacon());
    }
}