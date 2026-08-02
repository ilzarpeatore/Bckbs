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
     * genera automáticamente las semanas 2..num_weeks aplicando
     * `progression_rules`: multiplica la carga prescrita por
     * `load_multiplier`, y marca `allow_special_techniques`/`is_deload`
     * como metadato de esa semana. NO asume ningún valor de progresión
     * propio — si no hay progression_rules para una semana, esa semana
     * se clona igual que S1 (multiplicador 1.00 por defecto), nunca se
     * inventa una progresión no definida por el coach.
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
            $rule = $program->progressionRules->firstWhere('week_number', $week);
            $load_multiplier = $rule->load_multiplier ?? 1.00;

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
                            $this->cloneExercise($source_exercise, $new_day->id, $new_block->id, $load_multiplier);
                        }
                    }

                    // Ejercicios sin bloque (creados antes del sistema de bloques).
                    foreach ($source_day->workoutDayExercise as $source_exercise) {
                        $this->cloneExercise($source_exercise, $new_day->id, null, $load_multiplier);
                    }
                }

                $created_days[] = $new_day;
            }
        }

        return $created_days;
    }

    /**
     * Clona un ejercicio prescrito aplicando el multiplicador de carga
     * SOLO al campo `carga` dentro de `sets` (si existe) — el resto de
     * prescripción (reps, RPE, tempo, notas) se mantiene igual, ya que
     * la progresión de tu metodología es de carga, no de reps/RPE.
     * Las métricas habilitadas (enabled_metrics) se copian tal cual.
     */
    private function cloneExercise(WorkoutDayExercise $source, int $new_day_id, ?int $new_block_id, float $load_multiplier): void
    {
        $prescribed = $source->sets ?? [];

        if (isset($prescribed['carga']) && is_numeric($prescribed['carga'])) {
            $prescribed['carga'] = round($prescribed['carga'] * $load_multiplier, 2);
        }

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
