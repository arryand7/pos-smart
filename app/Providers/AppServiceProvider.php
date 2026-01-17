<?php

namespace App\Providers;

use App\Services\Payment\PaymentManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentManager::class, function ($app) {
            $providers = [];

            foreach (config('smart.payments.provider_map', []) as $class) {
                if (class_exists($class)) {
                    $providers[] = $app->make($class);
                }
            }

            return new PaymentManager($providers);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
