<?php

namespace App\Observers;

use App\Enums\AchievementEventType;
use App\Models\AchievementEvent;
use App\Models\ClientExerciseLog;
use App\Models\Exercise;
use App\Models\ExerciseSessionMetric;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\WorkoutSessionReview;
use App\Notifications\CommonNotification;
use Illuminate\Support\Facades\Gate;

/**
 * Listener documentado desde el principio en la migracion de
 * personal_records ("se rellena mediante un listener disparado al
 * completar una sesion") pero nunca implementado - la tabla estaba
 * siempre vacia. Se dispara en cada guardado de series (logSets ya
 * envia el estado acumulado de series completadas de ese ejercicio,
 * no solo la ultima), y solo crea un registro nuevo si supera el
 * record anterior de ese mismo tipo.
 *
 * Fase 3 (Motor de Auto-Regulación de Carga, documento §3.2, tarea #20):
 * ADEMÁS de lo anterior (personal_records + notificación, que NO se toca
 * ni se gatea, sigue igual para todos los clientes), si el cliente es
 * paid-tier se escribe también una fila en achievement_events (capa de
 * feed/historial nueva) cuando el récord de max_weight o max_1rm mejora, y
 * se evalúa la lógica nueva de pr_reps (no trackeada hasta ahora).
 */
class ClientExerciseLogObserver
{
    // documento §3.2: "no si la diferencia es <2.5% para evitar ruido de
    // redondeo" — umbral que filtra qué PR de carga llega al feed nuevo de
    // achievement_events; personal_records en sí sigue sin umbral, igual
    // que siempre, para todos los clientes.
    private const PR_CARGA_MIN_IMPROVEMENT_PCT = 0.025;

    // Ventana de sesiones históricas recientes revisadas para pr_reps —
    // evita cargar el historial completo de un cliente con años de datos
    // para un cálculo que solo necesita "recientemente, a este peso".
    private const PR_REPS_HISTORY_LIMIT = 200;

    public function created(ClientExerciseLog $log): void
    {
        $sets = $log->logged_sets ?? [];
        if (empty($sets)) {
            return;
        }

        $maxWeight = 0.0;
        $maxWeightReps = 0;
        $maxOneRm = 0.0;
        $totalVolume = 0.0;

        foreach ($sets as $set) {
            $weight = (float) ($set['carga'] ?? 0);
            $reps = (int) ($set['reps'] ?? 0);
            if ($weight <= 0 || $reps <= 0) {
                continue;
            }

            $totalVolume += $weight * $reps;
            if ($weight > $maxWeight) {
                $maxWeight = $weight;
                $maxWeightReps = $reps;
            } elseif ($weight == $maxWeight && $reps > $maxWeightReps) {
                $maxWeightReps = $reps;
            }
            $maxOneRm = max($maxOneRm, PersonalRecord::calculateEpley1RM($weight, $reps));
        }

        if ($maxWeight <= 0 && $maxOneRm <= 0 && $totalVolume <= 0) {
            return;
        }

        $achievedAt = $log->performed_date ?? now();

        // Un solo aviso aunque se batan varios tipos de récord a la vez
        // (max_weight/max_1rm/max_volume) en la misma serie — evita 3
        // notificaciones seguidas por un único set. Comportamiento INTACTO,
        // solo se captura además el valor previo de cada tipo (necesario
        // para poblar previous_best en achievement_events).
        [$isNewWeightRecord, $previousWeight] = $this->storeIfRecord($log->client_id, $log->exercise_id, 'max_weight', $maxWeight, $achievedAt);
        [$isNewOneRmRecord, $previousOneRm] = $this->storeIfRecord($log->client_id, $log->exercise_id, 'max_1rm', $maxOneRm, $achievedAt);
        [$isNewVolumeRecord, ] = $this->storeVolumeRecord($log->client_id, $log->exercise_id, $totalVolume, $achievedAt);

        if ($isNewWeightRecord || $isNewOneRmRecord || $isNewVolumeRecord) {
            $this->notifyNewRecord($log->client_id, $log->exercise_id);
        }

        $this->writeAchievementEvents($log, $maxWeight, $maxWeightReps, $isNewWeightRecord, $previousWeight, $isNewOneRmRecord, $previousOneRm, $maxOneRm);
    }

