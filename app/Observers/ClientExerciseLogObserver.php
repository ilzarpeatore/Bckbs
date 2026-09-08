<?php

namespace App\Observers;

use App\Enums\AchievementEventType;
use App\Models\AchievementEvent;
use App\Models\ClientExerciseLog;
use App\Models\Exercise;
use App\Models\ExerciseSessionMetric;
use App\Models\PersonalRecord;
use App\Models\TrainingQuestionnaireAnswer;
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

    // Ítem 34: umbral escalado por nivel de experiencia real
    // (TrainingQuestionnaireAnswer, Ronda 7) -- un novato genera más ruido
    // de aprendizaje motor (umbral más exigente), un avanzado cerca de su
    // techo genético merece que cualquier mejora real cuente (umbral más
    // permisivo). Sin dato de experiencia -> el umbral por defecto de
    // siempre, sin cambios.
    private const EXPERIENCE_NOVATO_MAX_MONTHS = 12;
    private const EXPERIENCE_AVANZADO_MIN_MONTHS = 60;
    private const IMPROVEMENT_PCT_NOVATO = 0.04;
    private const IMPROVEMENT_PCT_AVANZADO = 0.01;

    // Ítem 35: ventana de "mejor marca reciente" -- reconoce progreso real
    // durante una recuperación (lesión, parón) sin esperar a superar un
    // pico de hace años.
    private const RECENT_BEST_WINDOW_DAYS = 90;

    // Ítem 36: tolerancia para considerar que un cliente en déficit
    // "mantiene" fuerza (no la sube, pero tampoco la pierde de forma
    // relevante) + cooldown para no repetir el mismo logro cada sesión.
    private const DEFICIT_STRENGTH_MAINTAIN_TOLERANCE_PCT = 0.03;
    private const DEFICIT_ACHIEVEMENT_COOLDOWN_DAYS = 14;

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
        // Ítem 34: se calcula una sola vez por guardado, reutilizada tanto
        // para pr_carga como para max_volume (ítem 33).
        $threshold = $this->resolveImprovementThreshold($log->client_id);

        // Un solo aviso aunque se batan varios tipos de récord a la vez
        // (max_weight/max_1rm/max_volume) en la misma serie — evita 3
        // notificaciones seguidas por un único set. Comportamiento INTACTO,
        // solo se captura además el valor previo de cada tipo (necesario
        // para poblar previous_best en achievement_events).
        [$isNewWeightRecord, $previousWeight, $newWeightRecordId] = $this->storeIfRecord($log->client_id, $log->exercise_id, 'max_weight', $maxWeight, $achievedAt);
        [$isNewOneRmRecord, $previousOneRm] = $this->storeIfRecord($log->client_id, $log->exercise_id, 'max_1rm', $maxOneRm, $achievedAt);
        [$isNewVolumeRecord, ] = $this->storeVolumeRecord($log->client_id, $log->exercise_id, $totalVolume, $achievedAt, $threshold);

        if ($isNewWeightRecord || $isNewOneRmRecord || $isNewVolumeRecord) {
            $this->notifyNewRecord($log->client_id, $log->exercise_id);
        }

        $this->writeAchievementEvents($log, $maxWeight, $maxWeightReps, $isNewWeightRecord, $previousWeight, $isNewOneRmRecord, $previousOneRm, $maxOneRm, $threshold, $newWeightRecordId);
    }

    /**
     * Ítem 34 (Plan de Optimización, Ronda 12): umbral de "mejora
     * significativa" escalado por nivel de experiencia real (Ronda 7) --
     * usa la misma prioridad override-del-coach > autoevaluado > sin dato
     * que el resto del motor (TrainingQuestionnaireAnswer::effectiveExperienceMonths()).
     */
    private function resolveImprovementThreshold(int $clientId): float
    {
        $months = TrainingQuestionnaireAnswer::where('user_id', $clientId)->first()?->effectiveExperienceMonths();

        if ($months === null) {
            return self::PR_CARGA_MIN_IMPROVEMENT_PCT;
        }
        if ($months < self::EXPERIENCE_NOVATO_MAX_MONTHS) {
            return self::IMPROVEMENT_PCT_NOVATO;
        }
        if ($months >= self::EXPERIENCE_AVANZADO_MIN_MONTHS) {
            return self::IMPROVEMENT_PCT_AVANZADO;
        }

        return self::PR_CARGA_MIN_IMPROVEMENT_PCT;
    }

    /**
     * @return array{0: bool, 1: ?float} [se creó un récord nuevo, mejor valor previo (null si no había ninguno)]
     */
    /**
     * @return array{0: bool, 1: ?float, 2: ?int} [es récord nuevo, mejor valor previo, id de la fila creada (null si no se creó)]
     */
    private function storeIfRecord(int $userId, int $exerciseId, string $recordType, float $value, $achievedAt): array
    {
        if ($value <= 0) {
            return [false, null, null];
        }

        $previousBest = PersonalRecord::where('user_id', $userId)
            ->where('exercise_id', $exerciseId)
            ->where('record_type', $recordType)
            ->max('value');
        $previousBest = $previousBest !== null ? (float) $previousBest : null;

        if ($previousBest !== null && $value <= $previousBest) {
            return [false, $previousBest, null];
        }

        $record = PersonalRecord::create([
            'user_id'      => $userId,
            'exercise_id'  => $exerciseId,
            'record_type'  => $recordType,
            'value'        => round($value, 2),
            'achieved_at'  => $achievedAt,
        ]);

        return [true, $previousBest, $record->id];
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
     * Ítem 33 (Plan de Optimización, Ronda 12): el umbral de "mejora
     * significativa" (antes solo aplicado a pr_carga) también filtra aquí
     * -- un incremento de volumen frente a sesiones anteriores por debajo
     * del umbral no cuenta como récord nuevo (no notifica), igual que ya
     * pasaba con pr_carga. La actualización del acumulado DENTRO del mismo
     * día ($existingToday) no se toca -- eso es corregir el running total
     * de hoy, no una comparación cross-día.
     *
     * @return array{0: bool, 1: ?float} [se creó/mejoró un récord de volumen frente a sesiones anteriores, mejor valor previo]
     */
    private function storeVolumeRecord(int $userId, int $exerciseId, float $value, $achievedAt, float $threshold): array
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

        if (!$this->isSignificantImprovement($value, $previousBest, $threshold)) {
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
        float $maxOneRm,
        float $threshold,
        ?int $newWeightRecordId = null
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

        // Ítems 35-36 (Plan de Optimización, Ronda 12): cuando ESTE
        // guardado no llega a ser un PR_CARGA real (ni por no ser récord
        // all-time, ni por no superar el umbral de mejora significativa),
        // sigue habiendo dos logros distintos que evaluar antes de
        // descartarlo del todo.
        if ($isNewWeightRecord && $this->isSignificantImprovement($maxWeight, $previousWeight, $threshold)) {
            $this->recordAchievement($log, AchievementEventType::PR_CARGA, $maxWeight, $previousWeight, 'max_weight');
        } elseif ($maxWeight > 0) {
            $this->maybeRecordRecentBest($log, $maxWeight, 'max_weight', $newWeightRecordId);
            $this->maybeRecordDeficitMaintenance($log, $maxWeight, $previousWeight);
        }

        if ($isNewOneRmRecord) {
            $this->recordAchievement($log, AchievementEventType::MEJORA_E1RM, $maxOneRm, $previousOneRm, 'max_1rm');
        }

        $this->maybeRecordPrReps($log, $maxWeight, $maxWeightReps);
    }

    /**
     * Ítem 35 (Plan de Optimización, Ronda 12): "mejor marca reciente" de
     * los últimos RECENT_BEST_WINDOW_DAYS -- reconoce progreso real durante
     * una recuperación sin esperar a superar un pico de hace años. Si el
     * cliente lleva más de la ventana entera sin marcar nada de este tipo
     * (recuperación larga), cualquier valor real ya cuenta como "lo mejor
     * reciente" por defecto -- no hay nada DENTRO de la ventana con qué
     * compararlo, y ya se descartó que sea la primera vez absoluta
     * ($hasAnyHistory).
     *
     * $excludeRecordId: cuando este guardado NO fue un PR_CARGA real pero
     * SÍ superó el histórico all-time (isNewWeightRecord=true, por debajo
     * del umbral de mejora significativa), storeIfRecord() YA insertó la
     * fila de personal_records para este mismo valor antes de llegar aquí
     * -- sin excluirla, la comparación "recentBest" se compara consigo
     * misma (value <= recentBest siempre cierto) y nunca genera el logro.
     * En el resto de casos (récord no all-time, sin fila nueva) es null y
     * no cambia nada.
     */
    private function maybeRecordRecentBest(ClientExerciseLog $log, float $value, string $recordType, ?int $excludeRecordId = null): void
    {
        if ($value <= 0) {
            return;
        }

        $hasAnyHistory = PersonalRecord::where('user_id', $log->client_id)
            ->where('exercise_id', $log->exercise_id)
            ->where('record_type', $recordType)
            ->when($excludeRecordId, fn ($q) => $q->where('id', '!=', $excludeRecordId))
            ->exists();
        if (!$hasAnyHistory) {
            return; // primer registro real -- ya lo cubre storeIfRecord/PR_CARGA.
        }

        $recentBest = PersonalRecord::where('user_id', $log->client_id)
            ->where('exercise_id', $log->exercise_id)
            ->where('record_type', $recordType)
            ->where('achieved_at', '>=', now()->subDays(self::RECENT_BEST_WINDOW_DAYS))
            ->when($excludeRecordId, fn ($q) => $q->where('id', '!=', $excludeRecordId))
            ->max('value');
        $recentBest = $recentBest !== null ? (float) $recentBest : null;

        if ($recentBest !== null && $value <= $recentBest) {
            return;
        }

        $personalRecord = PersonalRecord::where('user_id', $log->client_id)
            ->where('exercise_id', $log->exercise_id)
            ->where('record_type', $recordType)
            ->orderByDesc('id')
            ->first();

        AchievementEvent::create([
            'client_id'                 => $log->client_id,
            'type'                       => AchievementEventType::MEJOR_MARCA_RECIENTE->value,
            'exercise_id'                => $log->exercise_id,
            'value'                      => round($value, 2),
            'previous_best'              => $recentBest,
            'significancia_verificada'   => true,
            'source_type'                => PersonalRecord::class,
            'source_id'                  => $personalRecord?->id,
        ]);
    }

    /**
     * Ítem 36 (Plan de Optimización, Ronda 12): un cliente en fase de
     * pérdida de grasa/recomposición (`TrainingQuestionnaireAnswer.goal_type`)
     * que MANTIENE su fuerza (dentro de una tolerancia pequeña del mejor
     * histórico, sin llegar a superarlo -- eso ya es un PR real, cubierto
     * aparte) está teniendo un resultado excelente dado su contexto. Sin
     * `goal_type` disponible, o sin referencia de fuerza previa, no hay
     * nada que evaluar. Cooldown de DEFICIT_ACHIEVEMENT_COOLDOWN_DAYS para
     * no repetir el mismo logro cada sesión mientras el cliente siga en la
     * misma fase.
     */
    private function maybeRecordDeficitMaintenance(ClientExerciseLog $log, float $maxWeight, ?float $previousBest): void
    {
        if ($maxWeight <= 0 || $previousBest === null || $previousBest <= 0) {
            return;
        }

        $goalType = TrainingQuestionnaireAnswer::where('user_id', $log->client_id)->value('goal_type');
        if (!in_array($goalType, ['lose_fat', 'recomposition'], true)) {
            return;
        }

        $ratio = $maxWeight / $previousBest;
        if ($ratio > 1.0 || $ratio < (1 - self::DEFICIT_STRENGTH_MAINTAIN_TOLERANCE_PCT)) {
            return; // superó el histórico (ya es PR_CARGA) o bajó más de lo tolerado (no es "mantener").
        }

        $alreadyRecorded = AchievementEvent::where('client_id', $log->client_id)
            ->where('exercise_id', $log->exercise_id)
            ->where('type', AchievementEventType::MANTIENE_FUERZA_EN_DEFICIT->value)
            ->where('created_at', '>=', now()->subDays(self::DEFICIT_ACHIEVEMENT_COOLDOWN_DAYS))
            ->exists();
        if ($alreadyRecorded) {
            return;
        }

        AchievementEvent::create([
            'client_id'                 => $log->client_id,
            'type'                       => AchievementEventType::MANTIENE_FUERZA_EN_DEFICIT->value,
            'exercise_id'                => $log->exercise_id,
            'value'                      => round($maxWeight, 2),
            'previous_best'              => $previousBest,
            'significancia_verificada'   => true,
            'source_type'                => ClientExerciseLog::class,
            'source_id'                  => $log->id,
        ]);
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

    private function isSignificantImprovement(float $value, ?float $previous, float $thresholdPct): bool
    {
        if ($previous === null || $previous <= 0) {
            return true; // primer récord real: siempre cuenta.
        }

        return (($value - $previous) / $previous) >= $thresholdPct;
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
     *
     * OPTIMIZACIÓN (Plan de Optimización, Ronda 1 ítem 4, ver
     * docs/Motor_Autorregulacion_Analisis.md): esto corre en el hilo
     * síncrono de CADA guardado de series (logSets(), más frecuente que
     * finishSession()). Antes se traían los `logged_sets` COMPLETOS de
     * hasta 200 ClientExerciseLog y se recorría cada set de cada log en
     * PHP para encontrar coincidencias exactas de `carga`. `logged_sets`
     * es JSON (ver migración client_exercise_logs) -- se empuja el
     * filtro "¿algún set de este log tiene carga == peso exacto?" a MySQL
     * con JSON_CONTAINS(logged_sets, JSON_OBJECT('carga', ?)): para un
     * array JSON de objetos, JSON_CONTAINS(array, candidato) es true si
     * ALGÚN elemento del array contiene ese candidato como subconjunto de
     * claves -- exactamente "¿hay algún set con ese peso?" sin tener que
     * decodificar/recorrer el JSON en PHP para los logs que no lo tienen
     * (la inmensa mayoría de los 200, en la práctica: el cliente progresa
     * de peso con el tiempo). Solo se decodifica logged_sets en PHP para
     * los logs que ya pasaron ese filtro, y el cálculo fino (tolerancia
     * ±0.01, exigir reps>0, máximo de reps) se mantiene IDÉNTICO en PHP.
     * Ventana idéntica a la anterior: los mismos 200 logs más recientes
     * (subconsulta con el mismo orderByDesc('id')->limit(...) de antes),
     * solo cambia CUÁLES de esos 200 se traen completos a PHP.
     *
     * Verificado contra producción (2026-09-07, MySQL 8.0.46): `carga` está
     * guardado unas veces como número JSON y otras como STRING JSON (el
     * endpoint de logSets en ClientCalendarController no valida
     * `logged_sets.*.carga` como numeric, solo filtra claves permitidas;
     * en una muestra de 500 logs reales, 582 valores eran string y 146
     * numéricos). JSON_CONTAINS compara por tipo -- y el bind de PDO para
     * un `float` de PHP se envía como JSON STRING (comprobado: `JSON_TYPE`
     * de `JSON_OBJECT('carga', ?)` con un float bindeado da STRING, no
     * DOUBLE/INTEGER). Eso significa que un solo bind de `$weight` (float)
     * YA matcheaba el caso string, pero NUNCA matcheaba `carga` guardado
     * como número puro (comprobado contra un log real con carga entera:
     * 0 matches). Por eso el filtro de abajo prueba dos representaciones:
     * `CAST(? AS DECIMAL(10,2))` para forzar tipo numérico JSON real, y el
     * bind tal cual para el caso string.
     */
    private function maybeRecordPrReps(ClientExerciseLog $log, float $weight, int $reps): void
    {
        if ($weight <= 0 || $reps <= 0) {
            return;
        }

        $candidateLogs = ClientExerciseLog::whereIn('id', function ($query) use ($log) {
                $query->select('id')
                    ->from('client_exercise_logs')
                    ->where('client_id', $log->client_id)
                    ->where('exercise_id', $log->exercise_id)
                    ->where('id', '!=', $log->id)
                    ->orderByDesc('id')
                    ->limit(self::PR_REPS_HISTORY_LIMIT);
            })
            ->where(function ($query) use ($weight) {
                $query->whereRaw("JSON_CONTAINS(logged_sets, JSON_OBJECT('carga', CAST(? AS DECIMAL(10,2))))", [$weight])
                    ->orWhereRaw("JSON_CONTAINS(logged_sets, JSON_OBJECT('carga', ?))", [(string) $weight]);
            })
            ->get(['logged_sets']);

        $historicalMaxReps = null;

        $candidateLogs->each(function (ClientExerciseLog $previousLog) use ($weight, &$historicalMaxReps) {
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
