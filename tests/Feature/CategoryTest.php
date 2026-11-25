<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Dish;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function should_list_categories_with_their_dish_count(): void
    {
        $drinks = Category::factory()->create(['name' => 'Bebidas']);
        Dish::factory()->count(2)->for($drinks)->create();
        $this->actingAsRole(Role::Customer);

        $response = $this->getJson('/api/categories');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Bebidas')
            ->assertJsonPath('data.0.dishes_count', 2)
            ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'total']]);
    }

    #[Test]
    public function should_create_a_category_when_the_user_is_admin(): void
    {
        $this->actingAsRole(Role::Admin);

        $response = $this->postJson('/api/categories', ['name' => 'Postres']);

        $response->assertCreated()->assertJsonPath('data.name', 'Postres');
        $this->assertDatabaseHas('categories', ['name' => 'Postres']);
    }

    /**
     * @return array<string, array{0: Role}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'waiter' => [Role::Waiter],
            'kitchen' => [Role::Kitchen],
            'customer' => [Role::Customer],
        ];
    }

    #[Test]
    #[DataProvider('nonAdminRoles')]
    public function should_forbid_menu_management_to_non_admin_roles(Role $role): void
    {
        $category = Category::factory()->create();
        $this->actingAsRole($role);

        $create = $this->postJson('/api/categories', ['name' => 'Nueva']);
        $update = $this->putJson("/api/categories/{$category->id}", ['name' => 'Cambiada']);
        $delete = $this->deleteJson("/api/categories/{$category->id}");

        $create->assertForbidden();
        $update->assertForbidden();
        $delete->assertForbidden();
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => $category->name]);
    }

    #[Test]
    public function should_reject_a_duplicate_category_name(): void
    {
        Category::factory()->create(['name' => 'Bebidas']);
        $this->actingAsRole(Role::Admin);

        $response = $this->postJson('/api/categories', ['name' => 'Bebidas']);

        $response->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    #[Test]
    public function should_allow_renaming_a_category_to_its_own_name(): void
    {
        $category = Category::factory()->create(['name' => 'Bebidas']);
        $this->actingAsRole(Role::Admin);

        $response = $this->putJson("/api/categories/{$category->id}", ['name' => 'Bebidas']);

        $response->assertOk();
    }

    #[Test]
    public function should_refuse_to_delete_a_category_that_has_dishes(): void
    {
        $category = Category::factory()->has(Dish::factory())->create();
        $this->actingAsRole(Role::Admin);

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertConflict();
        $this->assertModelExists($category);
    }

    #[Test]
    public function should_delete_an_empty_category(): void
    {
        $category = Category::factory()->create();
        $this->actingAsRole(Role::Admin);

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertNoContent();
        $this->assertModelMissing($category);
    }
}
