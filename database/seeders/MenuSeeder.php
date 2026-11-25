<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Demo menu grouped by category: [name, description, price, available].
     *
     * @var array<string, array<int, array{0: string, 1: string, 2: int, 3: bool}>>
     */
    private const MENU = [
        'Entradas' => [
            ['Empanadas', 'Tres empanadas de carne con ají casero', 9000, true],
            ['Patacones con hogao', 'Plátano verde frito con hogao y queso costeño', 12000, true],
            ['Arepa de chócolo', 'Arepa de maíz tierno con queso derretido', 10000, true],
        ],
        'Platos fuertes' => [
            ['Bandeja paisa', 'Frijoles, arroz, chicharrón, carne molida, huevo, chorizo, maduro y aguacate', 38000, true],
            ['Ajiaco santafereño', 'Sopa de tres papas con pollo, mazorca, alcaparras y crema', 32000, true],
            ['Sancocho de gallina', 'Sancocho tradicional con yuca, plátano y mazorca', 30000, true],
            ['Mojarra frita', 'Mojarra entera con patacón, arroz de coco y ensalada', 36000, false],
        ],
        'Bebidas' => [
            ['Limonada de coco', 'Limonada natural con crema de coco', 9500, true],
            ['Jugo de lulo', 'Jugo natural en agua o en leche', 7000, true],
            ['Aguapanela con limón', 'Bebida fría de panela con limón', 5000, true],
            ['Gaseosa', 'Gaseosa personal 400 ml', 5500, true],
        ],
        'Postres' => [
            ['Brownie con helado', 'Brownie de chocolate tibio con helado de vainilla', 12000, true],
            ['Arroz con leche', 'Arroz con leche con canela y uvas pasas', 8000, true],
            ['Torta tres leches', 'Bizcocho bañado en tres leches con merengue', 10000, true],
        ],
    ];

    /**
     * Only creates what is missing: never overwrites dishes the restaurant edited through the API.
     */
    public function run(): void
    {
        foreach (self::MENU as $categoryName => $dishes) {
            $category = Category::firstOrCreate(['name' => $categoryName]);

            foreach ($dishes as [$name, $description, $price, $available]) {
                $category->dishes()->firstOrCreate(
                    ['name' => $name],
                    ['description' => $description, 'price' => $price, 'available' => $available],
                );
            }
        }
    }
}
