<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Dish;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dish>
 */
class DishFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(30, 600) * 100,
            'available' => true,
        ];
    }

    public function unavailable(): static
    {
        return $this->state([
            'available' => false,
        ]);
    }
}
