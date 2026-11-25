<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\TableStatus;
use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function should_open_an_order_with_menu_prices_and_occupy_the_table(): void
    {
        $table = Table::factory()->create();
        $lemonade = Dish::factory()->create(['price' => 9500]);
        $brownie = Dish::factory()->create(['price' => 12000]);
        $waiter = $this->actingAsRole(Role::Waiter);

        $response = $this->postJson('/api/orders', [
            'table_id' => $table->id,
            'items' => [
                ['dish_id' => $lemonade->id, 'quantity' => 2, 'notes' => 'sin hielo', 'unit_price' => 1],
                ['dish_id' => $brownie->id, 'quantity' => 1],
            ],
            'total' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::Pending->value)
            ->assertJsonPath('data.total', 31000)
            ->assertJsonPath('data.items.0.unit_price', 9500)
            ->assertJsonPath('data.items.0.notes', 'sin hielo')
            ->assertJsonPath('data.waiter.id', $waiter->id);
        $this->assertSame(TableStatus::Occupied, $table->fresh()->status);
    }

    #[Test]
    public function should_reject_unavailable_dishes_without_creating_anything(): void
    {
        $table = Table::factory()->create();
        $soldOut = Dish::factory()->unavailable()->create();
        $this->actingAsRole(Role::Waiter);

        $response = $this->postJson('/api/orders', [
            'table_id' => $table->id,
            'items' => [['dish_id' => $soldOut->id, 'quantity' => 1]],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('items.0.dish_id');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(TableStatus::Free, $table->fresh()->status);
    }

    #[Test]
    public function should_refuse_a_second_active_order_on_the_same_table(): void
    {
        $existing = Order::factory()->create();
        $dish = Dish::factory()->create();
        $this->actingAsRole(Role::Waiter);

        $response = $this->postJson('/api/orders', [
            'table_id' => $existing->table_id,
            'items' => [['dish_id' => $dish->id, 'quantity' => 1]],
        ]);

        $response->assertConflict();
        $this->assertDatabaseCount('orders', 1);
    }

    /**
     * @return array<string, array{0: Role}>
     */
    public static function rolesThatCannotOpenOrders(): array
    {
        return [
            'kitchen' => [Role::Kitchen],
            'customer' => [Role::Customer],
        ];
    }

    #[Test]
    #[DataProvider('rolesThatCannotOpenOrders')]
    public function should_forbid_opening_orders_to_kitchen_and_customers(Role $role): void
    {
        $table = Table::factory()->create();
        $dish = Dish::factory()->create();
        $this->actingAsRole($role);

        $response = $this->postJson('/api/orders', [
            'table_id' => $table->id,
            'items' => [['dish_id' => $dish->id, 'quantity' => 1]],
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function should_walk_the_full_lifecycle_and_free_the_table_when_paid(): void
    {
        $order = Order::factory()->create();

        $this->actingAsRole(Role::Kitchen);
        $inPreparation = $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'en_preparacion']);
        $ready = $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'listo']);
        $this->actingAsRole(Role::Waiter);
        $delivered = $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'entregado']);
        $paid = $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'pagado']);

        $inPreparation->assertOk();
        $ready->assertOk();
        $delivered->assertOk();
        $paid->assertOk()->assertJsonPath('data.status', 'pagado')->assertJsonPath('data.table.status', 'libre');
    }

    #[Test]
    public function should_free_the_table_when_the_order_is_cancelled(): void
    {
        $order = Order::factory()->create();
        $this->actingAsRole(Role::Waiter);

        $response = $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelado']);

        $response->assertOk();
        $this->assertSame(TableStatus::Free, $order->table->fresh()->status);
    }

    /**
     * @return array<string, array{0: OrderStatus, 1: OrderStatus, 2: Role, 3: int}>
     */
    public static function transitionsByRole(): array
    {
        return [
            'waiter cannot start cooking' => [OrderStatus::Pending, OrderStatus::InPreparation, Role::Waiter, 403],
            'waiter cannot mark ready' => [OrderStatus::InPreparation, OrderStatus::Ready, Role::Waiter, 403],
            'admin cannot mark ready' => [OrderStatus::InPreparation, OrderStatus::Ready, Role::Admin, 403],
            'kitchen marks ready' => [OrderStatus::InPreparation, OrderStatus::Ready, Role::Kitchen, 200],
            'kitchen cannot deliver' => [OrderStatus::Ready, OrderStatus::Delivered, Role::Kitchen, 403],
            'kitchen cannot charge' => [OrderStatus::Delivered, OrderStatus::Paid, Role::Kitchen, 403],
            'skipping to paid is a conflict' => [OrderStatus::Pending, OrderStatus::Paid, Role::Waiter, 409],
            'paid orders are final' => [OrderStatus::Paid, OrderStatus::Cancelled, Role::Admin, 409],
            'nobody can move back to pendiente' => [OrderStatus::InPreparation, OrderStatus::Pending, Role::Admin, 403],
        ];
    }

    #[Test]
    #[DataProvider('transitionsByRole')]
    public function should_enforce_who_can_move_an_order_and_which_moves_exist(
        OrderStatus $from,
        OrderStatus $to,
        Role $role,
        int $expectedStatus,
    ): void {
        $order = Order::factory()->withStatus($from)->create();
        $this->actingAsRole($role);

        $response = $this->patchJson("/api/orders/{$order->id}/status", ['status' => $to->value]);

        $response->assertStatus($expectedStatus);
    }

    #[Test]
    public function should_reject_a_status_that_does_not_exist(): void
    {
        $order = Order::factory()->create();
        $this->actingAsRole(Role::Kitchen);

        $response = $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'volando']);

        $response->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    #[Test]
    public function should_recalculate_the_total_when_items_are_added(): void
    {
        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->create(['quantity' => 2, 'unit_price' => 9000]);
        $order->update(['total' => 18000]);
        $soup = Dish::factory()->create(['price' => 32000]);
        $this->actingAsRole(Role::Waiter);

        $response = $this->postJson("/api/orders/{$order->id}/items", [
            'items' => [['dish_id' => $soup->id, 'quantity' => 1]],
        ]);

        $response->assertOk()->assertJsonPath('data.total', 50000)->assertJsonCount(2, 'data.items');
    }

    #[Test]
    public function should_refuse_new_items_once_the_kitchen_has_finished(): void
    {
        $order = Order::factory()->withStatus(OrderStatus::Ready)->create();
        $dish = Dish::factory()->create();
        $this->actingAsRole(Role::Waiter);

        $response = $this->postJson("/api/orders/{$order->id}/items", [
            'items' => [['dish_id' => $dish->id, 'quantity' => 1]],
        ]);

        $response->assertConflict();
    }

    #[Test]
    public function should_keep_historical_prices_when_the_menu_changes(): void
    {
        $dish = Dish::factory()->create(['price' => 9500]);
        $item = OrderItem::factory()->create(['dish_id' => $dish->id]);
        $dish->update(['price' => 15000]);
        $this->actingAsRole(Role::Kitchen);

        $response = $this->getJson("/api/orders/{$item->order_id}");

        $response->assertOk()->assertJsonPath('data.items.0.unit_price', 9500);
    }

    #[Test]
    public function should_list_orders_filtered_by_status_for_staff(): void
    {
        Order::factory()->withStatus(OrderStatus::Paid)->create();
        Order::factory()->count(2)->create();
        $this->actingAsRole(Role::Kitchen);

        $response = $this->getJson('/api/orders?status=pendiente');

        $response->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.status', 'pendiente');
    }
}
