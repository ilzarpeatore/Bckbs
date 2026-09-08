<?php

namespace App\Services;

use App\Enums\AchievementEventType;
use App\Models\AchievementEvent;
use App\Models\ExerciseSessionMetric;
use App\Models\ProgramClientAssignment;
use App\Models\TrainingProgram;
use App\Services\Concerns\ComputesLinearSlope;
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
    use ComputesLinearSlope;


    /**
     * Ítem 20 (docs/Motor_Autorregulacion_Analisis.md, Plan de Optimización,
     * Ronda 5) — mínimo de sesiones válidas para ajustar una regresión
     * lineal sobre carga_efectiva en vez de comparar solo primera vs.
     * última. Con 2 puntos la recta pasa exactamente por ambos (equivale al
     * comportamiento antiguo, no suaviza nada) — se exige un mínimo de 3
     * para que el intercepto realmente incorpore información de al menos
     * una sesión intermedia. Umbral elegido por criterio propio (no viene
     * del "documento" de diseño original), documentado aquí.
     */
    private const MIN_SESSIONS_FOR_LINEAR_TREND = 3;

    /**
     * Umbral bajo el cual la pendiente de la regresión se considera
     * "prácticamente plana": por debajo de esta variación relativa por
     * sesión (0.1% de la carga media del grupo), el intercepto queda tan
     * cerca del valor medio que sustituir la primera sesión real por él no
     * aporta nada y sí resta transparencia al dato — se mantiene el
     * fallback de comparar contra la primera sesión real. Umbral elegido
     * por criterio propio, documentado aquí.
     */
    private const FLAT_SLOPE_RELATIVE_THRESHOLD = 0.001;

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
     * Compara la TENDENCIA completa de volumen_total del mesociclo (todas
     * las sesiones válidas, no solo primera vs. última), por ejercicio
     * principal, y persiste un achievement_event por ejercicio con datos
     * suficientes.
     *
     * Ítem 20 (docs/Motor_Autorregulacion_Analisis.md, Plan de Optimización,
     * Ronda 5): antes se comparaba únicamente first() vs. last(), sensible a
     * que cualquiera de esos dos puntos fuera un outlier puntual (día de
     * test de calibración, mal día aislado). Ahora, cuando hay suficientes
     * sesiones (>= MIN_SESSIONS_FOR_LINEAR_TREND), se ajusta una regresión
     * lineal simple en orden cronológico y se usa el INTERCEPTO (valor que
     * tendría la recta en la primera sesión, x=0) como previous_best en vez
     * del valor crudo de la primera sesión, para suavizar el efecto de que
     * esa sesión concreta fuera atípica. `value` sigue siendo el valor real
     * de la ÚLTIMA sesión válida (sigue siendo relevante saber dónde
     * terminó el cliente el bloque). Si hay menos de 3 sesiones válidas, o
     * la pendiente resulta prácticamente plana (ver
     * FLAT_SLOPE_RELATIVE_THRESHOLD), cae al comportamiento original
     * (primera sesión real como previous_best).
     *
     * CAMBIO DE MÉTRICA (Plan de Optimización, Ronda 10 ítem 29): antes
     * `value`/`previous_best` se calculaban sobre `carga_efectiva` (el pico
     * de UN set del ejercicio principal). Ahora usan `volumen_total`
     * (tonelaje real, Ronda 8) -- la métrica más honesta para "cuánto
     * trabajaste en este ejercicio durante el bloque", en vez de solo el
     * peso máximo levantado. La lógica de regresión/intercepto (ítem 20)
     * no cambia, solo la columna de origen. Igual que en
     * ReadinessCalculationService::acwr() (ítem 28), esto cambia el
     * significado de `mesociclo_cerrado` para achievement_events nuevos --
     * comunicar el cambio al desplegar, no silencioso.
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
            ->whereNotNull('volumen_total')
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

            // Fallback por defecto: comportamiento original (primera sesión real).
            $previousBest = $first->volumen_total;

            if ($group->count() >= self::MIN_SESSIONS_FOR_LINEAR_TREND) {
                $values = $group->map(fn ($m) => (float) $m->volumen_total)->values()->all();
                $regression = $this->linearRegression($values);

                if ($regression !== null) {
                    $meanVolumen = array_sum($values) / count($values);
                    $relativeSlope = $meanVolumen == 0.0 ? 0.0 : abs($regression['slope']) / abs($meanVolumen);

                    // Pendiente prácticamente plana: el intercepto quedaría
                    // casi idéntico al valor medio, sin aportar nada frente
                    // al dato real de la primera sesión -- se mantiene el
                    // fallback ya asignado arriba.
                    if ($relativeSlope >= self::FLAT_SLOPE_RELATIVE_THRESHOLD) {
                        $previousBest = $regression['intercept'];
                    }
                }
            }

            AchievementEvent::create([
                'client_id'                 => $assignment->client_id,
                'type'                       => AchievementEventType::MESOCICLO_CERRADO->value,
                'exercise_id'                => $exerciseId,
                'value'                      => $last->volumen_total,
                'previous_best'              => $previousBest,
                'significancia_verificada'   => true,
                'source_type'                => ProgramClientAssignment::class,
                'source_id'                  => $assignment->id,
            ]);
        }
    }

    /**
     * Pendiente e intercepto de una regresión lineal simple sobre $values
     * (eje X = 0..n-1, en orden cronológico).
     *
     * CONSOLIDACIÓN (Plan de Optimización, Ronda 3 ítem 9, decisión abierta
     * #3 del handoff de verificación): esta era, a sabiendas, una tercera
     * copia del mismo cálculo que SessionInterpretationService::linearSlope()
     * y SessionProgressionRuleEngine::linearSlope() — ahora reutiliza el
     * núcleo compartido `ComputesLinearSlope::computeRawLinearSlope()`
     * (misma pendiente) y solo calcula aquí el intercepto (`meanY - slope *
     * meanX`), que es lo único que este servicio necesita y los otros dos
     * no. Cero cambio de comportamiento respecto a la copia local anterior.
     *
     * @return array{slope: float, intercept: float}|null null si hay menos de 2 puntos (no hay recta que ajustar).
     */
    private function linearRegression(array $values): ?array
    {
        $n = count($values);
        if ($n < 2) {
            return null;
        }

        $slope = $this->computeRawLinearSlope($values);
        $meanX = array_sum(range(0, $n - 1)) / $n;
        $meanY = array_sum($values) / $n;
        $intercept = $meanY - $slope * $meanX;

        return ['slope' => $slope, 'intercept' => $intercept];
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
