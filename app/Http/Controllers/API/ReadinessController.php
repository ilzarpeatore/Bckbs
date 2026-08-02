<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DailyReadinessCheck;
use App\Models\ClientFeatureSetting;

/**
 * Readiness diario obligatorio antes de Workout Preview (sueño, agujetas,
 * energía, estrés) - obligatorio por defecto, salvo que el admin lo
 * desactive para un cliente concreto (ClientFeatureSetting 'readiness_check',
 * opt-out por diseño, ver ClientFeatureSetting::isEnabledFor()).
 */
class ReadinessController extends Controller
{
    public function today(Request $request)
    {
        $user = auth('sanctum')->user();
        $today = now()->toDateString();

        $required = ClientFeatureSetting::isEnabledFor($user->id, 'readiness_check');

        $check = DailyReadinessCheck::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        return json_custom_response([
            'data' => [
                'required'        => $required,
                'submitted_today' => $check !== null,
                'today'           => $check ? [
                    'sleep_quality'  => $check->sleep_quality,
                    'soreness_level' => $check->soreness_level,
                    'energy_level'   => $check->energy_level,
                    'stress_level'   => $check->stress_level,
                ] : null,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sleep_quality'  => 'required|integer|min:1|max:5',
            'soreness_level' => 'required|integer|min:1|max:10',
            'energy_level'   => 'required|integer|min:1|max:5',
            'stress_level'   => 'required|integer|min:1|max:5',
        ]);

        $user = auth('sanctum')->user();

        $check = DailyReadinessCheck::updateOrCreate(
            ['user_id' => $user->id, 'date' => now()->toDateString()],
            [
                'sleep_quality'  => $request->sleep_quality,
                'soreness_level' => $request->soreness_level,
                'energy_level'   => $request->energy_level,
                'stress_level'   => $request->stress_level,
            ]
        );

        return json_custom_response(['data' => $check]);
    }
}
