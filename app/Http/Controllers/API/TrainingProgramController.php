<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TrainingProgram;
use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
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

    /**
     * Duplica un programa completo (metadatos + program_day_assignments +
     * workout_templates/blocks/exercises) como una fila 100% independiente --
     * pedido explícito 2026-09-20: editar el duplicado no debe mutar el
     * original. NO basta con copiar training_programs y reapuntar los
     * program_day_assignments a los MISMOS workout_template_id: dos
     * training_programs distintos ya pueden compartir fila de
     * workout_templates hoy (ver stats "templates_reused" del importador,
     * y el bug real de 2026-09-18 en WorkoutTemplate::cloneStructure() que
     * resolvió el mismo problema para clientes distintos apuntando al mismo
     * template) -- por eso cada workout_template del programa se clona
     * también con cloneStructure(), nunca se reutiliza el id original.
     *
     * `workout_id` (arquitectura legacy Workout/WorkoutDay) se copia tal
     * cual sin clonar su árbol -- verificado 2026-09-20 que las 13 filas
     * reales de training_programs tienen workout_id=null, así que no hay
     * caso real que este campo pueda romper hoy; si en el futuro vuelve a
     * usarse, esta función necesitará también clonar esa rama.
     *
     * Nunca copia program_client_assignments -- el duplicado nace sin
     * asignar a ningún cliente, igual que un programa recién importado.
     */
    public function duplicate(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:training_programs,id',
        ]);

        $original = TrainingProgram::with('dayAssignments.workoutTemplate')
            ->where('coach_id', auth('sanctum')->id())
            ->find($request->id);

        if ($original == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Training Program']));
        }

        DB::beginTransaction();
        try {
            $copy = TrainingProgram::create([
                'title'               => trim(($original->title ?: 'Programa #'.$original->id).' (copia)'),
                'is_personal'         => false,
                'personal_client_id'  => null,
                'workout_id'          => $original->workout_id,
                'coach_id'            => auth('sanctum')->id(),
                'client_id'           => null,
                'num_weeks'           => $original->num_weeks,
                'fecha_inicio'        => $original->fecha_inicio,
                'fecha_fin'           => $original->fecha_fin,
                'activo'              => true,
                'is_free_accessible'  => $original->is_free_accessible,
                'billing_plan_id'     => $original->billing_plan_id,
                'source'              => null,
                'source_id'           => null,
            ]);

            // Un mismo workout_template puede repetirse en varios días del
            // propio programa (ej. "Torso A" en semana 1 y semana 3) -- se
            // clona una sola vez por template original y se reutiliza esa
            // copia dentro del NUEVO programa, en vez de crear una copia
            // distinta por cada fila que lo referenciaba.
            $clonedTemplateIds = [];
            foreach ($original->dayAssignments as $dayAssignment) {
                $newTemplateId = null;
                if ($dayAssignment->workout_template_id !== null && $dayAssignment->workoutTemplate !== null) {
                    $originalTemplateId = $dayAssignment->workout_template_id;
                    if (!isset($clonedTemplateIds[$originalTemplateId])) {
                        $clonedTemplateIds[$originalTemplateId] = $dayAssignment->workoutTemplate->cloneStructure()->id;
                    }
                    $newTemplateId = $clonedTemplateIds[$originalTemplateId];
                }

                ProgramDayAssignment::create([
                    'training_program_id' => $copy->id,
                    'week_number'         => $dayAssignment->week_number,
                    'day_of_week'         => $dayAssignment->day_of_week,
                    'workout_template_id' => $newTemplateId,
                    'scheduled_date'      => null,
                    'is_deload'           => $dayAssignment->is_deload,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return json_message_response('Failed: ' . $e->getMessage(), 500);
        }

        return json_custom_response([
            'data'    => $copy,
            'message' => 'Programa duplicado ("'.$copy->title.'"), sin clientes asignados.',
        ], 201);
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
