<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AdaptiveWeekPlan;
use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
use App\Models\User;
use App\Notifications\CommonNotification;
use App\Services\AdaptiveWeekPlanner;
use App\Services\CoachExceptionFeedService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Motor de Auto-Regulación de Carga — Fase 4, modo vida real (documento
 * §4.2). El documento original no especifica endpoints para esta sub-fase
 * (a diferencia de §4.1, que sí los lista) -- el plan de ejecución solo
 * pidió expresamente `POST /api/adaptive-week-plans/{id}/approve`. Se añade
 * aquí también `POST /api/adaptive-week-plans/generate` (adición propia,
 * no pedida literalmente) porque sin ella `AdaptiveWeekPlanner` sería
 * inalcanzable desde la app -- solo invocable por tinker -- y la feature
 * quedaría inutilizable end-to-end. Mismo patrón de autorización
 * cliente-o-coach que `SessionInterpretationController::exerciseMetrics()`.
 */
class AdaptiveWeekPlanController extends Controller
{
    /**
     * POST /api/adaptive-week-plans/generate
     *
     * Body: { client_id, week_start (Y-m-d), sessions_available, priorizacion }
     *
     * Solo el coach del cliente puede pedir una propuesta. Siempre crea
     * status=propuesto (AdaptiveWeekPlanner nunca hace otra cosa).
     *
     * mesocycle_extension YA NO es un input del coach (antes era
     * `required|boolean` aquí) -- una semana de "modo vida real" nunca
     * extiende el fin del mesociclo, cuenta como una semana normal (regla
     * de negocio confirmada junto con el cierre automático de mesociclo,
     * ver MesocycleClosureService/ProgramClientAssignment.fecha_fin, que se
     * calcula una vez al asignar y no se recalcula al vuelo). Se sigue
     * guardando en adaptive_week_plans.mesocycle_extension (columna ya
     * existente) pero AdaptiveWeekPlanner la fija siempre a false.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'client_id'            => 'required|exists:users,id',
            'week_start'           => 'required|date',
            'sessions_available'   => 'required|integer|min:0|max:14',
            'priorizacion'         => 'required|in:mantener_ejercicios_principales,mantener_grupo_muscular_prioritario,mantener_distribucion_semanal_completa',
        ]);

        $authUser = auth('sanctum')->user();
        $client = User::find($request->client_id);

        if ($client->coach_id !== $authUser->id) {
            return json_custom_response(['message' => 'No autorizado.'], 403);
        }

        try {
            $plan = (new AdaptiveWeekPlanner())->generateProposal(
                $client,
                Carbon::parse($request->week_start),
                (int) $request->sessions_available,
                $request->priorizacion
            );
        } catch (\RuntimeException $e) {
            // Cliente free (gate paid-tier, Fase 0 §0.3) u otro error de dominio.
            return json_custom_response(['message' => $e->getMessage()], 422);
        }

        return json_custom_response(['data' => $plan]);
    }

    /**
     * POST /api/adaptive-week-plans/{id}/approve
     *
     * Único camino para que status pase de propuesto a aprobado. Solo el
     * coach del cliente dueño del plan puede aprobar. Aprobar YA aplica
     * (2026-08-12, cierra la transición aprobado->aplicado documentada como
     * pendiente) -- no existe un tercer estado, mismo criterio que aprobar
     * una sugerencia de carga en SessionProgressionRuleController::doApprove().
     */
    public function approve(Request $request, $id)
    {
        $plan = AdaptiveWeekPlan::find($id);

        if (!$plan) {
            return json_custom_response(['message' => 'Plan no encontrado.'], 404);
        }

        $authUser = auth('sanctum')->user();
        $client = User::find($plan->client_id);

        if (!$client || $client->coach_id !== $authUser->id) {
            return json_custom_response(['message' => 'No autorizado.'], 403);
        }

        return $this->doApprove($plan, (int) $authUser->id);
    }

    /**
     * POST /admin/adaptive-week-plans/{id}/approve — espejo admin de
     * approve() (Plan_Cierre_Motor_UI.md, Fase 1): sin comparar
     * client->coach_id contra auth()->id(), staff actúa en nombre del
     * coach -- mismo criterio ya usado en
     * SessionProgressionRuleController::adminApprove() y
     * CoachExceptionItemController::adminResolve(). Misma lógica real
     * (doApprove()), no duplicada.
     */
    public function adminApprove(Request $request, $id)
    {
        $plan = AdaptiveWeekPlan::find($id);

        if (!$plan) {
            return json_custom_response(['message' => 'Plan no encontrado.'], 404);
        }

        $authUser = $request->user();

        return $this->doApprove($plan, (int) $authUser->id);
    }

