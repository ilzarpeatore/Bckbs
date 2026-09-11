<?php

namespace App\Http\Controllers\API;

use App\Enums\ActionTaken;
use App\Enums\ActionType;
use App\Enums\BaseReference;
use App\Enums\ConditionOperator;
use App\Enums\ConditionVariable;
use App\Enums\FallbackBehavior;
use App\Enums\RoundingMode;
use App\Enums\RuleMode;
use App\Enums\ScopeType;
use App\Enums\TargetStatus;
use App\Http\Controllers\Controller;
use App\Models\ExerciseSessionMetric;
use App\Models\NextSessionTarget;
use App\Models\OverrideLog;
use App\Models\SessionProgressionRule;
use App\Models\ShadowEvaluation;
use App\Models\User;
use App\Services\CoachExceptionFeedService;
use App\Services\SessionProgressionRuleEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;

/**
 * Motor de Auto-Regulación de Carga — Fase 2 (documento §2.5). Endpoints
 * de coach para configurar reglas, panel de excepciones (sugerencias
 * pendientes) y auditoría.
 *
 * IMPORTANTE (verificado con datos reales antes de escribir esto): en
 * este esquema "coach" NO es un Spatie role — hasRole('admin') solo lo
 * tienen los usuarios de panel (System Admin/Admin, user_type=admin, para
 * el /admin real), mientras que los coaches reales de la app tienen
 * user_type='coach' y CERO roles Spatie asignados. El resto de
 * controladores coach-owned del proyecto (WorkoutTemplateController,
 * ClientTagController, SessionInterpretationController::exerciseMetrics,
 * etc.) nunca comprueban rol, solo comparan coach_id === auth()->id() —
 * se replica exactamente ese mismo criterio aquí, sin hasRole().
 *
 * AÑADIDO (Plan_Cierre_Motor_UI.md, Fase 1): adminApprove()/adminEdit()/
 * adminReject() son espejos admin de approve()/edit()/reject() -- mismo
 * criterio ya usado en CoachExceptionItemController::adminResolve()/
 * adminDismiss() (sin comparar coach_id contra auth()->id(), staff actúa
 * en nombre del coach). La lógica real vive en doApprove()/doEdit()/
 * doReject() (privados), compartida entre ambas superficies -- self-service
 * y admin llaman a la misma pieza, la única diferencia es si se pasa por
 * requireCoach() antes.
 */
