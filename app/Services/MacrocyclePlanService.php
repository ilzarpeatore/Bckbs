<?php

namespace App\Services;

use App\Models\BodyPart;
use App\Models\ProgramDayAssignment;
use App\Models\TrainingProgram;
use Illuminate\Support\Collection;

/**
 * Dashboard de KPIs de un macrociclo (página /macrociclos del panel, pedido
 * 2026-09-27): lo PLANIFICADO por el coach -- nunca lo registrado por el
 * cliente. Aplana cada ejercicio de cada sesión de cada semana de cada
 * mesociclo (TrainingProgram) en una fila con su `prescribed` tal cual
 * (series, reps, rir, rpe, carga...; strings, pueden ser rangos "8-10" o
 * texto "Subir"/"Mantener"/"Bajar") y su grupo muscular primario. La
 * agregación (por semana / mesociclo / macrociclo, series directas o con
 * indirectas) la hace el panel, que es quien tiene el selector.
 *
 * Mismo origen de datos que ProgramSessionMatrixService::build() (el editor
 * de sesiones): program_day_assignments -> workout_template -> blocks ->
 * exercises.
 */
class MacrocyclePlanService
{
    /**
     * @param  Collection<int, TrainingProgram>  $programs
     * @param  array<int, int|null>  $mesocycleNumbers  program_id => nº de mesociclo
     */
    public function build(Collection $programs, array $mesocycleNumbers): array
    {
        $programIds = $programs->pluck('id')->all();

        $assignments = ProgramDayAssignment::whereIn('training_program_id', $programIds)
            ->whereNotNull('workout_template_id')
            ->with(['workoutTemplate.blocks.exercises.exercise'])
            ->orderBy('training_program_id')
            ->orderBy('week_number')
            ->orderBy('day_of_week')
            ->orderBy('id')
            ->get()
            ->filter(fn ($a) => $a->workoutTemplate !== null);

        $bodyPartTitles = BodyPart::pluck('title', 'id');

        $sessions = [];
        $rows = [];
        $splits = [];

        foreach ($assignments as $a) {
            [, $sessionLabel] = ProgramSessionMatrixService::sessionStem($a->workoutTemplate->title);
            $sessions[] = [
                'assignment_id' => $a->id,
                'program_id'    => (int) $a->training_program_id,
                'week'          => (int) $a->week_number,
                'day'           => (int) $a->day_of_week,
                'is_deload'     => (bool) $a->is_deload,
                'session'       => $sessionLabel,
            ];

            foreach ($a->workoutTemplate->blocks as $block) {
                foreach ($block->exercises as $ex) {
                    $firstId = MuscleVolumeService::firstBodyPartId($ex->exercise?->bodypart_ids);
                    $primary = $firstId !== null ? ($bodyPartTitles[$firstId] ?? null) : null;
                    if ($primary !== null && !isset($splits[$primary])) {
                        $splits[$primary] = MuscleVolumeService::getMuscleSplit($primary);
                    }

                    $p = (array) ($ex->prescribed ?? []);
                    $rows[] = [
                        'assignment_id'  => $a->id,
                        'exercise_id'    => (int) $ex->exercise_id,
                        'exercise_title' => $ex->exercise?->title ?? ('Ejercicio #'.$ex->exercise_id),
                        'block_title'    => $block->title,
                        'muscle'         => $primary,
                        'series'         => self::str($p['series'] ?? null),
                        'reps'           => self::str($p['reps'] ?? null),
                        'rir'            => self::str($p['rir'] ?? null),
                        'rpe'            => self::str($p['rpe'] ?? null),
                        'carga'          => self::str($p['carga'] ?? null),
                        'carga_pct'      => self::str($p['carga_pct'] ?? null),
                        'descanso'       => self::str($p['descanso'] ?? null),
                        'tecnica'        => self::str($p['tecnica'] ?? null),
                        'tecnica_series' => self::str($p['tecnica_series'] ?? null),
                        'tecnica_otra'   => self::str($p['tecnica_otra'] ?? null),
                    ];
                }
            }
        }

        return [
            'programs' => $programs->map(fn (TrainingProgram $p) => [
                'id'               => $p->id,
                'title'            => $p->title,
                'mesocycle_number' => $mesocycleNumbers[$p->id] ?? null,
                'num_weeks'        => $p->num_weeks,
            ])->values()->all(),
            'sessions'      => $sessions,
            'rows'          => $rows,
            // grupo primario => { músculo => multiplicador } (series indirectas, ver MuscleVolumeService)
            'muscle_splits' => (object) $splits,
        ];
    }

    private static function str(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
