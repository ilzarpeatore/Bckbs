<?php

namespace App\Services;

use App\Models\ClientExerciseCalibration;
use App\Models\ClientExerciseLog;
use App\Models\ClientExerciseOverride;
use App\Models\ExerciseSessionMetric;
use App\Models\PainReport;
use App\Models\PersonalRecord;
use App\Models\ProgramDayAssignment;
use App\Models\User;
use App\Models\WorkoutSessionReview;
use App\Models\WorkoutTemplateExercise;
use App\Services\Concerns\ComputesLinearSlope;
use Illuminate\Support\Collection;

/**
 * Motor de Auto-Regulación de Carga — Fase 1, Capa 2 (interpretación).
 *
 * Se dispara de forma asíncrona (job ProcessSessionInterpretation) tras
 * ClientCalendarController::finishSession(), SOLO para clientes paid-tier
 * (el gate ya se comprobó aguas arriba, en el punto de entrada — este
 * servicio asume que si se está ejecutando, ya pasó el gate).
 *
 * Reutiliza client_exercise_logs.logged_sets (JSON con claves libres
 * carga/reps/rir según enabled_metrics) en vez de una tabla set_logs
 * normalizada nueva — decisión ya confirmada. No existe un session_id
 * único como en el documento original: "sesión" se identifica exactamente
 * igual que ya hace finishSession() — por program_day_assignment_id (día
 * de programa, un único slot de calendario) o, si es un workout suelto,
 * por workout_template_id + performed_date del mismo día.
 *
 * IMPORTANTE (regla del documento, ver Notas generales del plan): ningún
 * componente de Fase 2 en adelante debe leer client_exercise_logs
 * directamente — siempre a través de exercise_session_metrics.
 */
class SessionInterpretationService
{
    use ComputesLinearSlope;

    // Umbral de desviación para marcar outlier (documento §1.2).
    private const OUTLIER_DEVIATION = 0.30;
    // Ventana de sesiones válidas usadas para comparar contra outliers.
    private const OUTLIER_WINDOW = 5;
    // >50% de sets sin RIR reportado => sin_dato_suficiente (documento §1.2).
    private const INSUFFICIENT_DATA_RATIO = 0.50;
    // Ventana de sesiones válidas para tendencias (documento §1.2, default N=3).
    private const TREND_WINDOW = 3;

    // Ventana de backfill (documento de la clase App\Jobs\BackfillClientSessionHistory).
    private const BACKFILL_WINDOW_DAYS = 90;

    /**
     * Motor de Auto-Regulación de Carga (2026-08-12) — reprocesa el
     * historial ya existente de un cliente que acaba de pasar a paid-tier.
     * Solo toca WorkoutSessionReview de los últimos 90 días que TODAVÍA no
     * tengan ninguna fila en exercise_session_metrics (idempotente: llamar
     * dos veces no reprocesa lo ya hecho, y permite reintentar tras un
     * fallo parcial sin duplicar trabajo). No comprueba el gate paid-tier
     * aquí -- se asume que quien llama (el observer/servicio disparador) ya
     * confirmó que el cliente es paid-tier en este momento.
     *
     * @return int número de WorkoutSessionReview procesadas.
     */
    public function backfillHistoryForClient(User $client): int
    {
        $alreadyProcessedReviewIds = ExerciseSessionMetric::where('client_id', $client->id)
            ->pluck('workout_session_review_id')
            ->unique();

        $reviews = WorkoutSessionReview::where('user_id', $client->id)
            ->where('completed_at', '>=', now()->subDays(self::BACKFILL_WINDOW_DAYS))
            ->whereNotIn('id', $alreadyProcessedReviewIds)
            ->get();

        foreach ($reviews as $review) {
            $this->processReview($review);
        }

        return $reviews->count();
    }

    /**
     * Punto de entrada único del servicio. Procesa todos los ejercicios
     * tocados en la sesión que cerró $review.
     */
    public function processReview(WorkoutSessionReview $review): void
    {
        $clientId = (int) $review->user_id;
        $programDayAssignmentId = $review->program_day_assignment_id;
        $performedDate = optional($review->completed_at)->toDateString() ?? now()->toDateString();

        $exerciseIds = $this->sessionLogsQuery($clientId, $programDayAssignmentId, $review->workout_template_id, $performedDate)
            ->pluck('exercise_id')
            ->unique();

        foreach ($exerciseIds as $exerciseId) {
            $this->processExercise($review, (int) $exerciseId, $programDayAssignmentId, $performedDate);
        }
    }

