<?php

namespace Database\Seeders;

use App\Models\IngredientCategory;
use Illuminate\Database\Seeder;

class IngredientCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Carnes',
            'Verduras',
            'Frutas',
            'Granos y Pastas',
            'Lácteos',
            'Aceites y Grasas',
            'Frutos Secos y Semillas',
            'Legumbres',
            'Especias y Hierbas',
            'Condimentos y Salsas',
            'Bebidas',
            'Panadería',
            'Mariscos',
            'Procesados',
            'Endulzantes',
        ];

        foreach ($categories as $title) {
            IngredientCategory::updateOrCreate(
                ['title' => $title],
                ['status' => 'active']
            );
        }

        $this->command->info('Seeded ' . count($categories) . ' ingredient categories.');
    }
}
