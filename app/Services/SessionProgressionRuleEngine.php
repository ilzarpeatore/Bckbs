<?php

namespace App\Services;

use App\Enums\ActionType;
use App\Enums\BaseReference;
use App\Enums\ConditionOperator;
use App\Enums\ConditionVariable;
use App\Enums\ExceptionCategory;
use App\Enums\ExceptionSeverity;
use App\Enums\ScopeType;
use App\Enums\TargetStatus;
use App\Models\ClientExerciseCalibration;
use App\Models\ClientExerciseOverride;
use App\Models\Exercise;
use App\Models\ExerciseSessionMetric;
use App\Models\ExerciseSubstitution;
use App\Models\NextSessionTarget;
use App\Models\PainReport;
use App\Models\ProgramDayAssignment;
use App\Models\ReadinessScore;
use App\Models\SessionProgressionRule;
use App\Models\ShadowEvaluation;
use App\Models\TrainingQuestionnaireAnswer;
use App\Models\User;
use App\Models\WorkoutSessionReview;
use App\Models\WorkoutTemplateExercise;
use App\Services\Concerns\ComputesLinearSlope;
use App\Services\CoachExceptionFeedService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Motor de Auto-Regulación de Carga — Fase 2 (documento §2.2).
 *
 * Consume SOLO exercise_session_metrics (Fase 1) — nunca
 * client_exercise_logs directamente (regla explícita del documento). Las
 * únicas otras tablas que este servicio lee son las de PRESCRIPCIÓN
 * (workout_template_exercises / client_exercise_overrides /
 * program_day_assignments), necesarias para resolver `base_reference` y
 * para aplicar el resultado en modo automático — son la fuente real de
 * "lo prescrito", nunca el registro crudo de lo ejecutado.
 */
class SessionProgressionRuleEngine
{
    use ComputesLinearSlope;

    // documento §2.4: mínimo global por defecto si una regla no lo sobreescribe.
    private const DEFAULT_MIN_CALIBRATION_SESSIONS = ClientExerciseCalibration::DEFAULT_MIN_SESSIONS;

    /**
     * Plan de Optimización, Ronda 1 ítems 1-2 (docs/Motor_Autorregulacion_Analisis.md):
     * cachés de instancia, vigentes durante la vida de ESTE objeto engine.
     * El engine no está registrado como singleton en el contenedor (sin
     * binding explícito, confirmado) -- se resuelve una vez por
     * job/request y esa misma instancia se reutiliza en el loop de
     * EvaluateSessionProgressionRules::handle() (una llamada a
     * evaluateForExercise() por ejercicio de la sesión), que es
     * exactamente el caso que se quiere optimizar. Si en el futuro el
     * engine pasara a ser singleton (p. ej. bajo Octane) estas cachés
     * necesitarían invalidación explícita entre ejecuciones -- fuera de
     * alcance de esta tarea.
     *
     * $applicableRulesCache: ítem 1, keyed por "{client_id}:{exercise_id}:{training_program_id}".
     * Se incluye client_id además de coach_id/exercise_id/training_program_id
     * (más específico que lo pedido literalmente) porque el propio
     * resultado de applicableRules() depende del cliente vía el scope
     * `cliente_especifico` (scope_id = client->id) -- cachear solo por
     * coach_id mezclaría resultados de reglas cliente-específicas entre
     * clientes distintos del mismo coach si esta instancia llegara a
     * evaluar más de un cliente. coach_id es además derivable de
     * client_id (client->coach_id), así que no se pierde granularidad.
     */
    private array $applicableRulesCache = [];

    /**
     * $evaluationCache: ítem 2, memoiza readiness_score (por
     * client_id+fecha) y e1rm_delta (por exercise_session_metric->id)
     * dentro del scope de una sola evaluación. Se resetea explícitamente
     * al empezar cada evaluateForExercise()/simulateRule() -- ver
     * resetEvaluationCache() -- para no arrastrar valores de una
     * evaluación a la siguiente dentro de la misma instancia (a
     * diferencia de $applicableRulesCache, que sí conviene mantener
     * entre ejercicios de la misma sesión/job).
     */
    private array $evaluationCache = [];

    /**
     * Plan de Optimización, Ronda 15 ítem 44: nº de bajar_carga_pct/
     * marcar_para_coach ya disparados (aplicados de verdad, ni simulados ni
     * en shadow_mode) en ESTA ejecución del job -- a diferencia de
     * $evaluationCache, NO se resetea en resetEvaluationCache() porque debe
     * persistir entre los evaluateForExercise() de distintos ejercicios de
     * la misma sesión (mismo criterio que $applicableRulesCache: vive tanto
     * como la instancia, y la instancia vive tanto como el job,
     * EvaluateSessionProgressionRules::handle() resuelve una instancia
     * nueva por ejecución). Ver resolveAccionesBajadaEnSesion().
     */
    private int $accionesBajadaEnSesion = 0;

    // Fase 3 (documento §3.1): ventana de sesiones recientes usada para
    // validar la lógica de estancamiento antes de disparar una sustitución
    // — mismo tamaño que OUTLIER_WINDOW en SessionInterpretationService,
    // reutilizado como criterio razonable de "sesiones recientes" para no
    // introducir un tercer número mágico distinto sin motivo.
    private const STAGNATION_WINDOW = 5;
    // >50% de las sesiones de la ventana con readiness_band=bajo -> no sustituir (documento §3.1).
    private const READINESS_LOW_MAJORITY_RATIO = 0.5;
    // Ítem 42 (Plan de Optimización, Ronda 13): ventana de "reciente" para
    // ConditionVariable::DOLOR_RECIENTE_NO_BLOQUEANTE.
    private const DOLOR_RECIENTE_WINDOW_DAYS = 14;

    /**
     * Punto de entrada único (documento §2.2). $sessionId =
     * workout_session_reviews.id (mismo identificador de "sesión" real que
     * usa Fase 1, ver SessionInterpretationService).
     *
     * @param  bool  $simulate  true = modo simulación (documento §2.6): nunca escribe en next_session_targets/shadow_evaluations.
     */
    public function evaluateForExercise(int $clientId, int $exerciseId, int $sessionId, bool $simulate = false): array
    {
        // Ítem 2: memoización de readiness_score/e1rm_delta acotada a ESTA
        // evaluación -- ver $evaluationCache arriba.
        $this->resetEvaluationCache();

        $client = User::find($clientId);
        if (!$client) {
            return $this->result('sin_cliente', null, null, null, null, null);
        }

        // Gate paid-tier (documento §0.3 y plan §Fase 2): comprobado aquí
        // como red de seguridad, aunque el flujo normal (job disparado
        // desde finishSession) ya solo se ejecuta para paid-tier.
        if (!Gate::forUser($client)->allows('paid-tier')) {
            return $this->result('denegado_free_tier', null, null, null, null, null);
        }

        $metrics = ExerciseSessionMetric::where('workout_session_review_id', $sessionId)
            ->where('exercise_id', $exerciseId)
            ->where('client_id', $clientId)
            ->first();

        if (!$metrics) {
            return $this->result('sin_metricas', null, null, null, null, null);
        }

        // Paso 1 (documento §2.2): bloqueo por dolor, prioridad absoluta,
        // no pasa por ninguna regla configurada.
        if ($metrics->blocked_by_pain) {
            return $this->finalizeNeutral(
                $client, $exerciseId, $sessionId, $metrics, null,
                ActionType::BLOQUEAR_PROGRESION, TargetStatus::APLICADO, 'bloqueo_por_dolor', $simulate
            );
        }

        // Paso 2: cold start (documento §2.4).
        $calibration = ClientExerciseCalibration::where('client_id', $clientId)
            ->where('exercise_id', $exerciseId)
            ->first();

        if (!$calibration || !$calibration->calibration_complete) {
            return $this->finalizeNeutral(
                $client, $exerciseId, $sessionId, $metrics, null,
                ActionType::MANTENER, TargetStatus::APLICADO, 'calibracion', $simulate
            );
        }

        // Paso 3-4: reglas activas aplicables, ordenadas por jerarquía de scope.
        $trainingProgramId = $this->resolveTrainingProgramIdForSession($sessionId);
        $rules = $this->applicableRules($client, $exerciseId, $trainingProgramId);

        // Paso 5-6: primera regla que matchea gana.
        $winner = null;
        foreach ($rules as $rule) {
            if ($this->ruleExcludedByInsufficientData($rule, $metrics)) {
                continue;
            }
            if ($this->ruleMatches($rule, $metrics, $clientId, $exerciseId)) {
                $winner = $rule;
                break;
            }
        }

        if (!$winner) {
            return $this->finalizeNeutral(
                $client, $exerciseId, $sessionId, $metrics, null,
                ActionType::MANTENER, TargetStatus::APLICADO, 'sin_regla_aplicable', $simulate
            );
        }

        // Paso 7-8: ejecutar la acción ganadora y persistir.
        return $this->executeAction($client, $exerciseId, $sessionId, $metrics, $winner, $simulate);
    }

