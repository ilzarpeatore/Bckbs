<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\TrainingTechniques;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catálogo de técnicas especiales (App\Support\TrainingTechniques), para el
 * panel y la app. El panel (/tecnicas-especiales) edita sus textos.
 */
class TrainingTechniqueController extends Controller
{
    public function getList()
    {
        return json_custom_response(['data' => TrainingTechniques::list()]);
    }

    /** POST admin/training-technique-save: textos de una técnica (el slug no cambia). */
    public function save(Request $request)
    {
        $request->validate([
            'key'         => ['required', 'string', Rule::in(array_keys(TrainingTechniques::CATALOG))],
            'label'       => 'required|string|max:60',
            'description' => 'required|string|max:600',
            'steps'       => 'present|array|max:12',
            'steps.*'     => 'nullable|string|max:300',
            'mistakes'    => 'present|array|max:12',
            'mistakes.*'  => 'nullable|string|max:300',
            'logging'     => 'nullable|string|max:400',
        ]);

        $clean = fn (array $lines) => array_values(array_filter(array_map(fn ($l) => trim((string) $l), $lines), fn ($l) => $l !== ''));

        TrainingTechniques::saveTexts($request->key, [
            'label'       => trim($request->label),
            'description' => trim($request->description),
            'steps'       => $clean($request->input('steps', [])),
            'mistakes'    => $clean($request->input('mistakes', [])),
            'logging'     => trim((string) $request->input('logging', '')),
        ]);

        return json_custom_response(['data' => TrainingTechniques::list()]);
    }

    /** POST admin/training-technique-reset: vuelve a los textos por defecto. */
    public function reset(Request $request)
    {
        $request->validate([
            'key' => ['required', 'string', Rule::in(array_keys(TrainingTechniques::CATALOG))],
        ]);

        TrainingTechniques::resetTexts($request->key);

        return json_custom_response(['data' => TrainingTechniques::list()]);
    }
}