    /**
     * @return array{0: bool, 1: ?float} [se creó un récord nuevo, mejor valor previo (null si no había ninguno)]
     */
    private function storeIfRecord(int $userId, int $exerciseId, string $recordType, float $value, $achievedAt): array
    {
        if ($value <= 0) {
            return [false, null];
        }

        $previousBest = PersonalRecord::where('user_id', $userId)
            ->where('exercise_id', $exerciseId)
            ->where('record_type', $recordType)
            ->max('value');
        $previousBest = $previousBest !== null ? (float) $previousBest : null;

        if ($previousBest !== null && $value <= $previousBest) {
            return [false, $previousBest];
        }

        PersonalRecord::create([
            'user_id'      => $userId,
            'exercise_id'  => $exerciseId,
            'record_type'  => $recordType,
            'value'        => round($value, 2),
            'achieved_at'  => $achievedAt,
        ]);

        return [true, $previousBest];
    }

    /**
     * FIX 2026-08-12 (bug real reportado por el usuario, "siempre marca PR
     * en todos los ejercicios"): a diferencia de max_weight/max_1rm (cada
     * serie con más peso que nunca ES un récord real, aunque sea la 3ª
     * serie de la misma sesión — es una mejora genuina), el volumen total
     * de una sesión SOLO PUEDE CRECER a medida que se guardan más series,
     * porque logSets() manda el acumulado completo de la sesión en cada
     * guardado (ver docblock de created()). Comparar cada guardado parcial
     * contra el máximo histórico disparaba un "récord nuevo" (con su
     * notificación) varias veces en la misma sesión sin que el cliente
     * hiciera nada distinto entre un guardado y el siguiente — confirmado
     * en datos reales de producción (ejercicio 709 de un cliente real: 2
     * PRs de volumen, 925→1175, a los pocos minutos de diferencia, misma
     * sesión).
     *
     * Fix: se consolida en una sola fila de personal_records por sesión
     * (mismo client+exercise+record_type+día de achieved_at) — se
     * actualiza en vez de crear una nueva en los guardados siguientes de
     * esa sesión, y solo se cuenta/notifica como "récord nuevo" la primera
     * vez dentro de esa sesión que el acumulado supera el histórico de
     * sesiones ANTERIORES (nunca compara contra sí misma). El récord de
     * volumen real contra el histórico se sigue detectando igual, solo
     * deja de repetirse dentro de la misma sesión.
     *
     * @return array{0: bool, 1: ?float} [se creó/mejoró un récord de volumen frente a sesiones anteriores, mejor valor previo]
     */
    private function storeVolumeRecord(int $userId, int $exerciseId, float $value, $achievedAt): array
    {
        if ($value <= 0) {
            return [false, null];
        }

        $achievedDate = \Carbon\Carbon::parse($achievedAt)->toDateString();

        $existingToday = PersonalRecord::where('user_id', $userId)
            ->where('exercise_id', $exerciseId)
            ->where('record_type', 'max_volume')
            ->whereDate('achieved_at', $achievedDate)
            ->first();

        $previousBest = PersonalRecord::where('user_id', $userId)
            ->where('exercise_id', $exerciseId)
            ->where('record_type', 'max_volume')
            ->when($existingToday, fn ($q) => $q->where('id', '!=', $existingToday->id))
            ->max('value');
        $previousBest = $previousBest !== null ? (float) $previousBest : null;

        if ($existingToday) {
            if ($value > (float) $existingToday->value) {
                $existingToday->update(['value' => round($value, 2)]);
            }

            // Ya se contó/notificó como récord (si aplicaba) en un guardado
            // anterior de esta misma sesión -- no se repite el aviso.
            return [false, $previousBest];
        }

        if ($previousBest !== null && $value <= $previousBest) {
            return [false, $previousBest];
        }

        PersonalRecord::create([
            'user_id'     => $userId,
            'exercise_id' => $exerciseId,
            'record_type' => 'max_volume',
            'value'       => round($value, 2),
            'achieved_at' => $achievedAt,
        ]);

        return [true, $previousBest];
    }

