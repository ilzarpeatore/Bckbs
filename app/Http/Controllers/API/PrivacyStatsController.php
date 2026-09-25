<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BlockedUser;
use App\Models\User;
use App\Services\PublicStatsService;
use Illuminate\Http\Request;

/**
 * Estadísticas públicas opt-in de un usuario (perfil de otro usuario en la app).
 *
 * - GET  v1/user-public-stats?user_id=   resumen agregado de OTRO usuario (o el propio).
 * - GET  v1/my-privacy-settings          mi ajuste actual.
 * - POST v1/my-privacy-settings          activar/desactivar show_public_stats.
 *
 * Apagado por defecto; si el dueño no lo activó (o hay un bloqueo entre ambos) la
 * respuesta es solo {visible:false}, sin ningún dato. El propio usuario siempre ve lo suyo.
 */
class PrivacyStatsController extends Controller
{
    public function show(Request $request, PublicStatsService $stats)
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $me = auth('sanctum')->id();
        $target = User::findOrFail((int) $request->user_id);

        if ($target->id !== $me) {
            $blocked = BlockedUser::where(fn ($q) => $q->where('blocker_id', $me)->where('blocked_id', $target->id))
                ->orWhere(fn ($q) => $q->where('blocker_id', $target->id)->where('blocked_id', $me))
                ->exists();

            if (!$target->show_public_stats || $blocked) {
                return json_custom_response(['data' => ['visible' => false]]);
            }
        }

        return json_custom_response(['data' => [
            'visible' => true,
            'stats'   => $stats->forUser($target->id),
        ]]);
    }

    public function mySettings()
    {
        return json_custom_response(['data' => [
            'show_public_stats' => (bool) auth('sanctum')->user()->show_public_stats,
        ]]);
    }

    public function updateMySettings(Request $request)
    {
        $request->validate(['show_public_stats' => 'required|boolean']);

        $user = auth('sanctum')->user();
        // forceFill: la columna no está en $fillable a propósito (ningún endpoint de
        // perfil genérico debe poder tocarla por asignación masiva).
        $user->forceFill(['show_public_stats' => $request->boolean('show_public_stats')])->save();

        return json_custom_response(['data' => ['show_public_stats' => (bool) $user->show_public_stats]]);
    }
}