    /**
     * POST /api/rules/{id}/simulate (documento §2.6) — dry-run de UNA
     * regla concreta contra una fila histórica de exercise_session_metrics,
     * nunca escribe nada. Decisión de diseño propia: a diferencia de
     * evaluateForExercise() (que recorre TODAS las reglas aplicables y
     * decide cuál gana), este método prueba únicamente la regla indicada
     * por el endpoint — el coach quiere validar "¿qué habría hecho ESTA
     * regla?" antes de activarla, no repetir la jerarquía completa. Sigue
     * respetando el bloqueo por dolor (dato real de esa sesión histórica)
     * porque tiene prioridad absoluta incluso en modo simulación; no
     * replica el cold start (client_exercise_calibration solo guarda el
     * estado ACTUAL, no el histórico por fecha, así que no hay forma fiel
     * de "revivir" ese estado para una sesión pasada).
     */
    public function simulateRule(SessionProgressionRule $rule, ExerciseSessionMetric $metrics): array
    {
        // Ítem 2: mismo reset que evaluateForExercise() -- este método es
        // otro punto de entrada que también pasa por evaluateCondition().
        $this->resetEvaluationCache();

        $clientId = (int) $metrics->client_id;
        $exerciseId = (int) $metrics->exercise_id;
        $sessionId = (int) $metrics->workout_session_review_id;

        $client = User::find($clientId);
        if (!$client) {
            return $this->result('sin_cliente', $rule, null, null, null, null);
        }

        if ($metrics->blocked_by_pain) {
            return $this->result('bloqueo_por_dolor', null, ActionType::BLOQUEAR_PROGRESION, null, null, TargetStatus::APLICADO);
        }

        if ($this->ruleExcludedByInsufficientData($rule, $metrics)) {
            return $this->result('excluida_sin_dato_suficiente', $rule, null, null, null, null);
        }

        if (!$this->ruleMatches($rule, $metrics, $clientId, $exerciseId)) {
            return $this->result('no_matchea', $rule, null, null, null, null);
        }

        return $this->executeAction($client, $exerciseId, $sessionId, $metrics, $rule, true);
    }

    // ═══ Selección de reglas ═══════════════════════════════════════════

    private function resetEvaluationCache(): void
    {
        $this->evaluationCache = [];
    }

    /**
     * Reglas activas del coach del cliente, filtradas por scope
     * (documento §2.2 paso 3, ampliado 2026-08-11 con `programa_especifico`)
     * y devueltas ya ordenadas por jerarquía fija de scope > priority desc >
     * created_at desc (paso 4), con empate logueado.
     *
     * Ítem 1 (Plan de Optimización, Ronda 1): cacheada en
     * $applicableRulesCache dentro de la vida de esta instancia -- ver
     * comentario junto a la propiedad para el criterio de la clave.
     */
    public function applicableRules(User $client, int $exerciseId, ?int $trainingProgramId = null): Collection
    {
        $cacheKey = $client->id . ':' . $exerciseId . ':' . ($trainingProgramId ?? 'null');
        if (array_key_exists($cacheKey, $this->applicableRulesCache)) {
            return $this->applicableRulesCache[$cacheKey];
        }

        $coachId = $client->coach_id;
        if (!$coachId) {
            // Sin coach asignado no hay reglas que aplicar (todas las
            // progression_rules del esquema real cuelgan de un coach_id).
            return $this->applicableRulesCache[$cacheKey] = collect();
        }

        $exercise = Exercise::find($exerciseId);
        $bodypartIds = is_array($exercise?->bodypart_ids) ? $exercise->bodypart_ids : [];

        $rules = SessionProgressionRule::where('coach_id', $coachId)
            ->where('active', true)
            ->where(function ($q) use ($client, $exerciseId, $bodypartIds, $trainingProgramId) {
                $q->where(function ($qq) use ($client) {
                    $qq->where('scope_type', ScopeType::CLIENTE_ESPECIFICO->value)
                        ->where('scope_id', $client->id);
                })->orWhere(function ($qq) use ($exerciseId) {
                    $qq->where('scope_type', ScopeType::EJERCICIO_ESPECIFICO->value)
                        ->where('scope_id', $exerciseId);
                })->orWhere(function ($qq) use ($bodypartIds) {
                    $qq->where('scope_type', ScopeType::CATEGORIA_EJERCICIO->value)
                        ->whereIn('scope_id', $bodypartIds ?: [-1]);
                })->orWhere('scope_type', ScopeType::GLOBAL->value);

                // programa_especifico: aplica a cualquier cliente que esté
                // corriendo ese training_program concreto (sesión suelta,
                // sin program_day_assignment -> $trainingProgramId es null,
                // ninguna regla de este scope puede matchear, correcto).
                if ($trainingProgramId !== null) {
                    $q->orWhere(function ($qq) use ($trainingProgramId) {
                        $qq->where('scope_type', ScopeType::PROGRAMA_ESPECIFICO->value)
                            ->where('scope_id', $trainingProgramId);
                    });
                }
            })
            ->with(['conditions', 'action'])
            ->get();

        $sorted = $rules->sort(function (SessionProgressionRule $a, SessionProgressionRule $b) {
            $rankA = ScopeType::from($a->scope_type->value)->hierarchyRank();
            $rankB = ScopeType::from($b->scope_type->value)->hierarchyRank();
            if ($rankA !== $rankB) {
                return $rankB <=> $rankA;
            }
            if ($a->priority !== $b->priority) {
                return $b->priority <=> $a->priority;
            }
            return $b->created_at <=> $a->created_at;
        })->values();

        // Empate real (mismo rank+priority+created_at, o mismo
        // rank+priority sin poder desempatar más) -> loggear, documento §2.2 paso 4.
        for ($i = 0; $i < $sorted->count() - 1; $i++) {
            $a = $sorted[$i];
            $b = $sorted[$i + 1];
            $rankA = ScopeType::from($a->scope_type->value)->hierarchyRank();
            $rankB = ScopeType::from($b->scope_type->value)->hierarchyRank();
            if ($rankA === $rankB && $a->priority === $b->priority) {
                Log::info('SessionProgressionRuleEngine: empate de prioridad entre reglas', [
                    'rule_a' => $a->id, 'rule_b' => $b->id, 'scope' => $a->scope_type->value, 'priority' => $a->priority,
                ]);
            }
        }

        return $this->applicableRulesCache[$cacheKey] = $sorted;
    }