    private function notifyNewRecord(int $userId, int $exerciseId): void
    {
        $user = User::find($userId);
        $exercise = Exercise::find($exerciseId);
        if (!$user || !$exercise) {
            return;
        }
        $user->notify(new CommonNotification('personal_record', [
            'id'      => $exerciseId,
            'type'    => 'personal_record',
            'subject' => 'Nuevo récord personal',
            'message' => "¡Nuevo récord en \"{$exercise->title}\"! Sigue así.",
        ]));
    }

    // ═══ Fase 3 — feed de logros (achievement_events) ═══════════════════

    private function writeAchievementEvents(
        ClientExerciseLog $log,
        float $maxWeight,
        int $maxWeightReps,
        bool $isNewWeightRecord,
        ?float $previousWeight,
        bool $isNewOneRmRecord,
        ?float $previousOneRm,
        float $maxOneRm
    ): void {
        $client = User::find($log->client_id);
        if (!$client || !Gate::forUser($client)->allows('paid-tier')) {
            return; // free tier: personal_records/notificación ya se generaron igual arriba, sin cambios.
        }

        if ($this->isBlockedByOutlier($log)) {
            // Verificación de la tarea: un PR real sobre una sesión
            // marcada is_outlier en exercise_session_metrics no debe
            // generar achievement_event — personal_records SÍ se crea
            // igual arriba (sin tocar), esto solo bloquea el feed nuevo.
            return;
        }

        if ($isNewWeightRecord && $this->isSignificantImprovement($maxWeight, $previousWeight)) {
            $this->recordAchievement($log, AchievementEventType::PR_CARGA, $maxWeight, $previousWeight, 'max_weight');
        }

        if ($isNewOneRmRecord) {
            $this->recordAchievement($log, AchievementEventType::MEJORA_E1RM, $maxOneRm, $previousOneRm, 'max_1rm');
        }

        $this->maybeRecordPrReps($log, $maxWeight, $maxWeightReps);
    }

    /**
     * Decisión de diseño propia (misma lógica de resolución que
     * SessionInterpretationService usa para identificar "la sesión" de un
     * ClientExerciseLog): por el orden real de ejecución del backend
     * (logSets() -> este observer se dispara DURANTE la sesión, ANTES de
     * finishSession()), exercise_session_metrics normalmente NO existe
     * todavía para esta sesión concreta en el momento en que este observer
     * corre — se calcula después, síncronamente pero más tarde, dentro de
     * finishSession() (ProcessSessionInterpretation). Por eso: si YA existe
     * una fila de métricas para esta sesión+ejercicio (caso real: log
     * insertado/editado retroactivamente después del cierre, o los datos
     * de prueba de esta verificación), se respeta su is_outlier; si NO
     * existe todavía (caso normal en producción, en tiempo real), no se
     * bloquea — mismo criterio ya usado en el resto del motor: dato no
     * disponible = no bloqueante, nunca se asume outlier por defecto.
     *
     * FIX 2026-08-12 (bug real encontrado investigando el reporte del
     * usuario sobre logros que nunca aparecen): `program_day_assignment_id`
     * identifica una POSICIÓN dentro de la plantilla del programa (semana+
     * día), no una sesión concreta — si el cliente pasa por esa misma
     * posición más de una vez (programa repetido/re-asignado, o un
     * backfill que generó una fila de métrica para una sesión antigua de
     * esa posición), la consulta sin acotar por fecha podía devolver
     * CUALQUIER review histórico de esa posición, no el de la sesión que
     * disparó este log — bloqueando el feed de logros PARA SIEMPRE en esa
     * posición del programa por culpa de una sola sesión antigua/outlier,
     * aunque el cliente entrenara perfecto después. Confirmado en
     * producción: la métrica outlier que bloqueaba a un cliente real se
     * había creado 3 días después de la sesión real, por el job de
     * backfill. Fix: acotar también por la fecha real de la sesión
     * (`performed_date` del log, comparado contra `completed_at` del
     * review) — si no hay un review de ESE día concreto, no bloquea.
     */
    private function isBlockedByOutlier(ClientExerciseLog $log): bool
    {
        if (!$log->program_day_assignment_id) {
            return false; // workout suelto: sin forma fiable de resolver la sesión antes de finishSession().
        }

        $review = WorkoutSessionReview::where('user_id', $log->client_id)
            ->where('program_day_assignment_id', $log->program_day_assignment_id)
            ->whereDate('completed_at', $log->performed_date ?? now()->toDateString())
            ->first();

        if (!$review) {
            return false;
        }

        $metrics = ExerciseSessionMetric::where('workout_session_review_id', $review->id)
            ->where('exercise_id', $log->exercise_id)
            ->first();

        return (bool) ($metrics->is_outlier ?? false);
    }