    private function sessionLogsQuery(int $clientId, ?int $programDayAssignmentId, ?int $workoutTemplateId, string $performedDate)
    {
        $query = ClientExerciseLog::where('client_id', $clientId);

        if ($programDayAssignmentId) {
            // program_day_assignment_id ya identifica un único slot de
            // calendario (una sola ocurrencia real), no hace falta acotar
            // por fecha también.
            $query->where('program_day_assignment_id', $programDayAssignmentId);
        } else {
            // Workout suelto sin programa: no hay identificador único de
            // ocurrencia (limitación real del esquema, ver reconciliación
            // del plan) — se acota por mismo día como aproximación
            // razonable y documentada.
            $query->whereNull('program_day_assignment_id')
                ->where('performed_date', $performedDate);
        }

        return $query;
    }

    private function processExercise(WorkoutSessionReview $review, int $exerciseId, ?int $programDayAssignmentId, string $performedDate): void
    {
        $clientId = (int) $review->user_id;

        $log = $this->sessionLogsQuery($clientId, $programDayAssignmentId, $review->workout_template_id, $performedDate)
            ->where('exercise_id', $exerciseId)
            ->orderByDesc('id')
            ->first();

        if (!$log) {
            return;
        }

        // Cold start: cada sesión completada con este ejercicio cuenta,
        // independientemente de si luego queda bloqueada por dolor o
        // marcada outlier (Fase 2 solo necesita saber "cuántas sesiones
        // reales" lleva, no su calidad).
        ClientExerciseCalibration::registerSessionCompleted($clientId, $exerciseId);

        // Paso previo con prioridad absoluta (documento §1.3): el bloqueo
        // por dolor no pasa por ninguna otra comprobación.
        $blockedByPain = $this->isBlockedByPain($clientId, $exerciseId, $programDayAssignmentId, $review->workout_template_id, $performedDate);

        $agg = $this->aggregateSetLogs($log);

        $metrics = ExerciseSessionMetric::updateOrCreate(
            [
                'workout_session_review_id' => $review->id,
                'exercise_id'                => $exerciseId,
            ],
            [
                'client_id'          => $clientId,
                // Slot exacto realmente logueado (no el primero que
                // matchee exercise_id en la plantilla) - se lee de aqui en
                // Fase 2 (resolveLastPrescribed) para no tener que abrir
                // client_exercise_logs desde ese servicio.
                'workout_template_exercise_id' => $log->workout_template_exercise_id,
                'rir_delta_sesion'   => $agg['rir_delta_sesion'],
                // Ítem 39 (Plan de Optimización, Ronda 13): RIR delta de la
                // serie top, ya calculado en aggregateSetLogs() pero nunca
                // persistido hasta ahora.
                'rir_delta_serie_top' => $agg['rir_delta_serie_top'],
                'completion_ratio'   => $agg['completion_ratio'],
                'peor_serie_index'   => $agg['peor_serie_index'],
                // Fase 2 (condición 'peor_serie', ver migración
                // 2026_08_11_090000): valor real del RIR del peor set, ya
                // se calculaba aquí (aggregateSetLogs) pero no se
                // persistía porque Fase 1 no lo necesitaba.
                'peor_serie_rir'     => $agg['peor_serie_rir'],
                'carga_efectiva'     => $agg['carga_efectiva'],
                // Ítem 38 (Plan de Optimización, Ronda 13): reps de la
                // serie que define carga_efectiva -- ya se calculaba aquí
                // (aggregateSetLogs) pero nunca se persistía; hace falta
                // para resolver ConditionVariable::REPS_EN_TOPE_RANGO.
                'carga_efectiva_reps' => $agg['carga_efectiva_reps'],
                'volumen_total'      => $agg['volumen_total'],
                'blocked_by_pain'    => $blockedByPain,
            ]
        );

        if ($blockedByPain) {
            // "no calcular tendencias de progresión para ese ejercicio en
            // esa sesión" (documento §1.3) — se detiene aquí a propósito,
            // is_outlier/sin_dato_suficiente/tendencias quedan en su
            // default (false/null/0).
            return;
        }

        // Ítem 45 (Ronda 16): $programDayAssignmentId ya resuelto arriba
        // para el bloqueo por dolor -- se reutiliza, sin sesión programada
        // (workout suelto) el concepto de "semana de descarga" no aplica.
        $isDeloadWeek = $programDayAssignmentId
            ? (bool) ProgramDayAssignment::find($programDayAssignmentId)?->is_deload
            : false;
        $isOutlier = $this->detectOutliers($clientId, $exerciseId, $agg['carga_efectiva'], $metrics->id, $isDeloadWeek);
        $sinDatoSuficiente = $this->checkDataSufficiency($agg['sets']);

        $metrics->is_outlier = $isOutlier;
        $metrics->sin_dato_suficiente = $sinDatoSuficiente;
        $metrics->save();

        $this->updateTrendMetrics($clientId, $exerciseId, $metrics, $agg);
    }

