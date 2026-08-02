<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SectionTemplate;
use App\Models\SectionTemplateExercise;

class SectionTemplateController extends Controller
{
    public function getList(Request $request)
    {
        $sections = SectionTemplate::where('coach_id', auth()->id())
            ->withCount('exercises')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return json_custom_response(['data' => $sections]);
    }

    public function getDetail(Request $request)
    {
        $section = SectionTemplate::with('exercises.exercise')->find($request->id);

        if ($section == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Section']));
        }

        return json_custom_response(['data' => $section]);
    }

    public function store(Request $request)
    {
        $request->validate(['title' => 'required|string|max:255']);

        $section = SectionTemplate::create([
            'coach_id'     => auth()->id(),
            'title'        => $request->title,
            'instructions' => $request->instructions,
        ]);

        return json_custom_response(['data' => $section]);
    }

    public function update(Request $request)
    {
        $section = SectionTemplate::find($request->id);

        if ($section == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Section']));
        }

        $section->update($request->only(['title', 'instructions']));

        return json_message_response(__('message.save_form', ['form' => 'Section']));
    }

    public function destroy(Request $request)
    {
        $section = SectionTemplate::find($request->id);

        if ($section == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Section']));
        }

        $section->delete();

        return json_message_response(__('message.delete_form', ['form' => 'Section']));
    }

    /** Añadir/editar un ejercicio dentro de la plantilla de sección. */
    public function saveExercise(Request $request)
    {
        $request->validate([
            'section_template_id' => 'required|exists:section_templates,id',
            'exercise_id'          => 'required|exists:exercises,id',
        ]);

        $order = SectionTemplateExercise::where('section_template_id', $request->section_template_id)->max('sequence') ?? 0;

        $exercise = SectionTemplateExercise::updateOrCreate(
            [
                'id' => $request->id ?? null,
            ],
            [
                'section_template_id' => $request->section_template_id,
                'exercise_id'          => $request->exercise_id,
                'sequence'             => $request->sequence ?? ($order + 1),
                'prescribed'           => $request->prescribed ?? [],
                'enabled_metrics'      => $request->enabled_metrics ?? [],
            ]
        );

        return json_custom_response(['data' => $exercise]);
    }

    /**
     * CORREGIDO: guardado de UN SOLO campo del prescrito, fusionando con
     * lo que ya hubiera guardado — antes, cada cambio de campo sobrescribía
     * el objeto `prescribed` entero, borrando los demás campos.
     */
    public function updatePrescribedField(Request $request)
    {
        $request->validate([
            'id'    => 'required|exists:section_template_exercises,id',
            'field' => 'required|string',
            'value' => 'nullable',
        ]);

        $exercise = SectionTemplateExercise::find($request->id);
        $prescribed = $exercise->prescribed ?? [];
        $prescribed[$request->field] = $request->value;
        $exercise->update(['prescribed' => $prescribed]);

        return json_custom_response(['data' => $exercise]);
    }

    public function deleteExercise(Request $request)
    {
        SectionTemplateExercise::where('id', $request->id)->delete();

        return json_message_response(__('message.delete_form', ['form' => 'Exercise']));
    }
}
