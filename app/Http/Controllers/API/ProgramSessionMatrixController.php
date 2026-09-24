<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TrainingProgram;
use App\Services\ProgramSessionMatrixService;
use Illuminate\Http\Request;

/**
 * Editor de sesiones a nivel programa (mesociclo): ver ProgramSessionMatrixService.
 * Solo programas del coach autenticado.
 */
class ProgramSessionMatrixController extends Controller
{
    public function show(Request $request, ProgramSessionMatrixService $service)
    {
        $request->validate([
            'training_program_id' => 'required|exists:training_programs,id',
            'assignment_ids'      => 'nullable|array',
            'assignment_ids.*'    => 'integer',
        ]);

        $program = TrainingProgram::where('coach_id', auth()->id())->find($request->training_program_id);
        if ($program === null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Training Program']));
        }

        return json_custom_response([
            'data' => $service->build($program, $request->input('assignment_ids')),
        ]);
    }

    public function save(Request $request, ProgramSessionMatrixService $service)
    {
        $request->validate([
            'training_program_id'      => 'required|exists:training_programs,id',
            'changes'                  => 'present|array|max:2000',
            'changes.*.type'           => 'required|in:update,add,remove,substitute',
            'changes.*.assignment_id'  => 'required|integer',
            'changes.*.row_id'         => 'required_if:changes.*.type,update,remove,substitute|nullable|integer',
            'changes.*.exercise_id'    => 'required_if:changes.*.type,add,substitute|nullable|integer|exists:exercises,id',
            'changes.*.block_id'       => 'nullable|integer',
            'changes.*.prescribed'     => 'nullable|array',
            'changes.*.enabled_metrics' => 'nullable|array',
            'deload'                   => 'nullable|array',
            'deload.*.week_number'     => 'required|integer|min:1',
            'deload.*.is_deload'       => 'required|boolean',
        ]);

        $program = TrainingProgram::where('coach_id', auth()->id())->find($request->training_program_id);
        if ($program === null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Training Program']));
        }

        $result = $service->save(
            $program,
            (int) auth()->id(),
            $request->input('changes', []),
            $request->input('deload', []),
        );

        return json_custom_response([
            'data'    => $result,
            'message' => 'Cambios guardados',
        ]);
    }
}
