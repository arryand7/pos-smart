<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ProductCategory;
use App\Models\User;

class ProductCategoryPolicy
{
    public function before(User $user): bool|null
    {
        return $user->hasRole(UserRole::ADMIN) ? true : null;
    }

    public function viewAny(User $user): bool { return false; }
    public function view(User $user, ProductCategory $productCategory): bool { return false; }
    public function create(User $user): bool { return false; }
    public function update(User $user, ProductCategory $productCategory): bool { return false; }
    public function delete(User $user, ProductCategory $productCategory): bool { return false; }
    public function restore(User $user, ProductCategory $productCategory): bool { return false; }
    public function forceDelete(User $user, ProductCategory $productCategory): bool { return false; }
}
