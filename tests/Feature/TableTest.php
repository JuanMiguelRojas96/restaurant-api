<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TableStatus;
use App\Models\Order;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TableTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: Role, 1: int}>
     */
    public static function tableListingByRole(): array
    {
        return [
            'admin sees tables' => [Role::Admin, 200],
            'waiter sees tables' => [Role::Waiter, 200],
            'kitchen sees tables' => [Role::Kitchen, 200],
            'customer is forbidden' => [Role::Customer, 403],
        ];
    }

    #[Test]
    #[DataProvider('tableListingByRole')]
    public function should_show_tables_only_to_staff(Role $role, int $expectedStatus): void
    {
        Table::factory()->count(3)->create();
        $this->actingAsRole($role);

        $response = $this->getJson('/api/tables');

        $response->assertStatus($expectedStatus);
    }

    #[Test]
    public function should_create_tables_as_free_even_if_another_status_is_sent(): void
    {
        $this->actingAsRole(Role::Admin);

        $response = $this->postJson('/api/tables', ['number' => 11, 'capacity' => 4, 'status' => 'ocupada']);

        $response->assertCreated()->assertJsonPath('data.status', TableStatus::Free->value);
    }

    #[Test]
    public function should_reject_a_duplicate_table_number(): void
    {
        Table::factory()->create(['number' => 5]);
        $this->actingAsRole(Role::Admin);

        $response = $this->postJson('/api/tables', ['number' => 5, 'capacity' => 4]);

        $response->assertUnprocessable()->assertJsonValidationErrors('number');
    }

    /**
     * @return array<string, array{0: Role, 1: int}>
     */
    public static function statusChangeByRole(): array
    {
        return [
            'admin can' => [Role::Admin, 200],
            'waiter can' => [Role::Waiter, 200],
            'kitchen cannot' => [Role::Kitchen, 403],
            'customer cannot' => [Role::Customer, 403],
        ];
    }

    #[Test]
    #[DataProvider('statusChangeByRole')]
    public function should_let_only_admins_and_waiters_change_the_table_status(Role $role, int $expectedStatus): void
    {
        $table = Table::factory()->create();
        $this->actingAsRole($role);

        $response = $this->patchJson("/api/tables/{$table->id}/status", ['status' => TableStatus::Reserved->value]);

        $response->assertStatus($expectedStatus);
    }

    #[Test]
    public function should_refuse_to_free_a_table_that_has_an_active_order(): void
    {
        $order = Order::factory()->create();
        $this->actingAsRole(Role::Waiter);

        $response = $this->patchJson("/api/tables/{$order->table_id}/status", ['status' => TableStatus::Free->value]);

        $response->assertConflict();
        $this->assertSame(TableStatus::Occupied, $order->table->fresh()->status);
    }
}
