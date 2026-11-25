<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'table_id' => Table::factory()->occupied(),
            'user_id' => User::factory()->waiter(),
            'status' => OrderStatus::Pending,
            'total' => 0,
        ];
    }

    public function withStatus(OrderStatus $status): static
    {
        return $this->state([
            'status' => $status,
        ]);
    }
}
