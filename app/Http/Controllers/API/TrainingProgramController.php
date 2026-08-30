<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TrainingProgram;
use App\Models\ProgramClientAssignment;
use App\Models\User;
use App\Notifications\CommonNotification;
use App\Services\TrainingProgramGeneratorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TrainingProgramController extends Controller
{
    /**
     * Programa(s) de entrenamiento del cliente autenticado.
     * Sigue el mismo patrón de paginación que WorkoutController::getList.
     */
    public function getList(Request $request)
    {
        $user = auth('sanctum')->user();

        $program = TrainingProgram::with(['workout', 'client'])
            ->where('is_personal', false);

        if ($request->has('client_id') && !empty($request->client_id)) {
            $program = $program->where('client_id', $request->client_id);
        }

        if ($request->has('activo')) {
            $program = $program->where('activo', (bool) $request->activo);
        }

        if ($request->has('search') && !empty($request->search)) {
            $program = $program->where('title', 'like', '%'.$request->search.'%');
        }

        $per_page = config('constant.PER_PAGE_LIMIT');
        if ($request->has('per_page') && !empty($request->per_page)) {
            if (is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            }
            if ($request->per_page == -1) {
                $per_page = $program->count();
            }
        }

        $program = $program->orderByDesc('fecha_inicio')->paginate($per_page);

        $response = [
            'pagination' => json_pagination_response($program),
            'data'       => $program->items(),
        ];

        return json_custom_response($response);
    }

    /**
     * Detalle de un programa concreto, con sus semanas/reglas de progresión
     * y los días de entrenamiento (vía workout->workoutDay) ya filtrados
     * por semana si se pide `week_number`.
     */
    public function getDetail(Request $request)
    {
        $program = TrainingProgram::with(['workout.workoutDay.workoutDayExercise'])
            ->where('id', $request->id)
            ->first();

        if ($program == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Training Program']));
        }

        $workout_days = $program->workout ? $program->workout->workoutDay : collect();

        if ($request->has('week_number')) {
            $workout_days = $workout_days->where('week_number', $request->week_number)->values();
        }

        $workout_days_with_blocks = $workout_days->map(fn ($day) => $day->getFullDayWithBlocks());

        $response = [
            'data'                      => $program,
            'workout_days'              => $workout_days,
            'workout_days_with_blocks'  => $workout_days_with_blocks,
        ];

        return json_custom_response($response);
    }

    /**
     * Crear un programa nuevo (uso desde el panel Admin, por el coach).
     * No asume ningún split ni número de semanas por defecto: todo llega
     * en el payload, tal como marca el principio de "nada hardcodeado".
     */
    public function store(Request $request)
    {
        $request->validate([
            'workout_id'    => 'required|exists:workouts,id',
            'client_id'     => 'nullable|exists:users,id',
            'num_weeks'     => 'required|integer|min:1',
            'fecha_inicio'  => 'required|date',
        ]);

        $coach_id = auth('sanctum')->id();

        DB::beginTransaction();
        try {
            $program = TrainingProgram::create([
                'title'         => $request->title,
                'workout_id'    => $request->workout_id,
                'coach_id'      => $coach_id,
                'client_id'     => $request->client_id,
                'num_weeks'     => $request->num_weeks,
                'fecha_inicio'  => $request->fecha_inicio,
                'fecha_fin'     => $request->fecha_fin,
                'activo'        => true,
            ]);

            if ($program->num_weeks > 1 && $request->boolean('auto_generate', true)) {
                (new TrainingProgramGeneratorService())->generateFromWeekOne($program);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return json_message_response('Failed: ' . $e->getMessage(), 500);
        }

        return json_message_response(__('message.save_form', ['form' => 'Training Program']));
    }

    /**
     * Endpoint separado para (re)generar S2-N cuando la Semana 1 se crea
     * DESPUÉS del programa (flujo más habitual: primero el programa,
     * luego se construye la Semana 1 en el editor de Workout, y solo
     * entonces se generan las siguientes).
     */
    public function generateWeeks(Request $request)
    {
        $program = TrainingProgram::find($request->id);

        if ($program == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Training Program']));
        }

        try {
            $created = (new TrainingProgramGeneratorService())->generateFromWeekOne($program);
        } catch (\RuntimeException $e) {
            return json_message_response($e->getMessage(), 422);
        }

        return json_custom_response(['data' => $created, 'message' => count($created).' días generados']);
    }

    public function update(Request $request)
    {
        $program = TrainingProgram::find($request->id);

        if ($program == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Training Program']));
        }

        DB::beginTransaction();
        try {
            $program->update($request->only(['title', 'num_weeks', 'fecha_inicio', 'fecha_fin', 'activo']));

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return json_message_response('Failed: ' . $e->getMessage(), 500);
        }

        return json_message_response(__('message.save_form', ['form' => 'Training Program']));
    }

    public function destroy(Request $request)
    {
        $program = TrainingProgram::find($request->id);

        if ($program == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Training Program']));
        }

        $program->delete();

        return json_message_response(__('message.delete_form', ['form' => 'Training Program']));
    }

    public function assignClient(Request $request)
    {
        $request->validate([
            'training_program_id' => 'required|exists:training_programs,id',
            'client_id'           => 'required|exists:users,id',
            'start_date'          => 'required|date',
        ]);

        $program = TrainingProgram::find($request->training_program_id);
        $startDate = Carbon::parse($request->start_date);
        $fechaFin = ProgramClientAssignment::computeFechaFin($startDate, $program->num_weeks);

        $existing = ProgramClientAssignment::where('training_program_id', $request->training_program_id)
            ->where('client_id', $request->client_id)
            ->first();

        if ($existing) {
            $existing->update([
                'start_date' => $request->start_date,
                'fecha_fin'  => $fechaFin->toDateString(),
                'activo'     => true,
                'cerrado_at' => null, // renovación = nuevo ciclo del mesociclo, no continuación del cerrado
            ]);
            $this->notifyProgramAssigned($request->client_id, $program);
            return json_custom_response(['data' => $existing, 'message' => 'Assignment updated']);
        }

        $assignment = ProgramClientAssignment::create([
            'training_program_id' => $request->training_program_id,
            'client_id'           => $request->client_id,
            'start_date'          => $request->start_date,
            'fecha_fin'           => $fechaFin->toDateString(),
            'activo'              => true,
        ]);

        $this->notifyProgramAssigned($request->client_id, $program);

        return json_custom_response(['data' => $assignment, 'message' => 'Client assigned']);
    }

    private function notifyProgramAssigned(int $client_id, ?TrainingProgram $program): void
    {
        $user = User::find($client_id);
        if (!$user || !$program) {
            return;
        }
        $user->notify(new CommonNotification('new_training_program', [
            'id'      => $program->id,
            'type'    => 'new_training_program',
            'subject' => 'Nuevo programa de entrenamiento',
            'message' => "Tu coach te ha asignado el programa \"{$program->title}\".",
        ]));
    }

    public function removeAssignment(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:program_client_assignments,id',
        ]);

        ProgramClientAssignment::where('id', $request->id)->delete();

        return json_message_response('Client unassigned');
    }

    public function getAssignments(Request $request)
    {
        $request->validate([
            'training_program_id' => 'required|exists:training_programs,id',
        ]);

        $assignments = ProgramClientAssignment::with('client')
            ->where('training_program_id', $request->training_program_id)
            ->get();

        return json_custom_response(['data' => $assignments]);
    }
}
