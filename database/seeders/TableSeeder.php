<?php

namespace Database\Seeders;

use App\Models\Table;
use Illuminate\Database\Seeder;

class TableSeeder extends Seeder
{
    /**
     * Capacity of each demo table, indexed by table number.
     *
     * @var array<int, int>
     */
    private const CAPACITIES = [1 => 2, 2 => 2, 3 => 4, 4 => 4, 5 => 4, 6 => 4, 7 => 6, 8 => 6, 9 => 8, 10 => 8];

    public function run(): void
    {
        foreach (self::CAPACITIES as $number => $capacity) {
            Table::firstOrCreate(['number' => $number], ['capacity' => $capacity]);
        }
    }
}
