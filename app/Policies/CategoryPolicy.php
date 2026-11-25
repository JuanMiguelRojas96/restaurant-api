<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Any authenticated user can browse the menu.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Category $category): bool
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

    public function update(User $user, Category $category): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->hasRole(Role::Admin);
    }
}
