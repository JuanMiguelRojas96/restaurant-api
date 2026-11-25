<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Dish;
use App\Models\User;

class DishPolicy
{
    /**
     * Any authenticated user can browse the menu.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Dish $dish): bool
    {
        return true;
    }

    /**
     * Only admins manage the menu.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function update(User $user, Dish $dish): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function delete(User $user, Dish $dish): bool
    {
        return $user->hasRole(Role::Admin);
    }
}
