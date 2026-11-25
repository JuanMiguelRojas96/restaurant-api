<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Dish;
use App\Models\Order;
use App\Models\Table;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Business rules of the order lifecycle. Every operation runs in a transaction
 * and locks the rows it changes, so concurrent waiters cannot corrupt the state.
 */
class OrderService
{
    /**
     * @param  array<int, array{dish_id: int, quantity: int, notes?: string|null}>  $items
     */
    public function open(User $waiter, int $tableId, array $items): Order
    {
        return DB::transaction(function () use ($waiter, $tableId, $items) {
            $table = Table::lockForUpdate()->findOrFail($tableId);

            if ($table->status === TableStatus::Occupied || $table->orders()->active()->exists()) {
                throw new BusinessRuleException("Table {$table->number} already has an active order.");
            }

            $order = $table->orders()->create(['user_id' => $waiter->id]);
            $this->addLines($order, $items);
            $table->update(['status' => TableStatus::Occupied]);

            return $order;
        });
    }

    /**
     * @param  array<int, array{dish_id: int, quantity: int, notes?: string|null}>  $items
     */
    public function addItems(Order $order, array $items): Order
    {
        return DB::transaction(function () use ($order, $items) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if (! $order->status->acceptsNewItems()) {
                throw new BusinessRuleException("Items cannot be added to an order that is {$order->status->value}.");
            }

            $this->addLines($order, $items);

            return $order;
        });
    }

    public function changeStatus(Order $order, OrderStatus $next): Order
    {
        return DB::transaction(function () use ($order, $next) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if (! $order->status->canTransitionTo($next)) {
                throw new BusinessRuleException("An order cannot go from {$order->status->value} to {$next->value}.");
            }

            $order->status = $next;
            $order->save();

            if ($next->isClosed()) {
                $order->table()->update(['status' => TableStatus::Free]);
            }

            return $order;
        });
    }

    /**
     * Copies each dish's current price into the line (price history) and recalculates the total.
     *
     * @param  array<int, array{dish_id: int, quantity: int, notes?: string|null}>  $items
     */
    private function addLines(Order $order, array $items): void
    {
        $dishes = Dish::whereIn('id', array_column($items, 'dish_id'))
            ->where('available', true)
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $dish = $dishes->get($item['dish_id'])
                ?? throw new BusinessRuleException("Dish {$item['dish_id']} is not available.");

            $order->items()->create([
                'dish_id' => $dish->id,
                'quantity' => $item['quantity'],
                'unit_price' => $dish->price,
                'notes' => $item['notes'] ?? null,
            ]);
        }

        $order->total = $order->items()->sum(DB::raw('quantity * unit_price'));
        $order->save();
    }
}
