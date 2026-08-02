<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\WorkoutType;

return new class extends Migration
{
    /**
     * Solo existia "Entrenamiento de fuerza" - se piden estos 4 estilos
     * de entrenamiento adicionales para poder filtrar workouts por tipo
     * en filter_workout_screen.tsx / view_workouts_screen.tsx.
     */
    public function up(): void
    {
        foreach (['HIIT', 'AFAP', 'EMOM', 'HYROX'] as $title) {
            WorkoutType::firstOrCreate(['title' => $title], ['status' => 'active']);
        }
    }

    public function down(): void
    {
        WorkoutType::whereIn('title', ['HIIT', 'AFAP', 'EMOM', 'HYROX'])->delete();
    }
};
