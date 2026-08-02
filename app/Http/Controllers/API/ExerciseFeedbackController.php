<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClientExerciseFeedback;

class ExerciseFeedbackController extends Controller
{
    /**
     * Toggle: si feedback es null, borra el registro. Si ya existe con
     * el mismo valor, se sobreescribe igual (idempotente).
     */
    public function store(Request $request)
    {
        $request->validate([
            'exercise_id' => 'required|exists:exercises,id',
            'feedback'    => 'nullable|in:like,dislike',
        ]);

        $client_id = auth('sanctum')->id();

        if ($request->feedback === null) {
            ClientExerciseFeedback::where('client_id', $client_id)
                ->where('exercise_id', $request->exercise_id)
                ->delete();

            return json_custom_response(['data' => ['exercise_id' => (int) $request->exercise_id, 'feedback' => null]]);
        }

        $row = ClientExerciseFeedback::updateOrCreate(
            ['client_id' => $client_id, 'exercise_id' => $request->exercise_id],
            ['feedback' => $request->feedback]
        );

        return json_custom_response(['data' => $row]);
    }
}