    /**
     * aggregateSetLogs — documento §1.2, adaptado al esquema real: cada
     * ClientExerciseLog ya es "una sesión" para ese ejercicio (comentario
     * de la migración client_exercise_logs: "Cada fila = una sesión"), así
     * que no hay que sumar sets de varias filas — se parsea directamente
     * logged_sets de la fila más reciente para este ejercicio+sesión.
     *
     * "prescrito" no vive en logged_sets sino en
     * workout_template_exercise.prescribed (+ override), como un único
     * descriptor (series/reps/rir/carga objetivo) aplicado a todos los
     * sets del ejercicio, no una prescripción por set individual.
     */
    public function aggregateSetLogs(ClientExerciseLog $log): array
    {
        $sets = is_array($log->logged_sets) ? $log->logged_sets : [];
        $prescribed = $this->resolvePrescribed($log);

        $prescribedSeries = isset($prescribed['series']) && is_numeric($prescribed['series'])
            ? (int) $prescribed['series']
            : (count($sets) ?: null);
        $prescribedReps = isset($prescribed['reps']) && is_numeric($prescribed['reps']) ? (int) $prescribed['reps'] : null;
        $prescribedRir = isset($prescribed['rir']) && is_numeric($prescribed['rir']) ? (float) $prescribed['rir'] : null;

        $rirDeltas = [];
        $rirNullCount = 0;
        $completedCount = 0;
        $peorSerieIndex = null;
        $peorSerieRir = null;
        $cargaEfectiva = null;
        $cargaEfectivaReps = null;
        // Ítem 22 (Plan de Optimización, Ronda 8, docs/Motor_Autorregulacion_Analisis.md):
        // tonelaje real (peso × reps de cada set completado, sumado) — misma
        // fórmula ya usada y probada en MuscleVolumeService::computeVolume()
        // y ClientExerciseLogObserver::created(), llevada aquí como fuente
        // central para Fase 2 en adelante. A diferencia de carga_efectiva
        // (pico de UN set), esto es el trabajo total de la sesión.
        $volumenTotal = 0.0;
        // Ítem 39 (Plan de Optimización, Ronda 13): RIR delta de la
        // PRIMERA serie completada CON rir reportado (orden cronológico de
        // logged_sets) -- distinto del promedio de toda la sesión, soporta
        // razonar sobre la serie top por separado de las de backoff.
        $rirDeltaSerieTop = null;

        foreach ($sets as $index => $set) {
            $set = is_array($set) ? $set : [];

            $weight = isset($set['carga']) && $set['carga'] !== '' && $set['carga'] !== null ? (float) $set['carga'] : null;
            $reps = isset($set['reps']) && $set['reps'] !== '' && $set['reps'] !== null ? (int) $set['reps'] : null;
            $rir = isset($set['rir']) && $set['rir'] !== '' && $set['rir'] !== null ? (float) $set['rir'] : null;

            $isCompleted = $weight !== null && $weight > 0 && $reps !== null && $reps > 0;
            if ($isCompleted) {
                $completedCount++;
                $volumenTotal += $weight * $reps;
            }

            if ($rir === null) {
                $rirNullCount++;
            } else {
                // Un set con RIR nulo nunca debe romper el cálculo — se
                // excluye simplemente de la media (documento, criterio de
                // aceptación Fase 1).
                $delta = $prescribedRir !== null ? ($rir - $prescribedRir) : $rir;
                $rirDeltas[] = $delta;

                if ($isCompleted && $rirDeltaSerieTop === null) {
                    $rirDeltaSerieTop = $delta;
                }

                if ($peorSerieRir === null || $rir < $peorSerieRir) {
                    $peorSerieRir = $rir;
                    $peorSerieIndex = $index;
                }
            }

            if ($isCompleted && ($prescribedReps === null || $reps >= $prescribedReps)) {
                if ($cargaEfectiva === null || $weight > $cargaEfectiva) {
                    $cargaEfectiva = $weight;
                    $cargaEfectivaReps = $reps;
                }
            }
        }

        $rirDeltaSesion = count($rirDeltas) > 0 ? round(array_sum($rirDeltas) / count($rirDeltas), 3) : null;
        $rirDeltaSerieTop = $rirDeltaSerieTop !== null ? round($rirDeltaSerieTop, 3) : null;
        $completionRatio = $prescribedSeries && $prescribedSeries > 0
            ? round($completedCount / $prescribedSeries, 2)
            : null;
        // null (no 0.0) cuando no hubo NINGÚN set completado -- mismo
        // criterio que carga_efectiva: "sin dato" se distingue de "trabajo
        // real de cero", para que un futuro volumen_delta no confunda una
        // sesión sin datos con una sesión de volumen 0 real.
        $volumenTotal = $completedCount > 0 ? round($volumenTotal, 2) : null;

        return [
            'sets'               => $sets,
            'rir_null_count'     => $rirNullCount,
            'total_sets'         => count($sets),
            'rir_delta_sesion'   => $rirDeltaSesion,
            'rir_delta_serie_top' => $rirDeltaSerieTop,
            'completion_ratio'   => $completionRatio,
            'peor_serie_index'   => $peorSerieIndex,
            'peor_serie_rir'     => $peorSerieRir,
            'carga_efectiva'     => $cargaEfectiva,
            'volumen_total'      => $volumenTotal,
            'carga_efectiva_reps'=> $cargaEfectivaReps,
        ];
    }

