<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\Resource;
use Illuminate\Database\Seeder;

class RemainingDataSeeder extends Seeder
{
    public function run(): void
    {
        Challenge::create([
            'coach_id' => 1, 'title' => '30 días de plancha',
            'description' => 'Añade 5 segundos cada día', 'metric_type' => 'duration',
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->addDays(25)->toDateString(), 'scope' => 'all',
        ]);
        Challenge::create([
            'coach_id' => 1, 'title' => 'Reto 100 flexiones',
            'description' => 'Llega a 100 flexiones en un día', 'metric_type' => 'count',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(), 'scope' => 'all',
        ]);

        foreach ([
            ['title' => 'Guía de calentamiento', 'type' => 'pdf', 'category' => 'guides', 'coach_id' => 1],
            ['title' => 'Tabla de ejercicios básicos', 'type' => 'pdf', 'category' => 'guides', 'coach_id' => 1],
            ['title' => 'Vídeo técnica sentadilla', 'type' => 'video', 'category' => 'videos', 'coach_id' => 1],
            ['title' => 'Plantilla de progreso', 'type' => 'spreadsheet', 'category' => 'tools', 'coach_id' => 1],
        ] as $r) {
            Resource::create($r);
        }

        $this->command?->info('Challenges: 2, Resources: 4');
    }
}
