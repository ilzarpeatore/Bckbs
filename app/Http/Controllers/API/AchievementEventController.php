<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AchievementEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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