    private function resolvePrescribed(ClientExerciseLog $log): array
    {
        if (!$log->workout_template_exercise_id) {
            return [];
        }

        $wte = WorkoutTemplateExercise::find($log->workout_template_exercise_id);
        if (!$wte) {
            return [];
        }

        $base = is_array($wte->prescribed) ? $wte->prescribed : [];

        if ($log->program_day_assignment_id) {
            $override = ClientExerciseOverride::where('program_day_assignment_id', $log->program_day_assignment_id)
                ->where('client_id', $log->client_id)
                ->where('workout_template_exercise_id', $wte->id)
                ->first();

            $overridePrescribed = is_array($override->prescribed_override ?? null) ? $override->prescribed_override : [];

            return array_merge($base, $overridePrescribed);
        }

        return $base;
    }

    /**
     * detectOutliers — documento §1.2: compara contra la media de las
     * últimas 5 sesiones VÁLIDAS (no outlier) del mismo ejercicio/cliente.
     * Si la desviación es >30%, se marca is_outlier=true y se excluye de
     * agregados de tendencia (no se borra el dato).
     */
    /**
     * Ítem 45 (Plan de Optimización, Ronda 16): `$isDeloadWeek` -- ver
     * ProgramDayAssignment.is_deload (migración
     * 2026_09_10_090000_add_is_deload_to_program_day_assignments_table.php),
     * marcado por el coach vía TrainingProgramController::markWeekDeload().
     * En semana de descarga planificada, una BAJADA de carga es la
     * intención, no una anomalía -- se suprime el outlier solo cuando
     * $cargaEfectiva < $mean (la desviación es hacia abajo). Una subida
     * inusual durante una semana de descarga (dato raro, pero posible: un
     * cliente que rompe la pauta) sigue marcándose igual que siempre, la
     * descarga no la explica.
     */
    public function detectOutliers(int $clientId, int $exerciseId, ?float $cargaEfectiva, int $excludeMetricId, bool $isDeloadWeek = false): bool
    {
        if ($cargaEfectiva === null) {
            return false;
        }

        $recent = ExerciseSessionMetric::where('client_id', $clientId)
            ->where('exercise_id', $exerciseId)
            ->where('id', '!=', $excludeMetricId)
            ->where('is_outlier', false)
            ->where('blocked_by_pain', false)
            ->whereNotNull('carga_efectiva')
            ->orderByDesc('created_at')
            ->limit(self::OUTLIER_WINDOW)
            ->pluck('carga_efectiva');

        if ($recent->isEmpty()) {
            // Sin histórico todavía — no hay contra qué comparar, no se
            // puede marcar outlier (evita falsos positivos en cold start).
            return false;
        }

        $mean = $recent->avg();
        if ($mean <= 0) {
            return false;
        }

        if ($isDeloadWeek && $cargaEfectiva < $mean) {
            return false;
        }

        $deviation = abs($cargaEfectiva - $mean) / $mean;

        return $deviation > self::OUTLIER_DEVIATION;
    }

