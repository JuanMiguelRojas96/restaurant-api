<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Table;
use App\Models\User;

class TablePolicy
{
    /**
     * Tables are internal: only restaurant staff can see them.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Waiter, Role::Kitchen);
    }

    public function view(User $user, Table $table): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    /**
     * Waiters seat customers and free tables, so they can change the status too.
     */
    public function updateStatus(User $user, Table $table): bool
    {
        return $user->hasRole(Role::Admin, Role::Waiter);
    }
}
