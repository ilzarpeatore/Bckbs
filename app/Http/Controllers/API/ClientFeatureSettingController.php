<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClientFeatureSetting;

class ClientFeatureSettingController extends Controller
{
    /**
     * La app consulta esto al arrancar para saber qué pestañas/menús
     * mostrarle al cliente. Si una feature no tiene fila, se asume
     * habilitada (opt-out, no opt-in) — ver ClientFeatureSetting::isEnabledFor().
     */
    public function getMySettings(Request $request)
    {
        $user_id = $request->filled('client_id') ? $request->client_id : auth('sanctum')->id();

        $all_features = ['workout', 'nutrition', 'habits', 'forms', 'resources', 'chatbot', 'readiness_check'];

        $userSettings = ClientFeatureSetting::where('client_id', $user_id)
            ->whereIn('feature_key', $all_features)
            ->pluck('is_enabled', 'feature_key');

        $settings = collect($all_features)->mapWithKeys(function ($feature) use ($userSettings) {
            return [$feature => $userSettings->has($feature) ? (bool) $userSettings[$feature] : true];
        });

        return json_custom_response(['data' => $settings]);
    }

    /** Desde el panel Admin: activar/desactivar una feature para un cliente. */
    public function update(Request $request)
    {
        $request->validate([
            'client_id'   => 'required|exists:users,id',
            'feature_key' => 'required|in:workout,nutrition,habits,forms,resources,chatbot,readiness_check',
            'is_enabled'  => 'required|boolean',
        ]);

        ClientFeatureSetting::updateOrCreate(
            ['client_id' => $request->client_id, 'feature_key' => $request->feature_key],
            ['is_enabled' => $request->is_enabled]
        );

        return json_message_response(__('message.save_form', ['form' => 'Feature Setting']));
    }
}
