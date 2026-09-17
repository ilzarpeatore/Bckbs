<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TrainingProgram;
use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
use App\Models\User;
use App\Notifications\CommonNotification;
use App\Services\ProgramAssignmentService;
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
        // SEGURIDAD (barrido sistematico 2026-09-01, PLAUSIBLE/MEDIO-ALTO):
        // getDetail/generateWeeks/update/destroy/assignClient/removeAssignment/
        // getAssignments hacian find()/where('id',...) sin comprobar coach_id --
        // cualquier coach con cuenta de panel podia leer/editar/borrar el
        // programa de OTRO coach. store()/assignClient() ademas no comprobaban
        // que client_id perteneciera al roster de este coach (users.coach_id).
        // Optimizacion (2026-09-14): se anade 'workout.workoutDay.blocks.exercises.exercise'
        // y '...workoutDayExercise.exercise' al eager load de arriba para que
        // WorkoutDay::getFullDayWithBlocks() (llamado por dia mas abajo) no
        // tenga que volver a consultar blocks/workoutDayExercise POR CADA DIA
        // del programa -- antes eran 2 queries extra por dia (120-180 de mas
        // en un programa de 3 meses), ahora solo las de este with().
        $program = TrainingProgram::with([
                'workout.workoutDay.workoutDayExercise',
                'workout.workoutDay.blocks.exercises.exercise',
                'workout.workoutDay.workoutDayExercise.exercise',
            ])
            ->where('id', $request->id)
            ->where('coach_id', auth('sanctum')->id())
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

        if ($request->filled('client_id')) {
            $ownsClient = User::where('id', $request->client_id)->where('coach_id', $coach_id)->exists();
            if (!$ownsClient) {
                return json_message_response('Client not found or not assigned to this coach.', 404);
            }
        }

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
        $program = TrainingProgram::where('coach_id', auth('sanctum')->id())->find($request->id);

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
        $program = TrainingProgram::where('coach_id', auth('sanctum')->id())->find($request->id);

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
        $program = TrainingProgram::where('coach_id', auth('sanctum')->id())->find($request->id);

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

        $program = TrainingProgram::where('coach_id', auth('sanctum')->id())->find($request->training_program_id);
        if ($program == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Training Program']));
        }

        $ownsClient = User::where('id', $request->client_id)->where('coach_id', auth('sanctum')->id())->exists();
        if (!$ownsClient) {
            return json_message_response('Client not found or not assigned to this coach.', 404);
        }

        $startDate = Carbon::parse($request->start_date);

        $assignment = (new ProgramAssignmentService())->assignOrRenew(
            (int) $request->client_id,
            $program,
            ['start_date' => $startDate]
        );

        $this->notifyProgramAssigned($request->client_id, $program);

        return json_custom_response([
            'data'    => $assignment,
            // wasRecentlyCreated: true si assignOrRenew() creó la fila
            // (nueva) en esta misma llamada, false si actualizó una ya
            // existente (renovación) -- funciona igual con el flag de
            // clonado activado o desactivado.
            'message' => $assignment->wasRecentlyCreated ? 'Client assigned' : 'Assignment updated',
        ]);
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

        $deleted = ProgramClientAssignment::where('id', $request->id)
            ->whereHas('trainingProgram', function ($q) {
                $q->where('coach_id', auth('sanctum')->id());
            })
            ->delete();

        if ($deleted === 0) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Assignment']));
        }

        return json_message_response('Client unassigned');
    }

    public function getAssignments(Request $request)
    {
        $request->validate([
            'training_program_id' => 'required|exists:training_programs,id',
        ]);

        $program = TrainingProgram::where('coach_id', auth('sanctum')->id())->find($request->training_program_id);
        if ($program == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Training Program']));
        }

        $assignments = ProgramClientAssignment::with('client')
            ->where('training_program_id', $request->training_program_id)
            ->get();

        return json_custom_response(['data' => $assignments]);
    }

    /**
     * Plan de Optimización, Ronda 16 ítem 45 (docs/Motor_Autorregulacion_Analisis.md):
     * el coach marca una semana ENTERA del mesociclo como descarga
     * planificada -- bulk update de todas las filas de
     * program_day_assignments de esa (training_program_id, week_number),
     * sin importar cuántos días tenga la semana ni cómo se hayan creado
     * esas filas (import, generador de semanas, calendario...). Ver
     * SessionInterpretationService::detectOutliers().
     */
    public function markWeekDeload(Request $request)
    {
        $request->validate([
            'training_program_id' => 'required|exists:training_programs,id',
            'week_number'         => 'required|integer|min:1',
            'is_deload'           => 'required|boolean',
        ]);

        $program = TrainingProgram::where('coach_id', auth('sanctum')->id())->find($request->training_program_id);
        if ($program == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Training Program']));
        }

        $updated = ProgramDayAssignment::where('training_program_id', $request->training_program_id)
            ->where('week_number', $request->week_number)
            ->update(['is_deload' => $request->boolean('is_deload')]);

        if ($updated === 0) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Week']));
        }

        return json_message_response($updated.' días actualizados');
    }
}
