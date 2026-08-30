<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateBlock;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Database\Seeder;

class DemoWorkoutTemplateSeeder extends Seeder
{
    /**
     * Entrenamiento de bienvenida: se auto-asigna al calendario personal
     * de todo cliente nuevo en el registro (ver
     * UserController::assignDemoWorkoutIfNeeded()), para que el tutorial
     * guiado ("Registra tu primera serie") tenga algo real sobre lo que
     * apuntar el primer día, en vez de un calendario vacío.
     *
     * Idempotente: usa updateOrCreate/firstOrCreate, así que correr el
     * seeder varias veces no duplica ni la plantilla ni sus bloques.
     */
    public function run(): void
    {
        // El WorkoutTemplate requiere coach_id (FK not-null). No es la
        // plantilla de un coach concreto — usamos el System Admin
        // sembrado por UserTableSeeder como "propietario" del contenido
        // estándar de la app, mismo criterio que StandardProfileFormSeeder.
        $ownerId = User::where('user_type', 'admin')->value('id')
            ?? User::query()->value('id');

        if (!$ownerId) {
            // No hay ningún usuario todavía (seeder corriendo antes que
            // UserTableSeeder) — no podemos crear la plantilla sin coach_id.
            return;
        }

        // Necesitamos algún Exercise al que referenciar. No hay ningún
        // ExerciseSeeder en este proyecto que garantice datos de partida,
        // así que si la tabla está vacía (instalación nueva) se crea uno
        // trivial — todos sus campos son nullable salvo el id.
        $exercise = Exercise::first();
        if (!$exercise) {
            $exercise = Exercise::create([
                'title'  => 'Sentadilla con peso corporal',
                'status' => 'active',
                'based'  => 'reps',
                'type'   => 'sets',
            ]);
        }

        $template = WorkoutTemplate::updateOrCreate(
            ['title' => 'Entrenamiento de bienvenida'],
            [
                'coach_id'    => $ownerId,
                'description' => 'Tu primer entrenamiento guiado — perfecto para aprender a registrar tus series.',
                'is_exclusive' => false,
                'is_demo'     => true,
            ]
        );

        $block = WorkoutTemplateBlock::updateOrCreate(
            ['workout_template_id' => $template->id, 'title' => 'Bloque principal'],
            ['instructions' => null, 'order' => 0]
        );

        // Claves EXACTAS del catálogo de métricas (metrics_catalog) y las
        // que ya lee el resto del código (p.ej. ClientCalendarController
        // ya lee prescribed['carga']) — no se inventan claves nuevas que
        // el cliente no reconocería. Se rellenan series/reps/carga/descanso
        // /rir/rpe para que el tutorial pueda explicar las cuatro.
        WorkoutTemplateExercise::updateOrCreate(
            ['workout_template_block_id' => $block->id, 'exercise_id' => $exercise->id],
            [
                'sequence' => 0,
                'prescribed' => [
                    'series'   => 3,
                    'reps'     => 10,
                    'carga'    => 20,
                    'descanso' => 60,
                    'rir'      => 2,
                    'rpe'      => 7,
                ],
                'enabled_metrics' => ['series', 'reps', 'carga', 'descanso', 'rir', 'rpe'],
            ]
        );
    }
}
