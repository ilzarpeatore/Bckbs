<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PersonalRecord;
use App\Models\UserExercise;

class PersonalRecordController extends Controller
{
    /**
     * Pantalla "Exercise History": 4 tarjetas resumen + histórico sesión
     * a sesión con % de mejora respecto a la sesión anterior.
     * Ver sección 7.2 del análisis (RPE/RIR intercambiables, referencia
     * de última vez).
     */
    public function getExerciseHistory(Request $request)
    {
        $request->validate(['exercise_id' => 'required|exists:exercises,id']);

        $user_id = auth('sanctum')->id();

        $max_weight = PersonalRecord::where('user_id', $user_id)
            ->where('exercise_id', $request->exercise_id)
            ->where('record_type', 'max_weight')->max('value');

        $max_1rm = PersonalRecord::where('user_id', $user_id)
            ->where('exercise_id', $request->exercise_id)
            ->where('record_type', 'max_1rm')->max('value');

        $best_volume = PersonalRecord::where('user_id', $user_id)
            ->where('exercise_id', $request->exercise_id)
            ->where('record_type', 'max_volume')->max('value');

        $sessions_count = UserExercise::where('user_id', $user_id)
            ->where('exercise_id', $request->exercise_id)
            ->count();

        $sessions = UserExercise::where('user_id', $user_id)
            ->where('exercise_id', $request->exercise_id)
            ->select('id', 'created_at', 'logged_sets')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $response = [
            'max_weight'   => $max_weight,
            'max_1rm'      => $max_1rm,
            'best_volume'  => $best_volume,
            'sessions_count' => $sessions_count,
            'sessions'     => $sessions,
        ];

        return json_custom_response($response);
    }

    /**
     * "Última vez que se hizo este ejercicio" — se resuelve aquí, sin
     * tabla propia, tal como quedó definido en el análisis. Se usa como
     * referencia al abrir el ejercicio para rellenar la sesión actual.
     */
    public function getLastPerformance(Request $request)
    {
        $request->validate(['exercise_id' => 'required|exists:exercises,id']);

        $user_id = auth('sanctum')->id();

        $last = UserExercise::where('user_id', $user_id)
            ->where('exercise_id', $request->exercise_id)
            ->orderByDesc('created_at')
            ->first();

        return json_custom_response(['data' => $last]);
    }
}
