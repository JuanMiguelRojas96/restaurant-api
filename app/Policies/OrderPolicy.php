<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Orders are internal: only restaurant staff can see them.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Waiter, Role::Kitchen);
    }

    public function view(User $user, Order $order): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Waiters (and admins) take orders at the tables.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Waiter);
    }

    public function addItems(User $user, Order $order): bool
    {
        return $this->create($user);
    }

    /**
     * Who may move an order to each status. Whether the move itself is valid
     * (e.g. not pendiente → pagado) is decided by OrderStatus, not here.
     */
    public function updateStatus(User $user, Order $order, OrderStatus $next): bool
    {
        return match ($next) {
            OrderStatus::InPreparation, OrderStatus::Ready => $user->hasRole(Role::Kitchen),
            OrderStatus::Delivered, OrderStatus::Paid, OrderStatus::Cancelled => $user->hasRole(Role::Admin, Role::Waiter),
            OrderStatus::Pending => false,
        };
    }
}