class SessionProgressionRuleController extends Controller
{
    private function requireCoach(Request $request, ?int $ownerCoachId = null): User
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'No autenticado.');
        }
        if ($ownerCoachId !== null && (int) $user->id !== $ownerCoachId) {
            abort(403, 'No autorizado.');
        }

        return $user;
    }

    private function validateRulePayload(Request $request): array
    {
        return $request->validate([
            'name'                => 'required|string|max:150',
            'scope_type'           => ['required', new Enum(ScopeType::class)],
            'scope_id'             => 'nullable|integer',
            'priority'             => 'nullable|integer',
            'active'               => 'nullable|boolean',
            'mode'                 => ['required', new Enum(RuleMode::class)],
            'fallback_behavior'    => ['required', new Enum(FallbackBehavior::class)],
            'shadow_mode'          => 'nullable|boolean',
            'conditions'                        => 'nullable|array',
            'conditions.*.variable'             => ['required', new Enum(ConditionVariable::class)],
            'conditions.*.operator'             => ['required', new Enum(ConditionOperator::class)],
            'conditions.*.threshold_value'      => 'nullable|numeric',
            'conditions.*.threshold_min'        => 'nullable|numeric',
            'conditions.*.threshold_max'        => 'nullable|numeric',
            'conditions.*.ventana_sesiones'     => 'nullable|integer|min:1',
            'conditions.*.logic_group'          => 'nullable|integer|min:0',
            // Plan de Optimización, Ronda 14 ítem 43: "N de M condiciones".
            'conditions.*.min_condiciones_requeridas' => 'nullable|integer|min:1',
            'action'                    => 'nullable|array',
            'action.type'                => ['required_with:action', new Enum(ActionType::class)],
            'action.value'               => 'nullable|numeric',
            'action.rounding'            => ['required_with:action', new Enum(RoundingMode::class)],
            'action.base_reference'      => ['required_with:action', new Enum(BaseReference::class)],
        ]);
    }

    // ═══ CRUD de reglas (documento §2.5) ════════════════════════════════

    /** GET /api/coaches/{id}/rules */
    public function index(Request $request, $coachId)
    {
        $this->requireCoach($request, (int) $coachId);

        return $this->doIndex((int) $coachId);
    }

    /**
     * GET /admin/session-progression/rules?coach_id= — espejo admin de
     * index(), mismo criterio que adminApprove() (Plan_Cierre_Motor_UI.md,
     * "Crear/editar reglas de progresión nuevas desde el admin"): sin
     * comparar coach_id contra auth()->id(), staff elige de qué coach
     * quiere ver/crear reglas (mismo patrón de selector ya usado en
     * CoachExceptionsView.tsx / CoachExceptionItemController::adminIndex()).
     */
    public function adminIndex(Request $request)
    {
        $request->validate(['coach_id' => 'required|exists:users,id']);

        return $this->doIndex((int) $request->coach_id);
    }

    private function doIndex(int $coachId)
    {
        $rules = SessionProgressionRule::where('coach_id', $coachId)
            ->with(['conditions', 'action'])
            ->orderByDesc('created_at')
            ->get();

        return json_custom_response(['data' => $rules]);
    }

    /** POST /api/coaches/{id}/rules */
    public function store(Request $request, $coachId)
    {
        $coach = $this->requireCoach($request, (int) $coachId);
        $data = $this->validateRulePayload($request);

        return $this->doStore($coach->id, $data);
    }

    /**
     * POST /admin/session-progression/rules — espejo admin de store().
     * `coach_id` va en el body (no hay coach autenticado, staff decide de
     * qué coach es la regla nueva).
     */
    public function adminStore(Request $request)
    {
        $request->validate(['coach_id' => 'required|exists:users,id']);
        $data = $this->validateRulePayload($request);

        return $this->doStore((int) $request->coach_id, $data);
    }

    private function doStore(int $coachId, array $data)
    {
        $rule = SessionProgressionRule::create([
            'coach_id'           => $coachId,
            'name'                => $data['name'],
            'scope_type'          => $data['scope_type'],
            'scope_id'            => $data['scope_id'] ?? null,
            'priority'            => $data['priority'] ?? 0,
            'active'              => $data['active'] ?? true,
            'mode'                => $data['mode'],
            'fallback_behavior'   => $data['fallback_behavior'],
            'shadow_mode'         => $data['shadow_mode'] ?? false,
        ]);

        foreach ($data['conditions'] ?? [] as $condition) {
            $rule->conditions()->create($condition);
        }
        if (isset($data['action'])) {
            $rule->action()->create($data['action']);
        }

        return json_custom_response(['data' => $rule->load(['conditions', 'action'])]);
    }

    /** PUT /api/rules/{id} */
    public function update(Request $request, $ruleId)
    {
        $rule = SessionProgressionRule::findOrFail($ruleId);
        $this->requireCoach($request, (int) $rule->coach_id);
        $data = $this->validateRulePayload($request);

        return $this->doUpdate($rule, $data, $request->has('conditions'), $request->has('action'));
    }

    /**
     * PUT /admin/session-progression/rules/{id} — espejo admin de update().
     */
    public function adminUpdate(Request $request, $ruleId)
    {
        $rule = SessionProgressionRule::findOrFail($ruleId);
        $data = $this->validateRulePayload($request);

        return $this->doUpdate($rule, $data, $request->has('conditions'), $request->has('action'));
    }

    private function doUpdate(SessionProgressionRule $rule, array $data, bool $hasConditions, bool $hasAction)
    {
        $rule->update([
            'name'                => $data['name'],
            'scope_type'          => $data['scope_type'],
            'scope_id'            => $data['scope_id'] ?? null,
            'priority'            => $data['priority'] ?? $rule->priority,
            'active'              => array_key_exists('active', $data) ? $data['active'] : $rule->active,
            'mode'                => $data['mode'],
            'fallback_behavior'   => $data['fallback_behavior'],
            'shadow_mode'         => array_key_exists('shadow_mode', $data) ? $data['shadow_mode'] : $rule->shadow_mode,
        ]);

        if ($hasConditions) {
            $rule->conditions()->delete();
            foreach ($data['conditions'] ?? [] as $condition) {
                $rule->conditions()->create($condition);
            }
        }

        if ($hasAction) {
            $rule->action()->delete();
            if (isset($data['action'])) {
                $rule->action()->create($data['action']);
            }
        }

        return json_custom_response(['data' => $rule->fresh(['conditions', 'action'])]);
    }

    /** DELETE /api/rules/{id} */
    public function destroy(Request $request, $ruleId)
    {
        $rule = SessionProgressionRule::findOrFail($ruleId);
        $this->requireCoach($request, (int) $rule->coach_id);

        return $this->doDestroy($rule);
    }

    /** DELETE /admin/session-progression/rules/{id} — espejo admin de destroy(). */
    public function adminDestroy(Request $request, $ruleId)
    {
        $rule = SessionProgressionRule::findOrFail($ruleId);

        return $this->doDestroy($rule);
    }

    private function doDestroy(SessionProgressionRule $rule)
    {
        $rule->delete();

        return json_message_response('Regla eliminada.');
    }

    // ═══ Simulación (documento §2.6) ═════════════════════════════════════

    /** POST /api/rules/{id}/simulate */
    public function simulate(Request $request, $ruleId, SessionProgressionRuleEngine $engine)
    {
        $rule = SessionProgressionRule::with(['conditions', 'action'])->findOrFail($ruleId);
        $this->requireCoach($request, (int) $rule->coach_id);

        return $this->doSimulate($request, $rule, $engine);
    }

    /** POST /admin/session-progression/rules/{id}/simulate — espejo admin de simulate(). */
    public function adminSimulate(Request $request, $ruleId, SessionProgressionRuleEngine $engine)
    {
        $rule = SessionProgressionRule::with(['conditions', 'action'])->findOrFail($ruleId);

        return $this->doSimulate($request, $rule, $engine);
    }

    private function doSimulate(Request $request, SessionProgressionRule $rule, SessionProgressionRuleEngine $engine)
    {
        $request->validate([
            'client_id'    => 'required|integer|exists:users,id',
            'exercise_id'  => 'nullable|integer|exists:exercises,id',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date',
        ]);

        $exerciseId = $request->integer('exercise_id')
            ?: ($rule->scope_type === ScopeType::EJERCICIO_ESPECIFICO ? (int) $rule->scope_id : null);

        if (!$exerciseId) {
            return json_custom_response(['message' => 'exercise_id es obligatorio para simular una regla que no es de scope ejercicio_específico.'], 422);
        }

        $countBefore = NextSessionTarget::count() + ShadowEvaluation::count();

        $query = ExerciseSessionMetric::where('client_id', $request->integer('client_id'))
            ->where('exercise_id', $exerciseId);
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        $rows = $query->orderBy('created_at')->get();

        $results = $rows->map(function (ExerciseSessionMetric $metrics) use ($rule, $engine) {
            $evaluation = $engine->simulateRule($rule, $metrics);
            $evaluation['session_id'] = $metrics->workout_session_review_id;
            $evaluation['evaluated_at'] = $metrics->created_at;

            return $evaluation;
        })->values();

        $countAfter = NextSessionTarget::count() + ShadowEvaluation::count();

        return json_custom_response([
            'data'  => $results,
            'meta'  => [
                'dry_run'                        => true,
                'sessions_evaluated'              => $rows->count(),
                'next_session_targets_untouched'  => $countBefore === $countAfter,
            ],
        ]);
    }

    /** GET /api/rules/{id}/shadow-evaluations — visible solo para el coach (documento §2.6). */
    public function shadowEvaluations(Request $request, $ruleId)
    {
        $rule = SessionProgressionRule::findOrFail($ruleId);
        $this->requireCoach($request, (int) $rule->coach_id);

        return $this->doShadowEvaluations((int) $ruleId);
    }

    /** GET /admin/session-progression/rules/{id}/shadow-evaluations — espejo admin. */
    public function adminShadowEvaluations(Request $request, $ruleId)
    {
        SessionProgressionRule::findOrFail($ruleId);

        return $this->doShadowEvaluations((int) $ruleId);
    }

    private function doShadowEvaluations(int $ruleId)
    {
        $rows = ShadowEvaluation::where('rule_id', $ruleId)
            ->orderByDesc('generated_at')
            ->limit(200)
            ->get();

        return json_custom_response(['data' => $rows]);
    }

    // ═══ Panel de excepciones (documento §2.3, §2.5) ═════════════════════

    /** GET /api/clients/{id}/pending-suggestions */
    public function pendingSuggestions(Request $request, $clientId)
    {
        $client = User::findOrFail($clientId);
        $this->requireCoach($request, (int) $client->coach_id);

        // Un cliente free nunca debe aparecer aquí (documento §0.3/§2.7).
        if (!Gate::forUser($client)->allows('paid-tier')) {
            return json_custom_response(['data' => []]);
        }

        $targets = NextSessionTarget::where('client_id', $clientId)
            ->where('status', TargetStatus::PENDIENTE->value)
            ->with(['exercise', 'rule.action'])
            ->orderBy('generated_at')
            ->get();

        return json_custom_response(['data' => $targets]);
    }

    /** POST /api/suggestions/{id}/approve */
    public function approve(Request $request, $suggestionId)
    {
        $target = NextSessionTarget::with('rule')->findOrFail($suggestionId);
        $this->requireCoach($request, (int) optional($target->rule)->coach_id);

        return $this->doApprove($target, (int) $request->user()->id);
    }

    /**
     * POST /admin/session-progression/suggestions/{id}/approve — espejo
     * admin de approve(), mismo criterio ya usado en CoachExceptionItemController
     * (adminResolve/adminDismiss): sin comparar coach_id contra auth()->id(),
     * staff actúa en nombre del coach. Misma lógica real (doApprove()), no
     * duplicada.
     */
    public function adminApprove(Request $request, $suggestionId)
    {
        $target = NextSessionTarget::with('rule')->findOrFail($suggestionId);

        return $this->doApprove($target, (int) $request->user()->id);
    }

    private function doApprove(NextSessionTarget $target, int $resolvedBy)
    {
        if ($target->status !== TargetStatus::PENDIENTE) {
            return json_custom_response(['message' => 'Esta sugerencia ya fue resuelta.'], 422);
        }

        $this->applyResolution($target, $target->proposed_weight, $target->proposed_reps);
        $target->status = TargetStatus::APLICADO;
        $target->resolved_at = now();
        $target->resolved_by = $resolvedBy;
        $target->save();

        $this->logOverride($target, $target->proposed_weight ?? $target->proposed_reps ?? 0, $target->proposed_weight ?? $target->proposed_reps ?? 0, ActionTaken::ACCEPTED, null);
        $this->resolveExceptionItem($target, $resolvedBy);

        return json_custom_response(['data' => $target->fresh(['exercise', 'rule.action'])]);
    }

    /** POST /api/suggestions/{id}/edit */
    public function edit(Request $request, $suggestionId)
    {
        $data = $request->validate([
            'proposed_weight' => 'nullable|numeric',
            'proposed_reps'   => 'nullable|integer',
            'motivo'          => 'nullable|string',
        ]);

        $target = NextSessionTarget::with('rule')->findOrFail($suggestionId);
        $this->requireCoach($request, (int) optional($target->rule)->coach_id);

        return $this->doEdit($target, (int) $request->user()->id, $request->has('proposed_weight'), $data['proposed_weight'] ?? null, $request->has('proposed_reps'), $data['proposed_reps'] ?? null, $data['motivo'] ?? null);
    }

    /**
     * POST /admin/session-progression/suggestions/{id}/edit — espejo admin
     * de edit(), mismo criterio que adminApprove() arriba.
     */
    public function adminEdit(Request $request, $suggestionId)
    {
        $data = $request->validate([
            'proposed_weight' => 'nullable|numeric',
            'proposed_reps'   => 'nullable|integer',
            'motivo'          => 'nullable|string',
        ]);

        $target = NextSessionTarget::with('rule')->findOrFail($suggestionId);

        return $this->doEdit($target, (int) $request->user()->id, $request->has('proposed_weight'), $data['proposed_weight'] ?? null, $request->has('proposed_reps'), $data['proposed_reps'] ?? null, $data['motivo'] ?? null);
    }

    private function doEdit(NextSessionTarget $target, int $resolvedBy, bool $hasWeight, $newWeightInput, bool $hasReps, $newRepsInput, ?string $motivo)
    {
        if ($target->status !== TargetStatus::PENDIENTE) {
            return json_custom_response(['message' => 'Esta sugerencia ya fue resuelta.'], 422);
        }

        $suggestedValue = $target->proposed_weight ?? $target->proposed_reps ?? 0;

        $newWeight = $hasWeight ? $newWeightInput : $target->proposed_weight;
        $newReps = $hasReps ? $newRepsInput : $target->proposed_reps;

        $this->applyResolution($target, $newWeight, $newReps);

        $target->proposed_weight = $newWeight;
        $target->proposed_reps = $newReps;
        $target->status = TargetStatus::APLICADO;
        $target->resolved_at = now();
        $target->resolved_by = $resolvedBy;
        $target->save();

        $appliedValue = $newWeight ?? $newReps ?? 0;
        $this->logOverride($target, $suggestedValue, $appliedValue, ActionTaken::EDITED, $motivo);
        $this->resolveExceptionItem($target, $resolvedBy);

        return json_custom_response(['data' => $target->fresh(['exercise', 'rule.action'])]);
    }

    /** POST /api/suggestions/{id}/reject */
    public function reject(Request $request, $suggestionId)
    {
        $data = $request->validate(['motivo' => 'nullable|string']);

        $target = NextSessionTarget::with('rule')->findOrFail($suggestionId);
        $this->requireCoach($request, (int) optional($target->rule)->coach_id);

        return $this->doReject($target, (int) $request->user()->id, $data['motivo'] ?? null);
    }

    /**
     * POST /admin/session-progression/suggestions/{id}/reject — espejo
     * admin de reject(), mismo criterio que adminApprove() arriba.
     */
    public function adminReject(Request $request, $suggestionId)
    {
        $data = $request->validate(['motivo' => 'nullable|string']);

        $target = NextSessionTarget::with('rule')->findOrFail($suggestionId);

        return $this->doReject($target, (int) $request->user()->id, $data['motivo'] ?? null);
    }

    private function doReject(NextSessionTarget $target, int $resolvedBy, ?string $motivo)
    {
        if ($target->status !== TargetStatus::PENDIENTE) {
            return json_custom_response(['message' => 'Esta sugerencia ya fue resuelta.'], 422);
        }

        $target->status = TargetStatus::RECHAZADO;
        $target->resolved_at = now();
        $target->resolved_by = $resolvedBy;
        $target->save();

        $suggestedValue = $target->proposed_weight ?? $target->proposed_reps ?? 0;
        $this->logOverride($target, $suggestedValue, $suggestedValue, ActionTaken::REJECTED, $motivo);
        $this->resolveExceptionItem($target, $resolvedBy);

        return json_custom_response(['data' => $target->fresh(['exercise', 'rule.action'])]);
    }

    /**
     * Panel de Excepciones del Coach (documento §3.3): aprobar/editar/
     * rechazar una sugerencia desde este endpoint ya existente resuelve
     * automáticamente el ítem del panel apuntando a este mismo
     * NextSessionTarget -- no debe quedar huérfano tras la acción real del
     * coach (documento, criterio de aceptación §7).
     */
    private function resolveExceptionItem(NextSessionTarget $target, int $resolvedBy): void
    {
        (new CoachExceptionFeedService())->resolveBySource(NextSessionTarget::class, $target->id, $resolvedBy);
    }

    private function applyResolution(NextSessionTarget $target, ?float $weight, ?int $reps): void
    {
        if ($weight === null && $reps === null) {
            return; // acciones neutras (marcar_para_coach, etc.) no tocan el plan del cliente.
        }

        app(SessionProgressionRuleEngine::class)->applyToNextScheduledSession(
            (int) $target->client_id, (int) $target->exercise_id, $weight, $reps
        );
    }

    private function logOverride(NextSessionTarget $target, $suggestedValue, $appliedValue, ActionTaken $action, ?string $motivo): void
    {
        OverrideLog::create([
            'next_session_target_id' => $target->id,
            'rule_id'                 => $target->rule_id,
            'client_id'               => $target->client_id,
            'exercise_id'             => $target->exercise_id,
            'suggested_value'         => $suggestedValue,
            'applied_value'           => $appliedValue,
            'action_taken'            => $action->value,
            'motivo'                  => $motivo,
        ]);
    }

    // ═══ Auditoría (documento §2.5) ══════════════════════════════════════

    /** GET /api/exercises/{id}/progression-history?client_id= */
    public function progressionHistory(Request $request, $exerciseId)
    {
        $request->validate(['client_id' => 'required|integer|exists:users,id']);

        $authUser = $request->user();
        $client = User::findOrFail($request->integer('client_id'));
        $isSelf = $authUser && (int) $authUser->id === $client->id;
        $isCoach = $authUser && (int) $client->coach_id === (int) $authUser->id;

        if (!$isSelf && !$isCoach) {
            return json_custom_response(['message' => 'No autorizado.'], 403);
        }

        $metrics = ExerciseSessionMetric::where('client_id', $client->id)
            ->where('exercise_id', $exerciseId)
            ->orderBy('created_at')
            ->limit(200)
            ->get();

        $targets = NextSessionTarget::where('client_id', $client->id)
            ->where('exercise_id', $exerciseId)
            ->with('rule.action')
            ->orderBy('generated_at')
            ->limit(200)
            ->get();

        return json_custom_response([
            'data' => [
                'metrics' => $metrics,
                'targets' => $targets,
            ],
        ]);
    }

    /** GET /api/rules/{id}/audit — % aceptación/edición/rechazo. */
    public function audit(Request $request, $ruleId)
    {
        $rule = SessionProgressionRule::findOrFail($ruleId);
        $this->requireCoach($request, (int) $rule->coach_id);

        return $this->doAudit($rule);
    }

    /** GET /admin/session-progression/rules/{id}/audit — espejo admin. */
    public function adminAudit(Request $request, $ruleId)
    {
        $rule = SessionProgressionRule::findOrFail($ruleId);

        return $this->doAudit($rule);
    }

    private function doAudit(SessionProgressionRule $rule)
    {
        $logs = OverrideLog::where('rule_id', $rule->id)->get();
        $total = $logs->count();

        $counts = [
            'accepted' => $logs->where('action_taken', ActionTaken::ACCEPTED)->count(),
            'edited'   => $logs->where('action_taken', ActionTaken::EDITED)->count(),
            'rejected' => $logs->where('action_taken', ActionTaken::REJECTED)->count(),
        ];

        $percentages = $total > 0 ? array_map(fn ($c) => round(($c / $total) * 100, 1), $counts) : ['accepted' => 0, 'edited' => 0, 'rejected' => 0];

        return json_custom_response([
            'data' => [
                'rule_id'      => $rule->id,
                'total_logs'    => $total,
                'counts'        => $counts,
                'percentages'   => $percentages,
                'targets_generated' => NextSessionTarget::where('rule_id', $rule->id)->count(),
            ],
        ]);
    }
}
