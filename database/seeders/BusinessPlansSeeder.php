<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BusinessPlansSeeder extends Seeder
{
    public function run(): void
    {
        // ═══ ENTRENAMIENTO PERSONAL (recurrente) ═══
        Plan::create([
            'name' => 'Entrenamiento Personal',
            'slug' => 'entrenamiento-personal',
            'description' => 'Suscripción mensual recurrente — acceso completo con entrenador personal.',
            'is_active' => true,
            'price' => 60.00,
            'signup_fee' => 0,
            'currency' => 'EUR',
            'invoice_period' => 1,
            'invoice_interval' => 'month',
            'trial_period' => 0,
            'trial_interval' => 'day',
            'grace_period' => 3,
            'grace_interval' => 'day',
            'sort_order' => 10,
        ]);

        // ═══ PROGRAMAS DE DURACIÓN FIJA ═══
        $programs = [
            ['name' => 'Hipertrofia', 'durations' => [3 => 150.00, 6 => 250.00]],
            ['name' => 'Definición', 'durations' => [3 => 140.00, 6 => 230.00]],
            ['name' => 'Fuerza', 'durations' => [3 => 130.00, 6 => 220.00]],
            ['name' => 'Pérdida de Peso', 'durations' => [3 => 120.00, 6 => 200.00]],
            ['name' => 'Preparación Competición', 'durations' => [4 => 200.00]],
        ];

        $order = 20;
        foreach ($programs as $prog) {
            foreach ($prog['durations'] as $months => $price) {
                Plan::create([
                    'name' => "Programa {$prog['name']} {$months} Meses",
                    'slug' => Str::slug("Programa {$prog['name']} {$months} Meses"),
                    'description' => "Programa de {$prog['name']} — duración fija de {$months} meses.",
                    'is_active' => true,
                    'price' => $price,
                    'signup_fee' => 0,
                    'currency' => 'EUR',
                    'invoice_period' => $months,
                    'invoice_interval' => 'month',
                    'trial_period' => 0,
                    'trial_interval' => 'day',
                    'grace_period' => 0,
                    'grace_interval' => 'day',
                    'sort_order' => $order++,
                ]);
            }
        }

        // ═══ FEATURES PARA ENTRENAMIENTO PERSONAL ═══
        $personalPlan = Plan::where('slug', 'entrenamiento-personal')->first();
        if ($personalPlan) {
            $features = [
                ['name' => 'Entrenamientos / semana', 'slug' => 'workouts_per_week', 'value' => 'Ilimitado', 'sort_order' => 1],
                ['name' => 'Dietas asignadas', 'slug' => 'diets', 'value' => 'Ilimitado', 'sort_order' => 2],
                ['name' => 'Check-ins / mes', 'slug' => 'checkins_per_month', 'value' => 'Ilimitado', 'sort_order' => 3],
                ['name' => 'Coaching 1-a-1', 'slug' => 'one_on_one', 'value' => 'Sí', 'sort_order' => 4],
                ['name' => 'Videollamadas / mes', 'slug' => 'video_calls', 'value' => '4', 'resettable_period' => 1, 'resettable_interval' => 'month', 'sort_order' => 5],
                ['name' => 'Acceso a biblioteca de recetas', 'slug' => 'recipe_library', 'value' => 'Sí', 'sort_order' => 6],
            ];
            foreach ($features as $f) {
                $personalPlan->features()->create($f);
            }
        }

        // Features básicas para cada programa
        $programPlans = Plan::where('slug', 'like', 'programa-%')->get();
        foreach ($programPlans as $plan) {
            $plan->features()->createMany([
                ['name' => 'Entrenamientos / semana', 'slug' => 'workouts_per_week', 'value' => 'Ilimitado', 'sort_order' => 1],
                ['name' => 'Dietas asignadas', 'slug' => 'diets', 'value' => '2', 'resettable_period' => 1, 'resettable_interval' => 'month', 'sort_order' => 2],
                ['name' => 'Check-ins / mes', 'slug' => 'checkins_per_month', 'value' => '4', 'resettable_period' => 1, 'resettable_interval' => 'month', 'sort_order' => 3],
                ['name' => 'Soporte chat', 'slug' => 'chat_support', 'value' => 'Sí', 'sort_order' => 4],
            ]);
        }

        $this->command?->info('Planes de negocio creados: Entrenamiento Personal + ' . $programPlans->count() . ' programas.');
    }
}
