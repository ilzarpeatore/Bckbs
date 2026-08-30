<?php

namespace App\Http\Controllers\API;

use App\Enums\ExceptionStatus;
use App\Http\Controllers\Controller;
use App\Models\CoachExceptionItem;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Panel de Excepciones del Coach (documento §4). Dos superficies:
 *
 * - Self-service (`/api/coaches/{id}/exceptions`, `/api/exceptions/{id}/
 *   resolve|dismiss`) -- tal como pide el documento, mismo patrón de
 *   autorización que SessionProgressionRuleController (comparar {id}/
 *   coach_id del ítem contra auth()->id(), sin prefijo /admin, no existe
 *   rol coach separado de admin en este esquema).
 * - Admin (`/admin/coach-exceptions*`) -- AÑADIDO, no pedido literalmente
 *   por el documento. Investigado antes de construir: el panel Next.js/
 *   shadcn (en realidad Vite+React Router, ver reconciliación en
 *   TAREAS.md) es la única superficie real donde un coach hoy ve datos de
 *   sus clientes -- ninguna vista de HabitsView/SessionDetailView/etc.
 *   llama nunca a `/api/coaches/{id}/...` directamente, todas usan rutas
 *   `/admin/*` con selector de cliente (staff elige con quién trabajar).
 *   Sin este espejo admin, el self-service quedaría inalcanzable desde la
 *   única UI real que existe hoy -- mismo criterio que
 *   AdaptiveWeekPlanController::generate() (añadido para que el motor no
 *   quedara inalcanzable). Selector de coach en vez de coach_id fijo,
 *   mismo patrón que el selector de cliente ya usado en HabitsView.
 */
class CoachExceptionItemController extends Controller
{
    /** GET /api/coaches/{id}/exceptions */
    public function index(Request $request, $coachId)
    {
        $authUser = $request->user();
        if (!$authUser || (int) $authUser->id !== (int) $coachId) {
            return json_custom_response(['message' => 'No autorizado.'], 403);
        }

        return json_custom_response(['data' => $this->buildQuery($request, (int) $coachId)->get()]);
    }

    /** POST /api/exceptions/{id}/resolve */
    public function resolve(Request $request, $id)
    {
        $item = CoachExceptionItem::findOrFail($id);
        $authUser = $request->user();

        if (!$authUser || (int) $authUser->id !== (int) $item->coach_id) {
            return json_custom_response(['message' => 'No autorizado.'], 403);
        }

        return $this->applyResolution($item, ExceptionStatus::RESUELTA, $authUser->id);
    }

    /** POST /api/exceptions/{id}/dismiss */
    public function dismiss(Request $request, $id)
    {
        $item = CoachExceptionItem::findOrFail($id);
        $authUser = $request->user();

        if (!$authUser || (int) $authUser->id !== (int) $item->coach_id) {
            return json_custom_response(['message' => 'No autorizado.'], 403);
        }

        return $this->applyResolution($item, ExceptionStatus::DESCARTADA, $authUser->id);
    }

    /**
     * GET /admin/coach-exceptions?coach_id=&client_id=
     *
     * AÑADIDO (Plan_Cierre_Motor_UI.md, Fase 2): coach_id ahora es opcional.
     * - Sin coach_id: lista de TODOS los coaches (tarjeta del dashboard
     *   general, admin/src/views/dashboard/index.tsx).
     * - Con client_id: filtra por cliente sin importar el coach (tarjeta de
     *   UserDetailView.tsx, ya se conoce el cliente por la URL).
     * Ambos parámetros son compatibles entre sí (se pueden combinar) y con
     * el resto de filtros existentes (category/severity/status). El uso ya
     * existente de CoachExceptionsView.tsx (siempre manda coach_id) sigue
     * funcionando exactamente igual.
     */
    public function adminIndex(Request $request)
    {
        $request->validate([
            'coach_id'  => 'nullable|exists:users,id',
            'client_id' => 'nullable|exists:users,id',
        ]);

        $coachId = $request->filled('coach_id') ? (int) $request->coach_id : null;

        return json_custom_response(['data' => $this->buildQuery($request, $coachId)->get()]);
    }

