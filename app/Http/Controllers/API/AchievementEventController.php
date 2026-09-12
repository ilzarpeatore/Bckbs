<?php

namespace App\Http\Controllers\API;

use App\Enums\AchievementEventType;
use App\Http\Controllers\Controller;
use App\Models\AchievementEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;

/**
 * Motor de Auto-Regulación de Carga — Fase 3 (documento §3.2, tarea #20).
 * Feed de logros del cliente y ajustes de coach, gateado a paid-tier — un
 * cliente free nunca ve nada en el feed nuevo (sus personal_records reales
 * siguen disponibles vía /my-personal-records, sin cambios).
 */
class AchievementEventController extends Controller
{
    /** GET /api/clients/{id}/achievements */
    public function index(Request $request, $clientId)
    {
        $client = User::findOrFail($clientId);
        $this->authorizeClientAccess($request, $client);

        if (!Gate::forUser($client)->allows('paid-tier')) {
            return json_custom_response(['data' => []]);
        }

        $events = AchievementEvent::where('client_id', $client->id)
            ->with('exercise')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return json_custom_response(['data' => $events]);
    }

    /** POST /api/clients/{id}/achievements/{achievementId}/seen */
    public function markSeen(Request $request, $clientId, $achievementId)
    {
        $client = User::findOrFail($clientId);
        $this->authorizeClientAccess($request, $client);

        $event = AchievementEvent::where('client_id', $client->id)->findOrFail($achievementId);
        $event->shown_to_client = true;
        $event->save();

        return json_custom_response(['data' => $event]);
    }

    /**
     * GET /admin/achievement-events — visión admin/coach del feed de
     * logros (Motor de Auto-Regulación de Carga, Fase 3 §3.2). Sin gate de
     * paid-tier (a diferencia de index() arriba): esto es una herramienta
     * de auditoría de staff, no la vista transitoria del propio cliente.
     */
    public function adminIndex(Request $request)
    {
        $request->validate([
            'client_id' => 'nullable|exists:users,id',
            'type'      => ['nullable', 'string', new Enum(AchievementEventType::class)],
            'from'      => 'nullable|date',
            'to'        => 'nullable|date',
        ]);

        $query = AchievementEvent::query();

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', Carbon::parse($request->input('to'))->endOfDay());
        }

        $events = $query->with(['client:id,first_name,last_name,email', 'exercise:id,title'])
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return json_custom_response(['data' => $events]);
    }

    /**
     * GET /api/coaches/{id}/achievement-settings
     *
     * Umbrales configurables (documento §3.2). Decisión de diseño propia:
     * de solo lectura desde config/achievements.php en vez de una tabla
     * nueva por coach (el documento deja el criterio abierto) — no se
     * gatea a paid-tier porque no hay un cliente concreto al que aplicarle
     * ese gate (es la configuración del propio coach, no de un cliente),
     * solo se comprueba que el coach autenticado sea el dueño del {id}.
     */
    public function achievementSettings(Request $request, $coachId)
    {
        $user = $request->user();
        if (!$user || (int) $user->id !== (int) $coachId) {
            return json_custom_response(['message' => 'No autorizado.'], 403);
        }

        return json_custom_response(['data' => config('achievements')]);
    }

    private function authorizeClientAccess(Request $request, User $client): void
    {
        $authUser = $request->user();
        $isSelf = $authUser && (int) $authUser->id === (int) $client->id;
        $isCoach = $authUser && (int) $client->coach_id === (int) $authUser->id;

        if (!$isSelf && !$isCoach) {
            abort(403, 'No autorizado.');
        }
    }
}
