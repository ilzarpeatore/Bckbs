<?php

namespace App\Services;

use App\Models\TrainingProgram;
use App\Models\WorkoutDay;
use App\Models\WorkoutDayExercise;
use App\Models\WorkoutDayBlock;

class TrainingProgramGeneratorService
{
    /**
     * A partir de los workout_days de la SEMANA 1 (ya creados a mano por
     * el coach, con su estructura de bloques/ejercicios/prescripción),
     * genera automáticamente las semanas 2..num_weeks clonando S1 tal
     * cual. El modelador de semanas de descarga (progression_rules,
     * load_multiplier/is_deload) se retiró (nunca se usó en producción,
     * 0 filas en 20 programas reales) — la progresión de carga real la
     * decide el Motor de Auto-Regulación (SessionProgressionRuleEngine)
     * sesión a sesión, no un multiplicador fijado al generar el programa.
     */
    public function generateFromWeekOne(TrainingProgram $program): array
    {
        $week_one_days = WorkoutDay::where('workout_id', $program->workout_id)
            ->where('week_number', 1)
            ->with(['blocks.exercises', 'workoutDayExercise' => function ($q) {
                $q->whereNull('workout_day_block_id');
            }])
            ->orderBy('sequence')
            ->get();

        if ($week_one_days->isEmpty()) {
            throw new \RuntimeException('No hay días en la Semana 1 de este workout — no hay nada de lo que partir para generar el resto de semanas.');
        }

        $created_days = [];

        for ($week = 2; $week <= $program->num_weeks; $week++) {
            foreach ($week_one_days as $source_day) {
                $new_day = WorkoutDay::create([
                    'workout_id'  => $source_day->workout_id,
                    'sequence'    => $source_day->sequence,
                    'is_rest'     => $source_day->is_rest,
                    'week_number' => $week,
                ]);

                if (!$source_day->is_rest) {
                    // Clonar bloques manteniendo el mismo título/orden.
                    $block_map = [];
                    foreach ($source_day->blocks as $source_block) {
                        $new_block = WorkoutDayBlock::create([
                            'workout_day_id' => $new_day->id,
                            'title'          => $source_block->title,
                            'order'          => $source_block->order,
                        ]);
                        $block_map[$source_block->id] = $new_block->id;

                        foreach ($source_block->exercises as $source_exercise) {
                            $this->cloneExercise($source_exercise, $new_day->id, $new_block->id);
                        }
                    }

                    // Ejercicios sin bloque (creados antes del sistema de bloques).
                    foreach ($source_day->workoutDayExercise as $source_exercise) {
                        $this->cloneExercise($source_exercise, $new_day->id, null);
                    }
                }

                $created_days[] = $new_day;
            }
        }

        return $created_days;
    }

    /**
     * Clona un ejercicio prescrito tal cual (reps, carga, RPE, tempo,
     * notas, enabled_metrics) — sin ningún multiplicador de carga.
     */
    private function cloneExercise(WorkoutDayExercise $source, int $new_day_id, ?int $new_block_id): void
    {
        $prescribed = $source->sets ?? [];

        WorkoutDayExercise::create([
            'workout_id'            => $source->workout_id,
            'workout_day_id'        => $new_day_id,
            'workout_day_block_id'  => $new_block_id,
            'exercise_id'           => $source->exercise_id,
            'sets'                  => $prescribed,
            'enabled_metrics'       => $source->enabled_metrics,
            'sequence'              => $source->sequence,
            'duration'              => $source->duration,
        ]);
    }
}