    /**
     * GET /admin/coach-exceptions/unread-summary
     *
     * AÑADIDO (Plan_Cierre_Motor_UI.md, Fase 4): endpoint ligero para la
     * campana de notificaciones del admin (Notifications.tsx, hoy con
     * datos mock) -- conteo de ítems pendientes por severidad de TODOS los
     * coaches + los N más recientes/severos para el dropdown. Reutiliza
     * buildQuery() (mismo shape que adminIndex(), sin filtrar por coach).
     */
    public function adminUnreadSummary(Request $request)
    {
        $request->validate(['limit' => 'nullable|integer|min:1|max:50']);

        $limit = (int) $request->get('limit', 10);

        $counts = CoachExceptionItem::where('status', ExceptionStatus::PENDIENTE->value)
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $items = $this->buildQuery($request->merge(['status' => ExceptionStatus::PENDIENTE->value]), null)
            ->limit($limit)
            ->get();

        return json_custom_response(['data' => [
            'counts' => [
                'alta'  => (int) ($counts['alta'] ?? 0),
                'media' => (int) ($counts['media'] ?? 0),
                'baja'  => (int) ($counts['baja'] ?? 0),
            ],
            'total' => (int) $counts->sum(),
            'items' => $items,
        ]]);
    }

    /**
     * GET /admin/coach-exceptions/coaches -- lista para el selector del
     * panel. AÑADIDO propio (no pedido por el documento): investigado antes
     * de usar `/admin/reports/coaching-metrics` (que ya devuelve coach_id/
     * coach_name) para no duplicar una consulta -- descartado al
     * encontrarla realmente rota en producción hoy mismo
     * (`ReportController::coachingMetrics()` llama a `User::role(['coach'])`,
     * que lanza `RoleDoesNotExist` porque el rol Spatie 'coach' no existe
     * para el guard 'web' -- 500 real, confirmado con curl contra
     * testapp.bestronger.es, bug preexistente no introducido aquí, dejado
     * sin tocar por ser código ajeno a esta tarea). Se evita la dependencia
     * por completo usando el mismo criterio ya establecido en todo el Motor
     * para identificar coaches: `user_type='coach'`, no roles Spatie (ver
     * docblock de SessionProgressionRuleController).
     */
    public function adminCoachOptions()
    {
        $coaches = User::where('user_type', 'coach')
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'display_name', 'email'])
            ->map(fn (User $c) => [
                'id' => $c->id,
                'name' => $c->display_name ?: trim("{$c->first_name} {$c->last_name}") ?: $c->email,
            ]);

        return json_custom_response(['data' => $coaches]);
    }

    /** POST /admin/coach-exceptions/{id}/resolve */
    public function adminResolve(Request $request, $id)
    {
        $item = CoachExceptionItem::findOrFail($id);

        return $this->applyResolution($item, ExceptionStatus::RESUELTA, $request->user()?->id);
    }

    /** POST /admin/coach-exceptions/{id}/dismiss */
    public function adminDismiss(Request $request, $id)
    {
        $item = CoachExceptionItem::findOrFail($id);

        return $this->applyResolution($item, ExceptionStatus::DESCARTADA, $request->user()?->id);
    }

    /**
     * Filtrable por ?category=, ?severity=, ?status= (default: pendiente),
     * ordenado por severity (alta > media > baja) y luego created_at desc
     * (documento §4).
     *
     * $coachId nullable (Plan_Cierre_Motor_UI.md, Fase 2): null = todos los
     * coaches (dashboard general). ?client_id= (opcional, independiente de
     * $coachId) filtra por cliente concreto sin importar el coach (resumen
     * de cliente).
     */
    private function buildQuery(Request $request, ?int $coachId)
    {
        $status = $request->get('status', ExceptionStatus::PENDIENTE->value);

        $query = CoachExceptionItem::query()
            ->when($coachId !== null, fn ($q) => $q->where('coach_id', $coachId))
            ->with(['client:id,first_name,last_name,display_name,email'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->severity))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->client_id));

        return $query
            ->orderByRaw("FIELD(severity, 'alta', 'media', 'baja')")
            ->orderByDesc('created_at');
    }

    private function applyResolution(CoachExceptionItem $item, ExceptionStatus $status, ?int $resolvedBy)
    {
        if ($item->status !== ExceptionStatus::PENDIENTE) {
            return json_custom_response(['message' => 'Este ítem ya fue resuelto.'], 422);
        }

        $item->status = $status->value;
        $item->resolved_at = now();
        $item->resolved_by = $resolvedBy;
        $item->save();

        return json_custom_response(['data' => $item->fresh(['client'])]);
    }
}
