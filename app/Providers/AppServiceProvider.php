<?php

namespace App\Providers;

use App\Models\AppSetting;
use App\Models\Location;
use App\Models\PaymentProviderConfig;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Santri;
use App\Models\User;
use App\Models\Wali;
use App\Observers\ActivityLogObserver;
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
        $observer = ActivityLogObserver::class;

        Product::observe($observer);
        ProductCategory::observe($observer);
        Location::observe($observer);
        Santri::observe($observer);
        Wali::observe($observer);
        User::observe($observer);
        AppSetting::observe($observer);
        PaymentProviderConfig::observe($observer);

        $timezone = AppSetting::getValue('timezone');
        if ($timezone) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }
    }
}
