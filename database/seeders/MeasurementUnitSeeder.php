<?php

namespace Database\Seeders;

use App\Models\MeasurementUnit;
use Illuminate\Database\Seeder;

class MeasurementUnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['title' => 'Gramo',        'symbol' => 'g',      'unit_type' => 'weight', 'base_conversion_factor' => 1,     'is_standard' => true],
            ['title' => 'Kilogramo',    'symbol' => 'kg',     'unit_type' => 'weight', 'base_conversion_factor' => 1000,  'is_standard' => false],
            ['title' => 'Onza',         'symbol' => 'oz',     'unit_type' => 'weight', 'base_conversion_factor' => 28.35, 'is_standard' => false],
            ['title' => 'Libra',        'symbol' => 'lb',     'unit_type' => 'weight', 'base_conversion_factor' => 453.6, 'is_standard' => false],
            ['title' => 'Mililitro',    'symbol' => 'ml',     'unit_type' => 'volume', 'base_conversion_factor' => 1,     'is_standard' => false],
            ['title' => 'Litro',        'symbol' => 'L',      'unit_type' => 'volume', 'base_conversion_factor' => 1000,  'is_standard' => false],
            ['title' => 'Taza',         'symbol' => 'cup',    'unit_type' => 'volume', 'base_conversion_factor' => 236.6, 'is_standard' => false],
            ['title' => 'Cucharada',    'symbol' => 'tbsp',   'unit_type' => 'volume', 'base_conversion_factor' => 14.79, 'is_standard' => false],
            ['title' => 'Cucharadita',  'symbol' => 'tsp',    'unit_type' => 'volume', 'base_conversion_factor' => 4.93,  'is_standard' => false],
            ['title' => 'Pieza',        'symbol' => 'pc',     'unit_type' => 'count',  'base_conversion_factor' => 1,     'is_standard' => false],
            ['title' => 'Rebanada',     'symbol' => 'slice',  'unit_type' => 'count',  'base_conversion_factor' => 1,     'is_standard' => false],
            ['title' => 'Unidad',       'symbol' => 'unit',   'unit_type' => 'count',  'base_conversion_factor' => 1,     'is_standard' => false],
            ['title' => 'Pizca',        'symbol' => 'pinch',  'unit_type' => 'count',  'base_conversion_factor' => 0.5,   'is_standard' => false],
            ['title' => 'Diente',       'symbol' => 'cloves', 'unit_type' => 'count',  'base_conversion_factor' => 1,     'is_standard' => false],
            ['title' => 'Pelado',       'symbol' => 'peeled', 'unit_type' => 'count',  'base_conversion_factor' => 1,     'is_standard' => false],
        ];

        foreach ($units as $unit) {
            MeasurementUnit::updateOrCreate(
                ['symbol' => $unit['symbol']],
                $unit
            );
        }

        $this->command->info('Seeded ' . count($units) . ' measurement units.');
    }
}
