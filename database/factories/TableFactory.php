<?php

namespace Database\Factories;

use App\Enums\TableStatus;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Table>
 */
class TableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(1, 999),
            'capacity' => fake()->randomElement([2, 4, 6, 8]),
            'status' => TableStatus::Free,
        ];
    }

    public function occupied(): static
    {
        return $this->state([
            'status' => TableStatus::Occupied,
        ]);
    }

    public function reserved(): static
    {
        return $this->state([
            'status' => TableStatus::Reserved,
        ]);
    }
}
