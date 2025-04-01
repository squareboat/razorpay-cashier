<?php

namespace Squareboat\RazorpayCashier;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        $this->publishes([
            __DIR__ . '/../config/razorpay.php' => config_path('razorpay.php'),
        ], 'razorpay-config');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/razorpay.php', 'razorpay');
    }
}
