<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\User;

class LocationPolicy
{
    public function before(User $user): bool|null
    {
        return $user->hasRole(UserRole::ADMIN) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Location $location): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Location $location): bool
    {
        return false;
    }

    public function delete(User $user, Location $location): bool
    {
        return false;
    }

    public function restore(User $user, Location $location): bool
    {
        return false;
    }

    public function forceDelete(User $user, Location $location): bool
    {
        return false;
    }
}