    /**
     * documento §2.2 paso 5: excluir automáticamente cualquier regla que
     * dependa de rir_delta_sesión/peor_serie si sin_dato_suficiente=true.
     */
    private function ruleExcludedByInsufficientData(SessionProgressionRule $rule, ExerciseSessionMetric $metrics): bool
    {
        if (!$metrics->sin_dato_suficiente) {
            return false;
        }

        foreach ($rule->conditions as $condition) {
            if ($condition->variable->dependsOnRirData()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evalúa las condiciones de la regla agrupadas por logic_group: AND
     * dentro del grupo, OR entre grupos (documento §2.2 paso 5). Una regla
     * sin condiciones se trata como comodín (siempre matchea) — útil para
     * una regla global de fallback.
     *
     * Plan de Optimización, Ronda 14 ítem 43: "N de M condiciones" -- un
     * grupo con `min_condiciones_requeridas` configurado ya no exige que
     * las M condiciones se cumplan, basta con que N de ellas se cumplan
     * (sin short-circuit: hay que evaluarlas todas para poder contar).
     */
    private function ruleMatches(SessionProgressionRule $rule, ExerciseSessionMetric $metrics, int $clientId, int $exerciseId): bool
    {
        $conditions = $rule->conditions;
        if ($conditions->isEmpty()) {
            return true;
        }

        $groups = $conditions->groupBy('logic_group');

        foreach ($groups as $group) {
            $minRequired = $this->resolveMinCondicionesRequeridas($group);
            $passedCount = 0;
            foreach ($group as $condition) {
                if ($this->evaluateCondition($condition, $metrics, $clientId, $exerciseId)) {
                    $passedCount++;
                }
            }
            if ($passedCount >= $minRequired) {
                return true; // OR entre grupos: el primer grupo que cumple basta.
            }
        }

        return false;
    }

    /**
     * Ítem 43 (Ronda 14): toma el mayor `min_condiciones_requeridas`
     * configurado por el coach en cualquiera de las condiciones del grupo
     * (es un parámetro de grupo, no de condición individual, pero la
     * columna vive en `session_progression_rule_conditions` para no crear
     * una tabla de grupos aparte). Sin configurar en ninguna condición del
     * grupo -> AND estricto de siempre (N = tamaño del grupo).
     */
    private function resolveMinCondicionesRequeridas(Collection $group): int
    {
        $configured = $group->pluck('min_condiciones_requeridas')->filter()->max();

        return $configured ? min((int) $configured, $group->count()) : $group->count();
    }

    private function evaluateCondition($condition, ExerciseSessionMetric $metrics, int $clientId, int $exerciseId): bool
    {
        $variable = $condition->variable;

        // ACTUALIZADO (Plan_Cierre_Motor_UI.md, "Crear/editar reglas...
        // Plantilla 5"): readiness_scores SÍ existe ya (Fase 4, construida
        // el mismo día que este motor) -- el gate isPhase4Only() que
        // forzaba estas 3 variables a "nunca cumplida" quedó obsoleto,
        // eliminado. Sin dato para la fecha de la sesión (cliente sin
        // wearable, o sin cuestionario ese día) -> resolveVariableValue()
        // devuelve null -> la condición falla igual que cualquier otra
        // variable sin dato (mismo criterio ya establecido en todo el
        // motor, no hace falta un caso especial aquí).
        $value = $this->resolveVariableValue($variable, $metrics, $clientId, $exerciseId);
        if ($value === null) {
            return false; // dato no disponible -> la condición falla, nunca rompe.
        }

        $operator = $condition->operator;

        return match ($operator) {
            ConditionOperator::GTE => $value >= (float) $condition->threshold_value,
            ConditionOperator::LTE => $value <= (float) $condition->threshold_value,
            ConditionOperator::EQ => abs($value - (float) $condition->threshold_value) < 0.0001,
            ConditionOperator::BETWEEN => $value >= (float) $condition->threshold_min && $value <= (float) $condition->threshold_max,
            // no_change_for_n: "sin cambio durante N sesiones" -> el
            // contador ya persistido (sesiones_consecutivas_sin_cambio)
            // debe alcanzar la ventana configurada en la propia condición.
            ConditionOperator::NO_CHANGE_FOR_N => $value >= (float) ($condition->ventana_sesiones ?: 1),
        };
    }

    private function resolveVariableValue(ConditionVariable $variable, ExerciseSessionMetric $metrics, int $clientId, int $exerciseId): ?float
    {
        return match ($variable) {
            ConditionVariable::RIR_DELTA_SESION => $metrics->rir_delta_sesion,
            ConditionVariable::COMPLETION_RATIO => $metrics->completion_ratio,
            ConditionVariable::TENDENCIA_RIR => $metrics->tendencia_rir,
            ConditionVariable::TENDENCIA_VOLUMEN => $metrics->tendencia_volumen,
            ConditionVariable::SESIONES_CONSECUTIVAS_SIN_CAMBIO => (float) $metrics->sesiones_consecutivas_sin_cambio,
            ConditionVariable::PEOR_SERIE => $metrics->peor_serie_rir,
            ConditionVariable::SIN_DATO_SUFICIENTE => $metrics->sin_dato_suficiente ? 1.0 : 0.0,
            ConditionVariable::E1RM_DELTA => $this->resolveE1rmDelta($clientId, $exerciseId, $metrics),
            ConditionVariable::HRV_Z_SCORE, ConditionVariable::SUENO_Z_SCORE, ConditionVariable::READINESS_BAND =>
                $this->resolveReadinessValue($variable, $clientId, $metrics),
            ConditionVariable::NIVEL_EXPERIENCIA => $this->resolveNivelExperiencia($clientId),
            ConditionVariable::RIR_DELTA_SERIE_TOP => $metrics->rir_delta_serie_top,
            ConditionVariable::REPS_EN_TOPE_RANGO => $this->resolveRepsEnTopeRango($metrics),
            ConditionVariable::ROL_EJERCICIO => $this->resolveRolEjercicio($metrics),
            ConditionVariable::DOLOR_RECIENTE_NO_BLOQUEANTE => $this->resolveDolorRecienteNoBloqueante($clientId, $exerciseId),
            ConditionVariable::ACCIONES_BAJADA_EN_SESION => (float) $this->accionesBajadaEnSesion,
        };
    }

    /**
     * Ítem 38 (Plan de Optimización, Ronda 13): 1.0 si la sesión llegó al
     * tope del rango de reps prescrito (`reps_max` en `prescribed`, clave
     * nueva junto a las ya existentes 'series'/'reps'/'rir' -- JSON, sin
     * migración), 0.0 si no lo alcanzó. Sin `reps_max` configurado por el
     * coach para este ejercicio, o sin `carga_efectiva_reps` de esta sesión
     * -> null (sin dato, la condición falla como cualquier otra). El coach
     * monta la doble progresión clásica con dos reglas ordenadas por
     * `priority` (jerarquía ya existente): una que sube reps mientras esto
     * sea 0.0, otra que sube carga (y resetea reps) cuando esto es 1.0.
     */
    private function resolveRepsEnTopeRango(ExerciseSessionMetric $metrics): ?float
    {
        if ($metrics->carga_efectiva_reps === null || !$metrics->workout_template_exercise_id) {
            return null;
        }

        $wte = WorkoutTemplateExercise::find($metrics->workout_template_exercise_id);
        $prescribed = $wte && is_array($wte->prescribed) ? $wte->prescribed : [];
        $repsMax = isset($prescribed['reps_max']) && is_numeric($prescribed['reps_max']) ? (int) $prescribed['reps_max'] : null;

        if ($repsMax === null) {
            return null;
        }

        return $metrics->carga_efectiva_reps >= $repsMax ? 1.0 : 0.0;
    }

    /**
     * Ítem 41 (Plan de Optimización, Ronda 13): 1.0 si este ejercicio es el
     * "principal" de su bloque (primer `sequence`, mismo proxy ya usado y
     * documentado en `AdaptiveWeekPlanner`/`MesocycleClosureService::principalExerciseIds()`),
     * 0.0 si es accesorio. Sin `workout_template_exercise_id` (ejercicio
     * ad-hoc sin slot prescrito) -> null, sin dato.
     */
    private function resolveRolEjercicio(ExerciseSessionMetric $metrics): ?float
    {
        if (!$metrics->workout_template_exercise_id) {
            return null;
        }

        $wte = WorkoutTemplateExercise::find($metrics->workout_template_exercise_id);
        if (!$wte || !$wte->block) {
            return null;
        }

        // WorkoutTemplateBlock::exercises() ya ordena por 'sequence'.
        $principal = $wte->block->exercises()->first();

        return ($principal && $principal->id === $wte->id) ? 1.0 : 0.0;
    }

    /**
     * Ítem 42 (Plan de Optimización, Ronda 13): 1.0 si hubo algún
     * `pain_report` para este cliente+ejercicio en los últimos
     * DOLOR_RECIENTE_WINDOW_DAYS días que NO llegó a bloquear progresión
     * (`blocksProgression()` false -- una molestia leve real, distinta del
     * bloqueo total ya cubierto en `evaluateForExercise()` L88-93). Sin
     * NINGÚN reporte en la ventana -> 0.0 (respuesta real, no "sin dato":
     * a diferencia de readiness, un pain_report es un evento que ocurre o
     * no ocurre, su ausencia SÍ es información).
     */
    private function resolveDolorRecienteNoBloqueante(int $clientId, int $exerciseId): float
    {
        $cacheKey = "dolor_reciente:{$clientId}:{$exerciseId}";
        if (array_key_exists($cacheKey, $this->evaluationCache)) {
            return $this->evaluationCache[$cacheKey];
        }

        $recentReports = PainReport::where('client_id', $clientId)
            ->where('exercise_id', $exerciseId)
            ->where('created_at', '>=', now()->subDays(self::DOLOR_RECIENTE_WINDOW_DAYS))
            ->get();

        if ($recentReports->isEmpty()) {
            return $this->evaluationCache[$cacheKey] = 0.0;
        }

        return $this->evaluationCache[$cacheKey] = ($recentReports->contains(fn (PainReport $report) => !$report->blocksProgression()) ? 1.0 : 0.0);
    }

    /**
     * Plan de Optimización, Ronda 7 ítem 25: meses de experiencia real de
     * entrenamiento, con prioridad override-del-coach > autoevaluado por el
     * cliente > sin dato (mismo criterio "sin dato = la condición falla"
     * de todo el motor, ver docblock de evaluateCondition()). Memoizado en
     * $evaluationCache -- una regla puede tener varias condiciones que lean
     * esta misma variable para el mismo cliente en la misma evaluación.
     */
    private function resolveNivelExperiencia(int $clientId): ?float
    {
        $cacheKey = "nivel_experiencia:{$clientId}";
        if (array_key_exists($cacheKey, $this->evaluationCache)) {
            return $this->evaluationCache[$cacheKey];
        }

        $answer = TrainingQuestionnaireAnswer::where('user_id', $clientId)->first();
        $months = $answer?->effectiveExperienceMonths();

        return $this->evaluationCache[$cacheKey] = $months !== null ? (float) $months : null;
    }

    /**
     * readiness_scores (Fase 4) para la fecha de la sesión evaluada -- una
     * fila por client_id+date, ver ReadinessCalculationService. Sin fila
     * ese día (cliente sin wearable/cuestionario) -> null, la condición
     * falla igual que cualquier otra variable sin dato disponible.
     *
     * readiness_band es texto ('optimo'/'reducido'/'bajo'/
     * 'dato_insuficiente'), pero threshold_value/min/max de
     * session_progression_rule_conditions son decimal -- se mapea a una
     * escala ordinal (peor -> mejor) para que gte/lte/eq/between tengan
     * sentido numérico sin tocar el esquema: bajo=0, reducido=1, optimo=2.
     * 'dato_insuficiente' -> null (no es una banda real de estado, es la
     * ausencia de dato -- ninguna condición debería considerarla "peor que
     * bajo" ni "mejor que optimo", se trata como sin dato).
     */
    /**
     * Ítem 2 (Plan de Optimización, Ronda 1): antes se ejecutaba esta
     * misma query una vez POR CONDICIÓN evaluada (si dos condiciones de la
     * misma regla, o de dos reglas candidatas distintas, leen
     * hrv_z_score/sueno_z_score/readiness_band del mismo cliente+fecha,
     * eran N queries idénticas) -- ahora se memoiza en $evaluationCache,
     * reseteada al empezar cada evaluateForExercise()/simulateRule().
     */
    private function resolveReadinessValue(ConditionVariable $variable, int $clientId, ExerciseSessionMetric $metrics): ?float
    {
        $score = $this->readinessScoreForDate($clientId, $metrics->created_at->toDateString());

        if (!$score) {
            return null;
        }

        return match ($variable) {
            ConditionVariable::HRV_Z_SCORE => $score->hrv_z_score,
            ConditionVariable::SUENO_Z_SCORE => $score->sueno_z_score,
            ConditionVariable::READINESS_BAND => $this->resolveSustainedReadinessBand($clientId, $score),
        };
    }

    private function readinessScoreForDate(int $clientId, string $date): ?ReadinessScore
    {
        $cacheKey = "readiness_score:{$clientId}:{$date}";

        if (!array_key_exists($cacheKey, $this->evaluationCache)) {
            $this->evaluationCache[$cacheKey] = ReadinessScore::where('client_id', $clientId)
                ->where('date', $date)
                ->first();
        }

        return $this->evaluationCache[$cacheKey];
    }

    /**
     * Ítem 21 (Plan de Optimización, Ronda 6 -- "hallazgo más urgente" del
     * análisis de Fase 4): ReadinessCalculationService::mapBand() puede
     * marcar la banda del día como 'bajo' por una ÚNICA métrica objetiva o
     * subjetiva en zona baja (regla de conflicto, ver mapBand()), algo
     * estadísticamente normal (~16% de probabilidad por azar con datos que
     * siguen aprox. una normal) -- ese 'bajo' de un solo día alimentaba
     * directamente esta condición del motor, pudiendo disparar una bajada
     * de carga real por ruido de una sola métrica.
     *
     * Mismo criterio de sostenimiento que ya usa
     * ReadinessCalculationService::syncReadinessExceptionItem() para el
     * panel del coach (2+ días consecutivos en 'bajo' antes de avisar),
     * replicado aquí SOLO para esta lectura -- sin tocar
     * ReadinessCalculationService (fuera de alcance, otro agente trabaja
     * en ese fichero en paralelo).
     *
     * Si 'bajo' de hoy NO está sostenido por el día anterior, no se deja
     * pasar igualmente como 'bajo': se usa el valor real de la banda de
     * ESE día sin la regla de conflicto de una sola métrica mala, es
     * decir, la banda que habría salido solo por combined_score (mismos
     * umbrales que mapBand(): >=70 óptimo, >=45 reducido, si no bajo).
     * band === 'bajo' garantiza combined_score no nulo (mapBand() solo
     * devuelve 'bajo' por conflicto después de comprobar que combined ya
     * se pudo calcular; 'dato_insuficiente' es el único caso con combined
     * null y no entra en esta rama), así que no hace falta contemplar aquí
     * un tercer caso de dato insuficiente.
     */
    private function resolveSustainedReadinessBand(int $clientId, ReadinessScore $score): ?float
    {
        if ($score->band !== 'bajo') {
            return $this->readinessBandOrdinal($score->band);
        }

        $yesterday = $this->readinessScoreForDate($clientId, $score->date->copy()->subDay()->toDateString());

        if ($yesterday && $yesterday->band === 'bajo') {
            return 0.0; // 2+ días consecutivos en 'bajo' -> señal real, sí dispara.
        }

        return $this->readinessBandOrdinal($this->scoreOnlyBand((float) $score->combined_score));
    }

    /**
     * Réplica local (solo lectura) del umbral final de
     * ReadinessCalculationService::mapBand() DESPUÉS de la regla de
     * conflicto -- es decir, a qué banda habría llegado el día únicamente
     * por su combined_score, ignorando que una sola métrica mala pudo
     * forzarlo a 'bajo'. No se reutiliza mapBand() directamente porque es
     * privado de un servicio fuera de mi alcance en esta tarea; esto es
     * matemática pura sobre un valor ya persistido (combined_score),
     * ningún hueco de "no tocar ReadinessCalculationService".
     */
    private function scoreOnlyBand(float $combinedScore): string
    {
        if ($combinedScore >= 70.0) {
            return 'optimo';
        }
        if ($combinedScore >= 45.0) {
            return 'reducido';
        }

        return 'bajo';
    }

    private function readinessBandOrdinal(?string $band): ?float
    {
        return match ($band) {
            'bajo' => 0.0,
            'reducido' => 1.0,
            'optimo' => 2.0,
            default => null, // 'dato_insuficiente'
        };
    }

    /**
     * Ítem 2 (Plan de Optimización, Ronda 1): memoizada en
     * $evaluationCache por exercise_session_metric->id (identifica de
     * forma única cliente+ejercicio+sesión ya evaluados), mismo criterio
     * que resolveReadinessValue().
     */
    private function resolveE1rmDelta(int $clientId, int $exerciseId, ExerciseSessionMetric $current): ?float
    {
        $cacheKey = "e1rm_delta:{$current->id}";
        if (array_key_exists($cacheKey, $this->evaluationCache)) {
            return $this->evaluationCache[$cacheKey];
        }

        if ($current->e1rm_estimado === null) {
            return $this->evaluationCache[$cacheKey] = null;
        }

        $previous = ExerciseSessionMetric::where('client_id', $clientId)
            ->where('exercise_id', $exerciseId)
            ->where('id', '!=', $current->id)
            ->where('is_outlier', false)
            ->where('blocked_by_pain', false)
            ->whereNotNull('e1rm_estimado')
            ->orderByDesc('created_at')
            ->first();

        if (!$previous) {
            return $this->evaluationCache[$cacheKey] = null;
        }

        return $this->evaluationCache[$cacheKey] = round($current->e1rm_estimado - $previous->e1rm_estimado, 2);
    }

    // ═══ Ejecución de la acción ganadora ═══════════════════════════════

    private function executeAction(User $client, int $exerciseId, int $sessionId, ExerciseSessionMetric $metrics, SessionProgressionRule $rule, bool $simulate): array
    {
        $action = $rule->action;

        if (!$action) {
            // Regla mal configurada (sin acción asociada) -> salvaguarda: mantener.
            return $this->finalizeNeutral($client, $exerciseId, $sessionId, $metrics, $rule, ActionType::MANTENER, TargetStatus::APLICADO, 'regla_sin_accion', $simulate);
        }

        $type = $action->type;

        // Fase 3 (documento §3.1): sustituir_ejercicio ahora tiene lógica
        // real (validación de estancamiento + búsqueda de variante en
        // exercise_substitutions) — se intercepta ANTES del switch neutral
        // genérico, que ya no la trata como siempre-degradada.
        if ($type === ActionType::SUSTITUIR_EJERCICIO) {
            return $this->executeSustitucion($client, $exerciseId, $sessionId, $metrics, $rule, $simulate);
        }

        if ($type->isNeutral()) {
            $status = match ($type) {
                ActionType::MANTENER, ActionType::BLOQUEAR_PROGRESION => TargetStatus::APLICADO,
                ActionType::MARCAR_PARA_COACH => TargetStatus::PENDIENTE,
                default => TargetStatus::APLICADO,
            };

            return $this->finalizeNeutral($client, $exerciseId, $sessionId, $metrics, $rule, $type, $status, $type->value, $simulate);
        }

        $base = $this->resolveBaseReference($action->base_reference, $client, $exerciseId, $metrics, $sessionId);

        $proposedWeight = null;
        $proposedReps = null;

        switch ($type) {
            case ActionType::AJUSTAR_CARGA_PCT:
                if ($base['weight'] === null) {
                    return $this->finalizeNeutral($client, $exerciseId, $sessionId, $metrics, $rule, ActionType::MANTENER, TargetStatus::APLICADO, 'sin_base_referencia', $simulate);
                }
                $proposedWeight = $this->applyRounding($base['weight'] * (1 + ((float) $action->value) / 100), $exerciseId, $action->rounding);
                break;

            case ActionType::AJUSTAR_CARGA_ABSOLUTA:
                if ($base['weight'] === null) {
                    return $this->finalizeNeutral($client, $exerciseId, $sessionId, $metrics, $rule, ActionType::MANTENER, TargetStatus::APLICADO, 'sin_base_referencia', $simulate);
                }
                $proposedWeight = $this->applyRounding($base['weight'] + (float) $action->value, $exerciseId, $action->rounding);
                break;

            case ActionType::BAJAR_CARGA_PCT:
                if ($base['weight'] === null) {
                    return $this->finalizeNeutral($client, $exerciseId, $sessionId, $metrics, $rule, ActionType::MANTENER, TargetStatus::APLICADO, 'sin_base_referencia', $simulate);
                }
                $proposedWeight = $this->applyRounding($base['weight'] * (1 - abs((float) $action->value) / 100), $exerciseId, $action->rounding);
                break;

            case ActionType::AJUSTAR_REPS:
                if ($base['reps'] === null) {
                    return $this->finalizeNeutral($client, $exerciseId, $sessionId, $metrics, $rule, ActionType::MANTENER, TargetStatus::APLICADO, 'sin_base_referencia', $simulate);
                }
                $proposedReps = max(0, (int) round($base['reps'] + (float) $action->value));
                break;

            default:
                return $this->finalizeNeutral($client, $exerciseId, $sessionId, $metrics, $rule, ActionType::MANTENER, TargetStatus::APLICADO, 'accion_no_reconocida', $simulate);
        }

        $status = $rule->mode === \App\Enums\RuleMode::AUTOMATICO ? TargetStatus::APLICADO : TargetStatus::PENDIENTE;

        return $this->finalizeProposal($client, $exerciseId, $sessionId, $metrics, $rule, $proposedWeight, $proposedReps, $status, $simulate);
    }

    /**
     * Ítem 40 (Plan de Optimización, Ronda 13): si el EJERCICIO tiene su
     * propio `increment_kg` configurado (mancuernas ±2kg, máquina con
     * saltos de 5kg, barra con discos de 1.25kg...), se usa como fallback
     * ANTES que el `RoundingMode` genérico de la regla -- evita proponer un
     * peso no cargable en la práctica en ese equipo concreto. Sin
     * `increment_kg` (el caso por defecto, nadie lo ha configurado), cae
     * exactamente en el `RoundingMode` de la acción, comportamiento
     * idéntico al de antes de este ítem.
     */
    private function applyRounding(float $value, int $exerciseId, \App\Enums\RoundingMode $fallbackRounding): float
    {
        $incrementKg = Exercise::find($exerciseId)?->increment_kg;

        if ($incrementKg !== null && (float) $incrementKg > 0) {
            return round($value / $incrementKg) * $incrementKg;
        }

        return $fallbackRounding->apply($value);
    }

    // ═══ Fase 3: sustitución de ejercicio (documento §3.1) ══════════════

    /**
     * documento §3.1: antes de disparar una sustitución, validar la lógica
     * de estancamiento. Orden: 1) completion_ratio de la ventana bajando
     * (posible sobreentrenamiento, no estancamiento simple) -> siempre
     * marcar_para_coach, con prioridad sobre la comprobación de readiness.
     * 2) readiness_band=bajo en >50% de la ventana -> NO sustituir (sin
     * datos de readiness para el cliente, se omite esta condición, no
     * bloqueante — decisión ya tomada con el usuario). Solo si ninguna de
     * las dos bloquea, se busca una variante real en exercise_substitutions;
     * si no existe, degrada a marcar_para_coach (comportamiento heredado
     * de Fase 2, ahora condicionado en vez de incondicional).
     */
    private function executeSustitucion(User $client, int $exerciseId, int $sessionId, ExerciseSessionMetric $metrics, SessionProgressionRule $rule, bool $simulate): array
    {
        $window = $this->recentValidMetricsWindow($client->id, $exerciseId, $metrics->id);

        if ($this->isCompletionRatioDeclining($window)) {
            return $this->finalizeNeutral(
                $client, $exerciseId, $sessionId, $metrics, $rule,
                ActionType::MARCAR_PARA_COACH, TargetStatus::PENDIENTE,
                'posible_sobreentrenamiento_completion_ratio_bajando', $simulate
            );
        }

        if ($this->isReadinessLowMajority($client->id, $window)) {
            return $this->finalizeNeutral(
                $client, $exerciseId, $sessionId, $metrics, $rule,
                ActionType::MARCAR_PARA_COACH, TargetStatus::PENDIENTE,
                'sustitucion_bloqueada_por_readiness_bajo', $simulate
            );
        }

        $substitution = $this->findSubstitution($client->coach_id, $exerciseId, $this->inferSubstitutionMotivo($rule));

        if (!$substitution) {
            return $this->finalizeNeutral(
                $client, $exerciseId, $sessionId, $metrics, $rule,
                ActionType::MARCAR_PARA_COACH, TargetStatus::PENDIENTE,
                'sustitucion_sin_variante_definida', $simulate
            );
        }

        return $this->finalizeSubstitution($client, $exerciseId, $sessionId, $metrics, $rule, $substitution, $simulate);
    }

    /**
     * Ítem 30 (Plan de Optimización, Ronda 11): `category` existía en el
     * esquema desde Fase 3 pero nunca se usaba -- se cogía la primera fila
     * sin criterio (ni siquiera determinista, sin `orderBy`). Ahora, si se
     * infirió un motivo (ver inferSubstitutionMotivo()), se prioriza una
     * variante etiquetada con esa `category`; si no hay ninguna así, cae a
     * una variante genérica (`category` null) para no perder cobertura de
     * las sustituciones ya configuradas antes de que existiera esta lógica.
     * `orderBy('id')` en ambos pasos -- determinista si el coach llegara a
     * definir más de una variante para el mismo (coach, ejercicio, category).
     */
    private function findSubstitution(?int $coachId, int $exerciseId, ?string $motivo): ?ExerciseSubstitution
    {
        if (!$coachId) {
            return null;
        }

        $base = ExerciseSubstitution::where('coach_id', $coachId)->where('original_exercise_id', $exerciseId);

        if ($motivo !== null) {
            $tagged = (clone $base)->where('category', $motivo)->orderBy('id')->first();
            if ($tagged) {
                return $tagged;
            }
        }

        return (clone $base)->whereNull('category')->orderBy('id')->first()
            ?? (clone $base)->orderBy('id')->first();
    }

    /**
     * Ítem 31 (Plan de Optimización, Ronda 11): motivo de la regla ganadora,
     * derivado de las variables que usan sus condiciones -- ninguna columna
     * de la regla registra "por qué" de forma explícita, así que se infiere
     * de QUÉ está midiendo. Heurística deliberadamente simple (no exhaustiva):
     * variables de estancamiento/rendimiento -> 'estancamiento'; variables de
     * readiness -> 'fatiga' (una regla puede condicionar a un umbral puntual
     * de readiness sin pasar por isReadinessLowMajority(), que solo bloquea
     * por MAYORÍA de la ventana). Una regla sin condiciones (comodín) o con
     * variables no mapeadas -> null, cae al comportamiento genérico. Vocabulario
     * abierto a extender cuando existan más ConditionVariable (p. ej. la Ronda 13
     * añade DOLOR_RECIENTE_NO_BLOQUEANTE -> debería mapear a 'dolor' aquí).
     */
    private function inferSubstitutionMotivo(SessionProgressionRule $rule): ?string
    {
        $variables = $rule->conditions->pluck('variable');

        $estancamiento = [
            ConditionVariable::SESIONES_CONSECUTIVAS_SIN_CAMBIO,
            ConditionVariable::TENDENCIA_RIR,
            ConditionVariable::E1RM_DELTA,
            ConditionVariable::RIR_DELTA_SESION,
            ConditionVariable::PEOR_SERIE,
            ConditionVariable::COMPLETION_RATIO,
        ];
        if ($variables->intersect($estancamiento)->isNotEmpty()) {
            return 'estancamiento';
        }

        $fatiga = [ConditionVariable::READINESS_BAND, ConditionVariable::HRV_Z_SCORE, ConditionVariable::SUENO_Z_SCORE];
        if ($variables->intersect($fatiga)->isNotEmpty()) {
            return 'fatiga';
        }

        return null;
    }

    private function recentValidMetricsWindow(int $clientId, int $exerciseId, int $excludeMetricId): Collection
    {
        return ExerciseSessionMetric::where('client_id', $clientId)
            ->where('exercise_id', $exerciseId)
            ->where('id', '!=', $excludeMetricId)
            ->where('is_outlier', false)
            ->where('blocked_by_pain', false)
            ->orderByDesc('created_at')
            ->limit(self::STAGNATION_WINDOW)
            ->get(['id', 'completion_ratio', 'created_at']);
    }

    private function isCompletionRatioDeclining(Collection $window): bool
    {
        $chronological = $window->reverse()->values();
        $ratios = $chronological->pluck('completion_ratio')->filter(fn ($v) => $v !== null)->values()->all();

        if (count($ratios) < 2) {
            return false; // sin suficiente histórico para hablar de tendencia.
        }

        return $this->linearSlope($ratios) < 0;
    }

    /**
     * Pendiente lineal simple — antes reimplementada aquí de forma
     * IDÉNTICA a SessionInterpretationService::linearSlope() (comentario
     * previo reconociéndolo explícitamente); el núcleo matemático ahora
     * vive en el trait compartido ComputesLinearSlope (Plan de
     * Optimización, Ronda 3 ítem 9). Este método se mantiene como wrapper
     * fino porque esta comprobación pertenece al motor de reglas, no a la
     * interpretación de sesión, y porque aquí NUNCA se llama con n<2 (el
     * único llamador, isCompletionRatioDeclining(), ya lo garantiza) ni se
     * redondea el resultado -- a diferencia del wrapper de
     * SessionInterpretationService, que sí hace ambas cosas. Cero cambio
     * de comportamiento respecto a antes de esta extracción.
     */
    private function linearSlope(array $values): float
    {
        return $this->computeRawLinearSlope($values);
    }

    /**
     * documento §3.1: "si Fase 4 no está implementada aún, omitir esta
     * condición" — generalizado (Fase 4 SÍ está implementada) a "sin datos
     * de readiness para este cliente en la ventana concreta", mismo
     * criterio de no-bloqueante, aplicado ahora por falta de dato en vez de
     * por falta de feature.
     */
    private function isReadinessLowMajority(int $clientId, Collection $window): bool
    {
        if ($window->isEmpty()) {
            return false;
        }

        $dates = $window->pluck('created_at')->filter()->map(fn ($d) => $d->toDateString())->unique()->values();
        if ($dates->isEmpty()) {
            return false;
        }

        $scores = ReadinessScore::where('client_id', $clientId)
            ->whereIn('date', $dates)
            ->get(['band']);

        if ($scores->isEmpty()) {
            return false;
        }

        $lowCount = $scores->where('band', 'bajo')->count();

        return ($lowCount / $scores->count()) > self::READINESS_LOW_MAJORITY_RATIO;
    }

    /**
     * Decisión de diseño propia: no existe hoy un mecanismo para "aplicar"
     * automáticamente un cambio de EJERCICIO al plan del cliente
     * (ClientExerciseOverride solo permite ajustar valores prescritos del
     * MISMO ejercicio, no sustituirlo por otro) — a diferencia de
     * ajustar_carga_pct/etc., que sí tienen un camino automático seguro
     * (applyToNextScheduledSession). Por eso una sustitución propuesta
     * SIEMPRE queda `pendiente` de aprobación del coach, independientemente
     * del `mode` de la regla — nunca se aplica sola. El ejercicio sustituto
     * propuesto se guarda en next_session_targets.proposed_exercise_id.
     *
     * Ítem 32 (Plan de Optimización, Ronda 11): `proposed_weight` ya no es
     * siempre null -- si la variante tiene `carga_ratio` configurado, se
     * propone `referencia * carga_ratio` (redondeado con el mismo
     * RoundingMode de la acción ganadora, que siempre existe aquí porque
     * `executeAction()` ya comprobó `$rule->action` antes de llegar a
     * `executeSustitucion()`). Sin `carga_ratio` -- el caso por defecto,
     * nadie lo ha configurado -- se mantiene exactamente el comportamiento
     * anterior (null, el coach decide desde cero).
     */
    private function finalizeSubstitution(User $client, int $exerciseId, int $sessionId, ExerciseSessionMetric $metrics, SessionProgressionRule $rule, ExerciseSubstitution $substitution, bool $simulate): array
    {
        $substituteExerciseId = (int) $substitution->substitute_exercise_id;
        $proposedWeight = $this->resolveSubstitutionStartingWeight($metrics, $rule, $substitution);

        if ($simulate) {
            return $this->result('sustitucion_propuesta', $rule, ActionType::SUSTITUIR_EJERCICIO, $proposedWeight, null, TargetStatus::PENDIENTE);
        }

        $target = NextSessionTarget::updateOrCreate(
            ['workout_session_review_id' => $sessionId, 'exercise_id' => $exerciseId, 'client_id' => $client->id],
            [
                'rule_id'               => $rule->id,
                'proposed_weight'        => $proposedWeight,
                'proposed_reps'          => null,
                'proposed_exercise_id'   => $substituteExerciseId,
                'status'                 => TargetStatus::PENDIENTE->value,
                'generated_at'           => now(),
            ]
        );

        // Panel de Excepciones del Coach (documento §3.3): sustitución CON
        // variante real definida -- distinta de "marcar_para_coach" (sin
        // variante, ver createExceptionItemForPendingTarget), siempre
        // pendiente de aprobación, nunca se auto-aplica.
        if ($client->coach_id) {
            $exerciseTitle = optional(Exercise::find($exerciseId))->title ?? 'un ejercicio';
            $substituteTitle = optional(Exercise::find($substituteExerciseId))->title ?? 'un sustituto';

            (new CoachExceptionFeedService())->createOrSkip(
                coachId: (int) $client->coach_id,
                clientId: $client->id,
                category: ExceptionCategory::SUGERENCIA_CARGA,
                severity: ExceptionSeverity::BAJA,
                sourceType: NextSessionTarget::class,
                sourceId: $target->id,
                title: "Sustitución de ejercicio propuesta: \"{$exerciseTitle}\" → \"{$substituteTitle}\"",
                description: 'Sustitución con variante ya definida por ti -- requiere aprobación.'
            );
        }

        $this->updateRachaMismaDireccion($metrics, $client->id, $exerciseId, $rule);

        return $this->result('sustitucion_propuesta', $rule, ActionType::SUSTITUIR_EJERCICIO, $proposedWeight, null, TargetStatus::PENDIENTE, $target);
    }

    /**
     * Ítem 32 (Plan de Optimización, Ronda 11): peso de referencia del
     * ejercicio ORIGINAL (e1RM estimado si existe, si no la carga_efectiva
     * de esta sesión -- mismo orden de preferencia que
     * BaseReference::E1RM_ESTIMADO/ULTIMO_EFECTIVO) multiplicado por
     * `carga_ratio` de la variante, redondeado con el mismo RoundingMode
     * que la acción de la regla ganadora ya usa para ajustes normales de
     * carga. Sin `carga_ratio` configurado, o sin ninguna referencia de
     * peso disponible todavía (ejercicio nuevo, sin histórico) -> null,
     * mismo comportamiento que antes de este ítem.
     */
    private function resolveSubstitutionStartingWeight(ExerciseSessionMetric $metrics, SessionProgressionRule $rule, ExerciseSubstitution $substitution): ?float
    {
        if ($substitution->carga_ratio === null) {
            return null;
        }

        $reference = $metrics->e1rm_estimado ?? $metrics->carga_efectiva;
        if ($reference === null) {
            return null;
        }

        $proposed = $reference * $substitution->carga_ratio;

        // Ítem 40 (Ronda 13): redondeo por equipo del ejercicio SUSTITUTO
        // (el peso propuesto es para ESE ejercicio, no para el original) --
        // cae al RoundingMode de la regla si no tiene increment_kg propio.
        if (!$rule->action) {
            return round($proposed, 2);
        }

        return $this->applyRounding($proposed, (int) $substitution->substitute_exercise_id, $rule->action->rounding);
    }

    /**
     * resolve base_reference (documento §2.1) -> ['weight' => ?float, 'reps' => ?int].
     * `reps` siempre viene de lo prescrito (no hay concepto de "reps
     * efectivas de referencia" en el documento), independientemente del
     * base_reference elegido para weight.
     */
    private function resolveBaseReference(BaseReference $ref, User $client, int $exerciseId, ExerciseSessionMetric $metrics, int $sessionId): array
    {
        $prescribed = $this->resolveLastPrescribed($client->id, $exerciseId, $sessionId, $metrics->workout_template_exercise_id);
        $reps = isset($prescribed['reps']) && is_numeric($prescribed['reps']) ? (int) $prescribed['reps'] : null;

        $weight = match ($ref) {
            BaseReference::ULTIMO_PRESCRITO => isset($prescribed['carga']) && is_numeric($prescribed['carga']) ? (float) $prescribed['carga'] : null,
            BaseReference::ULTIMO_EFECTIVO => $metrics->carga_efectiva,
            BaseReference::E1RM_ESTIMADO => $metrics->e1rm_estimado,
            BaseReference::PRIMERA_SEMANA_MESOCICLO => $this->resolveFirstWeekPrescribed($client->id, $exerciseId, $sessionId),
        };

        return ['weight' => $weight, 'reps' => $reps];
    }

    /**
     * training_program_id real de la sesión evaluada, para el scope
     * `programa_especifico` — null en un workout suelto (sin
     * program_day_assignment_id), igual que resolveFirstWeekPrescribed()
     * ya trata "sin mesociclo" como "el concepto no aplica".
     */
    private function resolveTrainingProgramIdForSession(int $sessionId): ?int
    {
        $review = WorkoutSessionReview::find($sessionId);
        if (!$review || !$review->program_day_assignment_id) {
            return null;
        }

        $assignment = ProgramDayAssignment::find($review->program_day_assignment_id);

        return $assignment?->training_program_id;
    }

    /**
     * $loggedWorkoutTemplateExerciseId = exercise_session_metrics.workout_template_exercise_id
     * de la sesion actual - el slot exacto que el cliente registro de
     * verdad. Cuando esta presente se usa tal cual (evita la ambiguedad de
     * findTemplateExercise() si el mismo ejercicio aparece en mas de un
     * bloque de la plantilla); si es null (metrica antigua, de antes de
     * este fix, o ejercicio ad-hoc sin slot prescrito) cae al criterio
     * anterior como mejor esfuerzo, sin romper nada retroactivamente.
     */
    private function resolveLastPrescribed(int $clientId, int $exerciseId, int $sessionId, ?int $loggedWorkoutTemplateExerciseId = null): array
    {
        $review = WorkoutSessionReview::find($sessionId);
        if (!$review) {
            return [];
        }

        if ($review->program_day_assignment_id) {
            $assignment = ProgramDayAssignment::find($review->program_day_assignment_id);
            if (!$assignment || !$assignment->workout_template_id) {
                return [];
            }
            $wte = $loggedWorkoutTemplateExerciseId
                ? WorkoutTemplateExercise::find($loggedWorkoutTemplateExerciseId)
                : $this->findTemplateExercise($assignment->workout_template_id, $exerciseId);
            if (!$wte) {
                return [];
            }
            $base = is_array($wte->prescribed) ? $wte->prescribed : [];
            $override = ClientExerciseOverride::where('program_day_assignment_id', $assignment->id)
                ->where('client_id', $clientId)
                ->where('workout_template_exercise_id', $wte->id)
                ->first();
            $overridePrescribed = is_array($override->prescribed_override ?? null) ? $override->prescribed_override : [];

            return array_merge($base, $overridePrescribed);
        }

        if ($review->workout_template_id) {
            $wte = $loggedWorkoutTemplateExerciseId
                ? WorkoutTemplateExercise::find($loggedWorkoutTemplateExerciseId)
                : $this->findTemplateExercise($review->workout_template_id, $exerciseId);
            return $wte && is_array($wte->prescribed) ? $wte->prescribed : [];
        }

        return [];
    }

    /**
     * Nota: sigue usando findTemplateExercise() sin desambiguar (misma
     * limitacion que tenia resolveLastPrescribed() antes de este fix) - no
     * corregida aqui porque "semana 1 del mesociclo" es una sesion
     * historica distinta a la actual, no hay un exercise_session_metrics
     * de "esta" sesion que apunte al slot correcto de esa otra semana.
     * Hueco real pero periferico (BaseReference::PRIMERA_SEMANA_MESOCICLO
     * es el menos usado de los 4), documentado para si se decide arreglar.
     */
    private function resolveFirstWeekPrescribed(int $clientId, int $exerciseId, int $sessionId): ?float
    {
        $review = WorkoutSessionReview::find($sessionId);
        if (!$review || !$review->program_day_assignment_id) {
            return null; // Sin mesociclo (workout suelto) -> concepto no aplica.
        }

        $assignment = ProgramDayAssignment::find($review->program_day_assignment_id);
        if (!$assignment) {
            return null;
        }

        $week1Assignments = ProgramDayAssignment::where('training_program_id', $assignment->training_program_id)
            ->where('week_number', 1)
            ->whereNotNull('workout_template_id')
            ->get();

        foreach ($week1Assignments as $week1) {
            $wte = $this->findTemplateExercise($week1->workout_template_id, $exerciseId);
            if (!$wte) {
                continue;
            }
            $base = is_array($wte->prescribed) ? $wte->prescribed : [];
            $override = ClientExerciseOverride::where('program_day_assignment_id', $week1->id)
                ->where('client_id', $clientId)
                ->where('workout_template_exercise_id', $wte->id)
                ->first();
            $overridePrescribed = is_array($override->prescribed_override ?? null) ? $override->prescribed_override : [];
            $merged = array_merge($base, $overridePrescribed);

            return isset($merged['carga']) && is_numeric($merged['carga']) ? (float) $merged['carga'] : null;
        }

        return null;
    }

    private function findTemplateExercise(int $workoutTemplateId, int $exerciseId): ?WorkoutTemplateExercise
    {
        return WorkoutTemplateExercise::where('exercise_id', $exerciseId)
            ->whereHas('block', fn ($q) => $q->where('workout_template_id', $workoutTemplateId))
            ->first();
    }

    // ═══ Persistencia del resultado ═════════════════════════════════════

    private function finalizeNeutral(User $client, int $exerciseId, int $sessionId, ExerciseSessionMetric $metrics, ?SessionProgressionRule $rule, ActionType $type, TargetStatus $status, string $reason, bool $simulate): array
    {
        return $this->finalizeProposal($client, $exerciseId, $sessionId, $metrics, $rule, null, null, $status, $simulate, $reason, $type);
    }

    private function finalizeProposal(User $client, int $exerciseId, int $sessionId, ExerciseSessionMetric $metrics, ?SessionProgressionRule $rule, ?float $proposedWeight, ?int $proposedReps, TargetStatus $status, bool $simulate, string $reason = '', ?ActionType $type = null): array
    {
        $type = $type ?? $rule?->action?->type;

        if ($simulate) {
            return $this->result($reason ?: 'evaluado', $rule, $type, $proposedWeight, $proposedReps, $status);
        }

        $payload = [
            'client_id'   => $client->id,
            'rule_id'     => $rule?->id,
            'proposed_weight' => $proposedWeight,
            'proposed_reps'   => $proposedReps,
            'status'      => $status->value,
            'generated_at' => now(),
        ];

        if ($rule && $rule->shadow_mode) {
            // Modo sombra (documento §2.6): escribe en shadow_evaluations
            // en vez de la real, sin generar sugerencias activas ni
            // aplicar nada al plan del cliente.
            $target = ShadowEvaluation::updateOrCreate(
                ['workout_session_review_id' => $sessionId, 'exercise_id' => $exerciseId, 'client_id' => $client->id],
                $payload
            );
            $this->updateRachaMismaDireccion($metrics, $client->id, $exerciseId, $rule);

            return $this->result($reason ?: 'evaluado_sombra', $rule, $type, $proposedWeight, $proposedReps, $status, $target);
        }

        $target = NextSessionTarget::updateOrCreate(
            ['workout_session_review_id' => $sessionId, 'exercise_id' => $exerciseId, 'client_id' => $client->id],
            $payload
        );

        // Ítem 44 (Ronda 15): solo cuenta como "disparada" una acción real
        // (ni simulate ni shadow_mode, ya descartados arriba en esta misma
        // rama de finalizeProposal()) de bajada de carga o marcado al coach.
        if ($type === ActionType::BAJAR_CARGA_PCT || $type === ActionType::MARCAR_PARA_COACH) {
            $this->accionesBajadaEnSesion++;
        }

        // Panel de Excepciones del Coach (documento §3.2/§3.3) — status
        // PENDIENTE aquí solo ocurre para marcar_para_coach (estancamiento/
        // sobreentrenamiento) o para un ajuste de carga en modo sugerido
        // (mode != automatico) -- distinguir por $type, no por $reason
        // (más estable que parsear el string de motivo).
        if ($status === TargetStatus::PENDIENTE && $client->coach_id) {
            $this->createExceptionItemForPendingTarget($client, $exerciseId, $target->id, $type, $reason);
        }

        // Modo automático con propuesta numérica real y ya aplicado ->
        // puebla la siguiente sesión programada (documento §2.3).
        if ($status === TargetStatus::APLICADO && $rule !== null && !$type->isNeutral()) {
            $this->applyToNextScheduledSession($client->id, $exerciseId, $proposedWeight, $proposedReps);
        }

        $this->updateRachaMismaDireccion($metrics, $client->id, $exerciseId, $rule);

        return $this->result($reason ?: 'evaluado', $rule, $type, $proposedWeight, $proposedReps, $status, $target);
    }

    /**
     * Panel de Excepciones del Coach (documento §3.2/§3.3). Un único punto
     * para los dos únicos tipos de acción que pueden dejar un
     * next_session_target en PENDIENTE vía finalizeProposal() (sustitución
     * tiene su propio punto, ver finalizeSubstitution()). Reusa
     * NextSessionTarget.id como source_id -- estable entre reevaluaciones
     * del mismo (sesión, ejercicio) porque el write de arriba es
     * updateOrCreate sobre esa misma clave.
     */
    private function createExceptionItemForPendingTarget(User $client, int $exerciseId, int $targetId, ?ActionType $type, string $reason): void
    {
        $exerciseTitle = optional(Exercise::find($exerciseId))->title ?? 'un ejercicio';

        if ($type === ActionType::MARCAR_PARA_COACH) {
            $motivo = match ($reason) {
                'posible_sobreentrenamiento_completion_ratio_bajando' => 'posible sobreentrenamiento (completion ratio en descenso)',
                'sustitucion_bloqueada_por_readiness_bajo' => 'readiness bajo sostenido bloqueó la sustitución automática',
                'sustitucion_sin_variante_definida' => 'sin variante de sustitución definida',
                default => $reason,
            };

            (new CoachExceptionFeedService())->createOrSkip(
                coachId: (int) $client->coach_id,
                clientId: $client->id,
                category: ExceptionCategory::ESTANCAMIENTO,
                severity: ExceptionSeverity::MEDIA,
                sourceType: NextSessionTarget::class,
                sourceId: $targetId,
                title: "Estancamiento en \"{$exerciseTitle}\" requiere revisión",
                description: ucfirst($motivo) . '.'
            );

            return;
        }

        (new CoachExceptionFeedService())->createOrSkip(
            coachId: (int) $client->coach_id,
            clientId: $client->id,
            category: ExceptionCategory::SUGERENCIA_CARGA,
            severity: ExceptionSeverity::BAJA,
            sourceType: NextSessionTarget::class,
            sourceId: $targetId,
            title: "Sugerencia de ajuste de carga pendiente en \"{$exerciseTitle}\"",
            description: 'Regla en modo sugerido -- requiere aprobación del coach.'
        );
    }

    /**
     * Aplica el valor propuesto directamente sobre la PRÓXIMA sesión
     * programada de ese ejercicio (documento §2.3: "el valor se puebla
     * directamente en la siguiente sesión programada... o el mecanismo
     * equivalente ya existente para poblar sesiones") — el mecanismo
     * equivalente real en este esquema es client_exercise_overrides
     * (confirmado en la reconciliación del plan), sobre el
     * program_day_assignment futuro más próximo que contenga este
     * ejercicio dentro del programa activo del cliente.
     */
    public function applyToNextScheduledSession(int $clientId, int $exerciseId, ?float $proposedWeight, ?int $proposedReps): bool
    {
        if ($proposedWeight === null && $proposedReps === null) {
            return false;
        }

        $assignment = $this->resolveNextAssignmentForExercise($clientId, $exerciseId);
        if (!$assignment) {
            return false; // No hay próxima sesión programada con este ejercicio -> nada que poblar todavía.
        }

        $wte = $this->findTemplateExercise($assignment->workout_template_id, $exerciseId);
        if (!$wte) {
            return false;
        }

        $override = ClientExerciseOverride::firstOrNew([
            'program_day_assignment_id'   => $assignment->id,
            'client_id'                    => $clientId,
            'workout_template_exercise_id' => $wte->id,
        ]);

        $prescribed = $override->prescribed_override ?? [];
        if ($proposedWeight !== null) {
            $prescribed['carga'] = $proposedWeight;
        }
        if ($proposedReps !== null) {
            $prescribed['reps'] = $proposedReps;
        }
        $override->prescribed_override = $prescribed;
        $override->save();

        return true;
    }

    /**
     * Próximo program_day_assignment (>= hoy) del programa activo del
     * cliente que contiene este ejercicio. Reutilizado tanto por
     * applyToNextScheduledSession() como por el comando
     * progression:apply-fallbacks (necesita saber si esa sesión está
     * dentro de la ventana de 24h antes de aplicar el fallback_behavior,
     * documento §2.3).
     */
    public function resolveNextAssignmentForExercise(int $clientId, int $exerciseId): ?ProgramDayAssignment
    {
        $programIds = \App\Models\ProgramClientAssignment::where('client_id', $clientId)
            ->where('activo', true)
            ->pluck('training_program_id');

        if ($programIds->isEmpty()) {
            return null;
        }

        return ProgramDayAssignment::whereIn('training_program_id', $programIds)
            ->whereNotNull('workout_template_id')
            ->whereDate('scheduled_date', '>=', now()->toDateString())
            ->whereHas('workoutTemplate.blocks.exercises', fn ($q) => $q->where('exercise_id', $exerciseId))
            ->orderBy('scheduled_date')
            ->first();
    }

    /**
     * Poblado por el motor de reglas (comentario ya dejado en la migración
     * de Fase 1 exercise_session_metrics: "no existe todavía 'última
     * acción del motor' -> queda a 0 hasta entonces").
     *
     * Decisión de diseño propia sobre qué es "misma dirección": el signo
     * del `value` configurado en la acción de la regla ganadora (subir,
     * bajar, mantener), NO el valor absoluto propuesto — el peso
     * propuesto siempre es positivo (es un kg real), así que comparar su
     * signo no diría nada sobre si el motor está subiendo o bajando carga.
     * Esto además es derivable igual de forma consistente tanto para la
     * evaluación actual como para next_session_targets ya guardados
     * (basta con mirar su rule_id -> action), sin tener que reconstruir
     * ninguna "base" histórica que no se persiste en ningún sitio.
     *
     * Ítem 3 (Plan de Optimización, Ronda 1): esta racha mide rachas de
     * subida/bajada REAL de carga -- 'hold' agrupa tanto mantener/
     * bloquear_progresion como "sin regla ganadora" (bloqueo por dolor,
     * calibración incompleta, sin_regla_aplicable; ver directionOfRule()),
     * ninguno de los cuales es un cambio de carga real. Se salta la query
     * completa de 20 NextSessionTarget con `rule.action` eager-cargado (y
     * el guardado) cuando la dirección es 'hold' -- deja
     * racha_misma_direccion en el valor que Fase 1 ya le puso (0 en una
     * fila recién creada, ver comentario de updateTrendMetrics() en
     * SessionInterpretationService). Confirmado (grep en todo el repo)
     * que ningún otro punto del código LEE hoy racha_misma_direccion, así
     * que este skip no cambia ningún comportamiento observable.
     *
     * `marcar_para_coach`/`sustituir_ejercicio` (direction === null, ver
     * directionOfRule()) NO se incluyen en este skip a propósito -- se
     * mantienen exactamente igual que antes (siguen ejecutando la query y
     * guardando 0), para no arriesgar ningún comportamiento que dependa
     * de ellos en otro sitio.
     */
    private function updateRachaMismaDireccion(ExerciseSessionMetric $metrics, int $clientId, int $exerciseId, ?SessionProgressionRule $rule): void
    {
        $direction = $this->directionOfRule($rule);

        if ($direction === 'hold') {
            return;
        }

        $previousTargets = NextSessionTarget::where('client_id', $clientId)
            ->where('exercise_id', $exerciseId)
            ->with('rule.action')
            ->orderByDesc('generated_at')
            ->limit(20)
            ->get();

        $streak = $direction !== null ? 1 : 0;
        if ($direction !== null) {
            foreach ($previousTargets as $prev) {
                $prevDirection = $this->directionOfRule($prev->rule);
                if ($prevDirection === $direction) {
                    $streak++;
                } else {
                    break;
                }
            }
        }

        $metrics->racha_misma_direccion = $streak;
        $metrics->save();
    }

    private function directionOfRule(?SessionProgressionRule $rule): ?string
    {
        $action = $rule?->action;
        if (!$action) {
            return 'hold'; // sin regla (bloqueo por dolor, calibración, sin_regla_aplicable) o mantener/bloquear.
        }

        return match ($action->type) {
            ActionType::BAJAR_CARGA_PCT => 'down',
            ActionType::MANTENER, ActionType::BLOQUEAR_PROGRESION => 'hold',
            ActionType::AJUSTAR_CARGA_PCT, ActionType::AJUSTAR_CARGA_ABSOLUTA, ActionType::AJUSTAR_REPS => match (true) {
                $action->value === null || (float) $action->value == 0.0 => 'hold',
                (float) $action->value > 0 => 'up',
                default => 'down',
            },
            default => null, // marcar_para_coach / sustituir_ejercicio: no representan una dirección de carga real.
        };
    }

    private function result(string $reason, ?SessionProgressionRule $rule, ?ActionType $type, ?float $proposedWeight, ?int $proposedReps, ?TargetStatus $status, $target = null): array
    {
        return [
            'reason'          => $reason,
            'rule_id'         => $rule?->id,
            'action_type'     => $type?->value,
            'proposed_weight' => $proposedWeight,
            'proposed_reps'   => $proposedReps,
            'status'          => $status?->value,
            'target'          => $target,
        ];
    }
}