    private function doApprove(AdaptiveWeekPlan $plan, int $resolvedBy)
    {
        if ($plan->status !== 'propuesto') {
            return json_custom_response(['message' => "El plan no está en estado 'propuesto' (actual: {$plan->status})."], 422);
        }

        $plan->status = 'aprobado';
        $plan->save();

        (new AdaptiveWeekPlanner())->applyPlan($plan);

        $client = User::find($plan->client_id);
        if ($client) {
            $client->notify(new CommonNotification('adaptive_week_applied', [
                'id'      => $plan->id,
                'type'    => 'adaptive_week_applied',
                'subject' => 'Tu semana fue ajustada',
                'message' => "Tu entrenador ajustó tu semana del {$plan->original_week_start->toDateString()} — revisa tu calendario.",
            ]));
        }

        // Panel de Excepciones del Coach (documento §3.5): resuelve el
        // ítem del panel apuntando a este plan -- mismo criterio que
        // sugerencias de carga, ver SessionProgressionRuleController.
        (new CoachExceptionFeedService())->resolveBySource(AdaptiveWeekPlan::class, $plan->id, $resolvedBy);

        return json_custom_response(['data' => $plan]);
    }

    /**
     * POST /api/adaptive-week-plans/{id}/reject (2026-08-12)
     *
     * Hueco real encontrado tras cerrar aprobado->aplicado: no existía
     * ningún camino para RECHAZAR un plan -- descartar el ítem del Panel de
     * Excepciones dejaba el AdaptiveWeekPlan huérfano en 'propuesto' para
     * siempre, sin avisar al cliente. Más urgente ahora que el cliente
     * también puede disparar la propuesta (requestFromClient) -- necesita
     * saber si su solicitud fue denegada, no solo silencio.
     */
    public function reject(Request $request, $id)
    {
        $plan = AdaptiveWeekPlan::find($id);

        if (!$plan) {
            return json_custom_response(['message' => 'Plan no encontrado.'], 404);
        }

        $authUser = auth('sanctum')->user();
        $client = User::find($plan->client_id);

        if (!$client || $client->coach_id !== $authUser->id) {
            return json_custom_response(['message' => 'No autorizado.'], 403);
        }

        return $this->doReject($plan, (int) $authUser->id, $request->input('motivo'));
    }

    /** POST /admin/adaptive-week-plans/{id}/reject — espejo admin de reject(). */
    public function adminReject(Request $request, $id)
    {
        $plan = AdaptiveWeekPlan::find($id);

        if (!$plan) {
            return json_custom_response(['message' => 'Plan no encontrado.'], 404);
        }

        $authUser = $request->user();

        return $this->doReject($plan, (int) $authUser->id, $request->input('motivo'));
    }

    private function doReject(AdaptiveWeekPlan $plan, int $resolvedBy, ?string $motivo)
    {
        if ($plan->status !== 'propuesto') {
            return json_custom_response(['message' => "El plan no está en estado 'propuesto' (actual: {$plan->status})."], 422);
        }

        $plan->status = 'rechazado';
        $plan->save();

        $client = User::find($plan->client_id);
        if ($client) {
            $client->notify(new CommonNotification('adaptive_week_rejected', [
                'id'      => $plan->id,
                'type'    => 'adaptive_week_rejected',
                'subject' => 'Tu solicitud no fue aprobada',
                'message' => "Tu entrenador no aprobó el ajuste de tu semana del {$plan->original_week_start->toDateString()}"
                    . ($motivo ? " — motivo: {$motivo}" : '. Habla con él/ella directamente si tienes dudas.'),
            ]));
        }

        (new CoachExceptionFeedService())->resolveBySource(AdaptiveWeekPlan::class, $plan->id, $resolvedBy);

        return json_custom_response(['data' => $plan]);
    }

    /**
     * POST /api/adaptive-week-plans/request-unavailable (cliente, 2026-08-12)
     *
     * Trigger del cliente: selecciona desde su calendario qué días de esa
     * semana no puede entrenar. Sigue naciendo `propuesto` -- pasa por el
     * mismo Panel de Excepciones y requiere aprobación del coach, igual que
     * una propuesta generada por el coach (nunca se salta "siempre
     * sugerido").
     */
    public function requestFromClient(Request $request)
    {
        $request->validate([
            'week_start'                 => 'required|date',
            'unavailable_assignment_ids' => 'required|array|min:1',
            'unavailable_assignment_ids.*' => 'integer|exists:program_day_assignments,id',
        ]);

        $client = auth('sanctum')->user();

        $activeProgramIds = ProgramClientAssignment::where('client_id', $client->id)
            ->where('activo', true)
            ->pluck('training_program_id');

        $validIds = ProgramDayAssignment::whereIn('id', $request->input('unavailable_assignment_ids'))
            ->whereIn('training_program_id', $activeProgramIds)
            ->whereNotNull('workout_template_id')
            ->pluck('id');

        if ($validIds->count() !== count($request->input('unavailable_assignment_ids'))) {
            return json_custom_response(['message' => 'Alguno de los días seleccionados no pertenece a uno de tus programas activos o no tiene entrenamiento real.'], 422);
        }

        try {
            $plan = (new AdaptiveWeekPlanner())->generateFromClientSelection(
                $client,
                Carbon::parse($request->week_start),
                $validIds->all()
            );
        } catch (\RuntimeException $e) {
            return json_custom_response(['message' => $e->getMessage()], 422);
        }

        return json_custom_response(['data' => $plan]);
    }
}