    /**
     * checkDataSufficiency — documento §1.2: si >50% de los sets no tienen
     * rir_reported, marcar sin_dato_suficiente=true.
     */
    public function checkDataSufficiency(array $sets): bool
    {
        $total = count($sets);
        if ($total === 0) {
            return true;
        }

        $nullCount = 0;
        foreach ($sets as $set) {
            $set = is_array($set) ? $set : [];
            if (!isset($set['rir']) || $set['rir'] === '' || $set['rir'] === null) {
                $nullCount++;
            }
        }

        return ($nullCount / $total) > self::INSUFFICIENT_DATA_RATIO;
    }

    /**
     * updateTrendMetrics — documento §1.2, ventana N=3 sesiones válidas
     * (no outlier, no blocked_by_pain).
     */
    public function updateTrendMetrics(int $clientId, int $exerciseId, ExerciseSessionMetric $current, array $agg): void
    {
        $validRecent = ExerciseSessionMetric::where('client_id', $clientId)
            ->where('exercise_id', $exerciseId)
            ->where('is_outlier', false)
            ->where('blocked_by_pain', false)
            ->orderByDesc('created_at')
            ->limit(self::TREND_WINDOW)
            ->get(['id', 'rir_delta_sesion', 'carga_efectiva', 'volumen_total', 'created_at']);

        // Pendiente lineal simple de rir_delta_sesión en las N sesiones
        // (orden cronológico ascendente para que la pendiente tenga signo
        // correcto: positivo = empeorando, más RIR del esperado con el
        // tiempo).
        $chronological = $validRecent->reverse()->values();
        $tendenciaRir = $this->linearSlope(
            $chronological->pluck('rir_delta_sesion')->filter(fn ($v) => $v !== null)->values()->all()
        );

        // Ítem 22 (Plan de Optimización, Ronda 8): misma pendiente lineal,
        // ahora sobre volumen_total -- tonelaje real en vez de rir_delta.
        // Mismo criterio de signo (positivo = subiendo volumen con el
        // tiempo) y misma ventana (TREND_WINDOW sesiones válidas).
        $tendenciaVolumen = $this->linearSlope(
            $chronological->pluck('volumen_total')->filter(fn ($v) => $v !== null)->values()->all()
        );

        // Sesiones consecutivas con la misma carga_efectiva que la actual.
        $sesionesSinCambio = 0;
        if ($agg['carga_efectiva'] !== null) {
            foreach ($validRecent as $row) {
                if ($row->id === $current->id) {
                    continue;
                }
                if ($row->carga_efectiva !== null && abs($row->carga_efectiva - $agg['carga_efectiva']) < 0.001) {
                    $sesionesSinCambio++;
                } else {
                    break;
                }
            }
        }

        $e1rm = null;
        if ($agg['carga_efectiva'] !== null && $agg['carga_efectiva_reps'] !== null) {
            $e1rm = PersonalRecord::calculateEpley1RM((float) $agg['carga_efectiva'], (int) $agg['carga_efectiva_reps']);
        }

        $current->tendencia_rir = $tendenciaRir;
        $current->tendencia_volumen = $tendenciaVolumen;
        $current->sesiones_consecutivas_sin_cambio = $sesionesSinCambio;
        $current->e1rm_estimado = $e1rm;
        // racha_misma_dirección: depende de la "última acción del motor"
        // (next_session_targets), que no existe todavía — es de Fase 2.
        // Se deja en su default (0) a propósito.
        $current->save();
    }

    private function linearSlope(array $values): ?float
    {
        $n = count($values);
        if ($n < 2) {
            return null;
        }

        // Núcleo matemático compartido con SessionProgressionRuleEngine
        // (Plan de Optimización, Ronda 3 ítem 9) — la guarda de n<2 y el
        // redondeo a 4 decimales son propios de este servicio, sin cambio
        // de comportamiento.
        return round($this->computeRawLinearSlope($values), 4);
    }

    /**
     * Bloqueo por dolor (documento §1.3): existe pain_report para
     * exercise_id + esta sesión con tipo != molestia_leve OR intensidad
     * >= 4.
     */
    public function isBlockedByPain(int $clientId, int $exerciseId, ?int $programDayAssignmentId, ?int $workoutTemplateId, string $performedDate): bool
    {
        $query = PainReport::where('client_id', $clientId)->where('exercise_id', $exerciseId);

        if ($programDayAssignmentId) {
            $query->where('program_day_assignment_id', $programDayAssignmentId);
        } else {
            $query->whereNull('program_day_assignment_id')
                ->where('workout_template_id', $workoutTemplateId)
                ->whereDate('created_at', $performedDate);
        }

        return $query->get()->contains(fn (PainReport $report) => $report->blocksProgression());
    }
}
