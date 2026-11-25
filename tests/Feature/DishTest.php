<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Dish;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DishTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function should_require_authentication_to_browse_the_menu(): void
    {
        $response = $this->getJson('/api/dishes');

        $response->assertUnauthorized();
    }

    #[Test]
    public function should_filter_dishes_by_category(): void
    {
        $drinks = Category::factory()->create();
        Dish::factory()->for($drinks)->create(['name' => 'Limonada']);
        Dish::factory()->create(['name' => 'Bandeja paisa']);
        $this->actingAsRole(Role::Customer);

        $response = $this->getJson("/api/dishes?category_id={$drinks->id}");

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Limonada');
    }

    #[Test]
    public function should_filter_dishes_by_availability(): void
    {
        Dish::factory()->create(['name' => 'Limonada']);
        Dish::factory()->unavailable()->create(['name' => 'Mojarra frita']);
        $this->actingAsRole(Role::Customer);

        $response = $this->getJson('/api/dishes?available=false');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Mojarra frita');
    }

    #[Test]
    public function should_search_dishes_by_name_or_description_ignoring_case(): void
    {
        Dish::factory()->create(['name' => 'Arroz con leche', 'description' => 'Postre']);
        Dish::factory()->create(['name' => 'Jugo de lulo', 'description' => 'En agua o en leche']);
        Dish::factory()->create(['name' => 'Gaseosa', 'description' => 'Bebida']);
        $this->actingAsRole(Role::Customer);

        $response = $this->getJson('/api/dishes?search=LECHE');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    #[Test]
    public function should_cap_the_page_size_at_one_hundred(): void
    {
        $this->actingAsRole(Role::Customer);

        $response = $this->getJson('/api/dishes?per_page=5000');

        $response->assertOk()->assertJsonPath('meta.per_page', 100);
    }

    #[Test]
    public function should_create_a_dish_that_is_available_by_default(): void
    {
        $category = Category::factory()->create();
        $this->actingAsRole(Role::Admin);

        $response = $this->postJson('/api/dishes', [
            'category_id' => $category->id,
            'name' => 'Cazuela de mariscos',
            'price' => 42000.5,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.price', 42000.5)
            ->assertJsonPath('data.category.id', $category->id);
    }

    #[Test]
    public function should_answer_403_before_validating_when_the_user_cannot_create_dishes(): void
    {
        $this->actingAsRole(Role::Waiter);

        $response = $this->postJson('/api/dishes', []);

        $response->assertForbidden();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidDishes(): array
    {
        return [
            'unknown category' => [['category_id' => 999999], 'category_id'],
            'negative price' => [['price' => -1], 'price'],
            'three decimals' => [['price' => 10.555], 'price'],
            'empty name' => [['name' => ''], 'name'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[Test]
    #[DataProvider('invalidDishes')]
    public function should_reject_invalid_dish_data(array $override, string $invalidField): void
    {
        $category = Category::factory()->create();
        $this->actingAsRole(Role::Admin);
        $payload = array_merge(['category_id' => $category->id, 'name' => 'Plato', 'price' => 1000], $override);

        $response = $this->postJson('/api/dishes', $payload);

        $response->assertUnprocessable()->assertJsonValidationErrors($invalidField);
    }

    #[Test]
    public function should_return_a_clean_404_for_a_missing_dish(): void
    {
        $this->actingAsRole(Role::Customer);

        $missing = $this->getJson('/api/dishes/999999');
        $notNumeric = $this->getJson('/api/dishes/abc');

        $missing->assertNotFound()->assertExactJson(['message' => 'Dish not found.']);
        $notNumeric->assertNotFound()->assertExactJson(['message' => 'Endpoint not found.']);
    }

    #[Test]
    public function should_refuse_to_delete_a_dish_that_was_already_sold(): void
    {
        $item = OrderItem::factory()->create();
        $this->actingAsRole(Role::Admin);

        $response = $this->deleteJson("/api/dishes/{$item->dish_id}");

        $response->assertConflict();
        $this->assertDatabaseHas('dishes', ['id' => $item->dish_id]);
    }
}
