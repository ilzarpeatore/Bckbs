<?php

namespace App\Services;

use App\Enums\AchievementEventType;
use App\Models\AchievementEvent;
use App\Models\ExerciseSessionMetric;
use App\Models\ProgramClientAssignment;
use App\Models\TrainingProgram;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Motor de Auto-Regulación de Carga — cierre automático de mesociclo
 * (achievement_events tipo mesociclo_cerrado). Hasta ahora era un valor de
 * enum sin lógica (ver docblock de AchievementEventType) porque no existía
 * ningún trigger real de "fin de mesociclo" — program_client_assignments
 * ahora sí lo tiene, vía fecha_fin (calculada y guardada al asignar/renovar,
 * ver ProgramClientAssignment::computeFechaFin()).
 *
 * Se evalúa TODA asignación activa con fecha_fin superada, esté o no el
 * cliente en paid-tier — el gate solo decide si se escribe el
 * achievement_event, nunca si la asignación se marca como cerrada (mismo
 * criterio que pain_reports: el hecho de negocio "el mesociclo terminó" no
 * depende del tier, solo la evidencia visible sí). Marcar cerrado_at
 * siempre evita que el job reevalúe para siempre asignaciones de clientes
 * free, que jamás van a generar el evento.
 */
class MesocycleClosureService
{
    /**
     * @return int Número de asignaciones cerradas en esta pasada.
     */
    public function closeEligibleAssignments(?Carbon $date = null): int
    {
        $date = $date ?? now()->startOfDay();
        $count = 0;

        ProgramClientAssignment::where('activo', true)
            ->whereNull('cerrado_at')
            ->whereNotNull('fecha_fin')
            ->where('fecha_fin', '<', $date->toDateString())
            ->with(['trainingProgram', 'client'])
            ->chunkById(100, function ($assignments) use ($date, &$count) {
                foreach ($assignments as $assignment) {
                    $this->closeAssignment($assignment, $date);
                    $count++;
                }
            });

        return $count;
    }

    private function closeAssignment(ProgramClientAssignment $assignment, Carbon $date): void
    {
        if ($assignment->client && Gate::forUser($assignment->client)->allows('paid-tier')) {
            $this->persistComparisons($assignment);
        }

        // SIEMPRE, gate o no -- idempotencia del job, ver docblock de clase.
        $assignment->cerrado_at = $date;
        $assignment->save();
    }

    /**
     * Compara carga_efectiva de la primera vs. la última sesión válida del
     * mesociclo, por ejercicio principal, y persiste un achievement_event
     * por ejercicio con datos suficientes.
     */
    private function persistComparisons(ProgramClientAssignment $assignment): void
    {
        $program = $assignment->trainingProgram;
        if (!$program) {
            return;
        }

        $exerciseIds = $this->principalExerciseIds($program);
        if (empty($exerciseIds)) {
            return;
        }

        $metrics = ExerciseSessionMetric::whereIn('exercise_id', $exerciseIds)
            ->where('client_id', $assignment->client_id)
            ->where('is_outlier', false)
            ->where('sin_dato_suficiente', false)
            ->whereNotNull('carga_efectiva')
            ->whereHas('workoutSessionReview', function ($q) use ($assignment) {
                $q->whereBetween('completed_at', [
                        Carbon::parse($assignment->start_date)->startOfDay(),
                        Carbon::parse($assignment->fecha_fin)->endOfDay(),
                    ])
                    ->whereHas('programDayAssignment', function ($q2) use ($assignment) {
                        $q2->where('training_program_id', $assignment->training_program_id);
                    });
            })
            ->with('workoutSessionReview')
            ->get()
            ->filter(fn ($m) => $m->workoutSessionReview !== null)
            ->sortBy(fn ($m) => $m->workoutSessionReview->completed_at)
            ->groupBy('exercise_id');

        foreach ($metrics as $exerciseId => $group) {
            // Hace falta al menos 2 sesiones válidas distintas para que una
            // comparativa inicial-vs-final tenga sentido -- un único dato no
            // es una progresión, evita un logro engañoso (previous_best ==
            // value).
            if ($group->count() < 2) {
                continue;
            }

            $first = $group->first();
            $last = $group->last();

            AchievementEvent::create([
                'client_id'                 => $assignment->client_id,
                'type'                       => AchievementEventType::MESOCICLO_CERRADO->value,
                'exercise_id'                => $exerciseId,
                'value'                      => $last->carga_efectiva,
                'previous_best'              => $first->carga_efectiva,
                'significancia_verificada'   => true,
                'source_type'                => ProgramClientAssignment::class,
                'source_id'                  => $assignment->id,
            ]);
        }
    }

    /**
     * "Ejercicio principal" no es un concepto modelado en el esquema (no
     * existe es_principal/categoria en workout_template_exercises) -- se
     * reutiliza el mismo proxy ya usado y documentado en
     * AdaptiveWeekPlanner::accessoryExerciseIdsToTrim(): el primer ejercicio
     * (sequence más bajo) de cada bloque es el principal de ese bloque, el
     * resto son accesorios. Distinct exercise_id across todo el programa.
     */
    private function principalExerciseIds(TrainingProgram $program): array
    {
        $ids = [];

        $dayAssignments = $program->dayAssignments()
            ->whereNotNull('workout_template_id')
            ->with('workoutTemplate.blocks.exercises')
            ->get();

        foreach ($dayAssignments as $pda) {
            if (!$pda->workoutTemplate) {
                continue;
            }
            foreach ($pda->workoutTemplate->blocks as $block) {
                $principal = $block->exercises->first();
                if ($principal) {
                    $ids[$principal->exercise_id] = true;
                }
            }
        }

        return array_keys($ids);
    }
}
