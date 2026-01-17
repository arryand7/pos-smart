<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Santri;
use App\Policies\LocationPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\SantriPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Product::class => ProductPolicy::class,
        Location::class => LocationPolicy::class,
        ProductCategory::class => ProductCategoryPolicy::class,
        Santri::class => SantriPolicy::class,
    ];

    public function boot(): void
    {
        Gate::before(function ($user) {
            return $user?->hasRole(UserRole::SUPER_ADMIN) ? true : null;
        });
    }
}
