<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExerciseSubstitution;
use Illuminate\Http\Request;

/**
 * Motor de Auto-Regulación de Carga — CRUD admin de sustituciones de
 * ejercicio (variantes definidas por el coach, consultadas por
 * SessionProgressionRuleEngine::findSubstitution() cuando la acción
 * ganadora es sustituir_ejercicio). `category` es texto libre en BD (el
 * motor hoy solo usa 'estancamiento'/'fatiga' como motivo inferido, pero
 * no hay enum ni constraint) y `carga_ratio` un multiplicador de carga
 * opcional (ej. 0.8).
 */
class ExerciseSubstitutionController extends Controller
{
    /** GET /admin/exercise-substitutions?coach_id= */
    public function index(Request $request)
    {
        $request->validate(['coach_id' => 'required|exists:users,id']);

        $substitutions = ExerciseSubstitution::where('coach_id', $request->coach_id)
            ->with(['originalExercise:id,title', 'substituteExercise:id,title'])
            ->orderByDesc('id')
            ->get();

        return json_custom_response(['data' => $substitutions]);
    }

    /** POST /admin/exercise-substitutions */
    public function store(Request $request)
    {
        $data = $request->validate([
            'coach_id'                => 'required|exists:users,id',
            'original_exercise_id'    => 'required|exists:exercises,id',
            'substitute_exercise_id'  => 'required|exists:exercises,id|different:original_exercise_id',
            'category'                => 'nullable|string|max:50',
            'carga_ratio'             => 'nullable|numeric|min:0|max:10',
        ]);

        $substitution = ExerciseSubstitution::create($data);

        return json_custom_response(['data' => $substitution->load(['originalExercise:id,title', 'substituteExercise:id,title'])]);
    }

    /** PUT /admin/exercise-substitutions/{id} */
    public function update(Request $request, $id)
    {
        $substitution = ExerciseSubstitution::find($id);
        if (!$substitution) {
            return json_message_response('Sustitución no encontrada.', 404);
        }

        $data = $request->validate([
            'original_exercise_id'    => 'required|exists:exercises,id',
            'substitute_exercise_id'  => 'required|exists:exercises,id|different:original_exercise_id',
            'category'                => 'nullable|string|max:50',
            'carga_ratio'             => 'nullable|numeric|min:0|max:10',
        ]);

        $substitution->update($data);

        return json_custom_response(['data' => $substitution->fresh(['originalExercise:id,title', 'substituteExercise:id,title'])]);
    }

    /** DELETE /admin/exercise-substitutions/{id} */
    public function destroy($id)
    {
        $substitution = ExerciseSubstitution::find($id);
        if (!$substitution) {
            return json_message_response('Sustitución no encontrada.', 404);
        }

        $substitution->delete();

        return json_message_response('Sustitución eliminada.');
    }
}
