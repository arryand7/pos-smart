<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Santri;
use App\Models\User;

class SantriPolicy
{
    public function before(User $user): bool|null
    {
        return $user->hasRole(UserRole::ADMIN) ? true : null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Santri $santri): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Santri $santri): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Santri $santri): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Santri $santri): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Santri $santri): bool
    {
        return false;
    }
}