    private function isSignificantImprovement(float $value, ?float $previous): bool
    {
        if ($previous === null || $previous <= 0) {
            return true; // primer récord real: siempre cuenta.
        }

        return (($value - $previous) / $previous) >= self::PR_CARGA_MIN_IMPROVEMENT_PCT;
    }

    private function recordAchievement(ClientExerciseLog $log, AchievementEventType $type, float $value, ?float $previousBest, string $recordType): void
    {
        $personalRecord = PersonalRecord::where('user_id', $log->client_id)
            ->where('exercise_id', $log->exercise_id)
            ->where('record_type', $recordType)
            ->orderByDesc('id')
            ->first();

        AchievementEvent::create([
            'client_id'                 => $log->client_id,
            'type'                       => $type->value,
            'exercise_id'                => $log->exercise_id,
            'value'                      => round($value, 2),
            'previous_best'              => $previousBest,
            'significancia_verificada'   => true,
            'source_type'                => PersonalRecord::class,
            'source_id'                  => $personalRecord?->id,
        ]);
    }

    /**
     * pr_reps (documento §3.2/tarea #20): "mejores reps a una carga dada"
     * — tipo nuevo, no trackeado en personal_records (su esquema no tiene
     * concepto de "récord a una carga concreta", solo un máximo absoluto
     * por tipo). Decisión de diseño propia: se evalúa solo sobre el peso
     * MÁS ALTO levantado en esta sesión ($maxWeight/$maxWeightReps, ya
     * calculados en created()) contra el máximo histórico de reps a ESE
     * mismo peso exacto en sesiones anteriores de este cliente+ejercicio
     * — evita generar ruido comparando cada serie suelta contra todo el
     * historial. Solo cuenta como logro si existe histórico previo a ese
     * peso exacto (si es la primera vez que se usa ese peso, no hay
     * "mejora" real que mostrar todavía).
     */
    private function maybeRecordPrReps(ClientExerciseLog $log, float $weight, int $reps): void
    {
        if ($weight <= 0 || $reps <= 0) {
            return;
        }

        $historicalMaxReps = null;

        ClientExerciseLog::where('client_id', $log->client_id)
            ->where('exercise_id', $log->exercise_id)
            ->where('id', '!=', $log->id)
            ->orderByDesc('id')
            ->limit(self::PR_REPS_HISTORY_LIMIT)
            ->get(['logged_sets'])
            ->each(function (ClientExerciseLog $previousLog) use ($weight, &$historicalMaxReps) {
                foreach (($previousLog->logged_sets ?? []) as $set) {
                    $setWeight = isset($set['carga']) && is_numeric($set['carga']) ? (float) $set['carga'] : null;
                    $setReps = isset($set['reps']) && is_numeric($set['reps']) ? (int) $set['reps'] : null;
                    if ($setWeight === null || $setReps === null || $setReps <= 0) {
                        continue;
                    }
                    if (abs($setWeight - $weight) < 0.01) {
                        $historicalMaxReps = $historicalMaxReps === null ? $setReps : max($historicalMaxReps, $setReps);
                    }
                }
            });

        if ($historicalMaxReps === null || $reps <= $historicalMaxReps) {
            return;
        }

        AchievementEvent::create([
            'client_id'                 => $log->client_id,
            'type'                       => AchievementEventType::PR_REPS->value,
            'exercise_id'                => $log->exercise_id,
            'value'                      => $reps,
            'previous_best'              => $historicalMaxReps,
            'significancia_verificada'   => true,
            'source_type'                => ClientExerciseLog::class,
            'source_id'                  => $log->id,
        ]);
    }
}
