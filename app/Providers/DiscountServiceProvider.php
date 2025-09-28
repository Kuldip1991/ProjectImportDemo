<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\DiscountService;

class DiscountServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(DiscountService::class, function ($app) {
            return new DiscountService();
        });

        $this->mergeConfigFrom(
            __DIR__ . '/../../config/user-discounts.php',
            'user-discounts'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/user-discounts.php' => config_path('user-discounts.php'),
        ], 'user-discounts-config');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

    }

}
