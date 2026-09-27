<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Support\FuzzySearch;
use App\Support\MacrocycleTitle;
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
        } else {
            // AÑADIDO (2026-09-20, junto con TrainingProgram::cloneForClient()):
            // sin este filtro, cada clon creado al asignar un programa a un
            // cliente aparecería aquí también -- este listado es la
            // BIBLIOTECA (selector para "duplicar"/"asignar a cliente" en
            // TrainingProgramsView.tsx y los desplegables de programas en
            // ClientCalendarView.tsx/UserDetailView.tsx/ProgressionRulesView.tsx,
            // ninguno pasa client_id hoy), no un listado de todas las copias
            // que existen en la BD.
            $program = $program->whereNull('client_id');
        }

        if ($request->has('activo')) {
            $program = $program->where('activo', (bool) $request->activo);
        }

        if ($request->has('search') && !empty($request->search)) {
            FuzzySearch::apply($program, ['title'], $request->search);
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

        // FIX (pedido explícito 2026-09-20): ordenaba por fecha_inicio, un
        // campo editable por el coach -- cambiar la fecha de inicio de un
        // programa (o cualquier edición que la toque) lo reordenaba en la
        // lista. created_at nunca cambia al editar (Eloquent solo actualiza
        // updated_at en un save()), así que el orden ahora es estable.
        $program = $program->orderByDesc('created_at')->paginate($per_page);

        $response = [
            'pagination' => json_pagination_response($program),
            'data'       => $program->items(),
        ];

        return json_custom_response($response);
    }

    /**
     * Macrociclos (página /macrociclos del panel, pedido 2026-09-27): todos
     * los programas no personales -- biblioteca Y copias por cliente, a
     * diferencia de getList() -- agrupados por macrociclo según su título
     * (ver App\Support\MacrocycleTitle). Un mismo macrociclo de biblioteca
     * y su copia asignada a un cliente son grupos distintos (clave
     * macrociclo + client_id): cada cliente tiene sus propias filas desde
     * TrainingProgram::cloneForClient(). Los programas cuyo título no
     * menciona mesociclo/macrociclo no aparecen.
     */
    public function getMacrocycles(Request $request)
    {
        $programs = TrainingProgram::with([
                'client:id,display_name,email',
                'clientAssignments' => fn ($q) => $q->with('client:id,display_name,email')->orderBy('start_date'),
            ])
            ->where('is_personal', false)
            ->orderBy('created_at')
            ->get();

        $groups = [];
        $unassigned = [];
        foreach ($programs as $program) {
            $parsed = self::macrocycleOf($program);
            if ($parsed === null) {
                // Programas sueltos: el panel los ofrece para asignarlos a mano a un macrociclo
                $unassigned[] = [
                    'id'     => $program->id,
                    'title'  => $program->title,
                    'client' => $program->client ? [
                        'id'           => $program->client->id,
                        'display_name' => $program->client->display_name,
                    ] : null,
                ];
                continue;
            }

            $groupKey = $parsed['key'] . '#' . ($program->client_id ?? 'library');
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'key'        => $groupKey,
                    'name'       => $parsed['macrocycle'],
                    'client'     => $program->client ? [
                        'id'           => $program->client->id,
                        'display_name' => $program->client->display_name,
                        'email'        => $program->client->email,
                    ] : null,
                    'mesocycles' => [],
                ];
            }

            $groups[$groupKey]['mesocycles'][] = [
                'id'               => $program->id,
                'title'            => $program->title,
                'mesocycle_number' => $parsed['mesocycle'],
                'grouping'         => $parsed['manual'] ? 'manual' : 'title',
                'macrocycle_name'  => $program->macrocycle_name,
                'num_weeks'        => $program->num_weeks,
                'fecha_inicio'     => optional($program->fecha_inicio)->toDateString(),
                'fecha_fin'        => optional($program->fecha_fin)->toDateString(),
                'activo'           => (bool) $program->activo,
                'source'           => $program->source,
                'source_id'        => $program->source_id,
                'created_at'       => optional($program->created_at)->toIso8601String(),
                'assignments'      => $program->clientAssignments->map(fn ($a) => [
                    'id'          => $a->id,
                    'client_id'   => $a->client_id,
                    'client_name' => optional($a->client)->display_name,
                    'start_date'  => $a->start_date ? Carbon::parse($a->start_date)->toDateString() : null,
                    'fecha_fin'   => $a->fecha_fin ? Carbon::parse($a->fecha_fin)->toDateString() : null,
                    'activo'      => (bool) $a->activo,
                    'cerrado_at'  => $a->cerrado_at ? Carbon::parse($a->cerrado_at)->toIso8601String() : null,
                ])->values(),
            ];
        }

        foreach ($groups as &$group) {
            // Numerados primero (1, 2, 3...), los sin número al final por fecha de creación
            usort($group['mesocycles'], function ($a, $b) {
                $na = $a['mesocycle_number'] ?? PHP_INT_MAX;
                $nb = $b['mesocycle_number'] ?? PHP_INT_MAX;
                return $na <=> $nb ?: strcmp((string) $a['created_at'], (string) $b['created_at']);
            });
            $group['total_weeks'] = array_sum(array_map(fn ($m) => (int) $m['num_weeks'], $group['mesocycles']));
            $group['last_created_at'] = max(array_map(fn ($m) => (string) $m['created_at'], $group['mesocycles']));
        }
        unset($group);

        // Los macrociclos tocados más recientemente arriba
        $groups = array_values($groups);
        usort($groups, fn ($a, $b) => strcmp($b['last_created_at'], $a['last_created_at']));

        return json_custom_response(['data' => $groups, 'unassigned' => $unassigned]);
    }

    /**
     * Macrociclo de un programa: el asignado a mano (macrocycle_name) manda;
     * si no hay, se deduce del título. mesocycle_number manual también manda
     * sobre el número del título.
     *
     * @return array{macrocycle: string, key: string, mesocycle: int|null, manual: bool}|null
     */
    private static function macrocycleOf(TrainingProgram $program): ?array
    {
        $parsed = MacrocycleTitle::parse($program->title);
        $manualName = trim((string) $program->macrocycle_name);

        if ($manualName !== '') {
            return [
                'macrocycle' => $manualName,
                'key'        => mb_strtolower($manualName),
                'mesocycle'  => $program->mesocycle_number ?? ($parsed['mesocycle'] ?? null),
                'manual'     => true,
            ];
        }

        if ($parsed === null) {
            return null;
        }

        if ($program->mesocycle_number !== null) {
            $parsed['mesocycle'] = $program->mesocycle_number;
        }

        return $parsed + ['manual' => false];
    }

    /** Nº de mesociclo de un programa (manual o deducido del título), para el dashboard del macrociclo. */
    public static function mesocycleNumberOf(TrainingProgram $program): ?int
    {
        return self::macrocycleOf($program)['mesocycle'] ?? null;
    }

    /**
     * Asigna a mano un programa a un macrociclo (página /macrociclos).
     * macrocycle_name vacío/null quita la asignación manual y el programa
     * vuelve a agruparse por su título. Mismo control de propiedad que
     * update(): solo el coach dueño del programa.
     */
    public function setMacrocycle(Request $request)
    {
        $request->validate([
            'id'               => 'required|integer',
            'macrocycle_name'  => 'nullable|string|max:150',
            'mesocycle_number' => 'nullable|integer|min:1|max:999',
        ]);

        $program = TrainingProgram::where('coach_id', auth('sanctum')->id())
            ->where('is_personal', false)
            ->find($request->id);

        if ($program == null) {
            return json_message_response('Programa no encontrado o no eres su coach.', 404);
        }

        $name = trim((string) $request->input('macrocycle_name', ''));
        $program->update([
            'macrocycle_name'  => $name !== '' ? $name : null,
            'mesocycle_number' => $request->filled('mesocycle_number') ? (int) $request->mesocycle_number : null,
        ]);

        return json_message_response(__('message.save_form', ['form' => 'Training Program']));
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
            // Lógica de clonado real (programa + días + workout_templates,
            // dedupe de plantillas repetidas) extraída a
            // TrainingProgram::cloneWithStructure() (2026-09-20) para
            // compartirla con TrainingProgram::cloneForClient(), que la
            // necesita para el mismo propósito al asignar un programa a un
            // cliente -- ver el docblock de ese método para el porqué.
            $copy = $original->cloneWithStructure([
                'title'     => trim(($original->title ?: 'Programa #'.$original->id).' (copia)'),
                'coach_id'  => auth('sanctum')->id(),
            ]);

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

        // BUG REAL (reportado 2026-09-20): esto apuntaba directamente a
        // $request->training_program_id -- el programa de la BIBLIOTECA,
        // compartido por cualquier otro cliente que también lo tuviera
        // asignado. Borrar un entrenamiento del calendario de un cliente
        // (ClientProfileCalendarController::removeAssignment()) borraba esa
        // fila para todos a la vez, incluida la propia biblioteca. Ver
        // TrainingProgram::cloneForClient() para el porqué completo y el
        // mismo patrón ya usado para asignación de un solo entrenamiento
        // suelto (assignDirect()).
        //
        // "¿ya tiene este programa asignado?" ahora compara contra el
        // ORIGINAL (source_id) además del id directo -- una asignación ya
        // clonada nunca vuelve a tener training_program_id igual al de la
        // biblioteca, pero una asignación previa a este fix (todavía sin
        // migrar) sí. Reasignar solo mueve fechas, nunca reclona: no debe
        // borrar el historial/personalizaciones que ese cliente ya tenga
        // sobre su copia.
        $existing = ProgramClientAssignment::where('client_id', $request->client_id)
            ->whereHas('trainingProgram', function ($q) use ($request) {
                $q->where('id', $request->training_program_id)
                  ->orWhere('source_id', $request->training_program_id);
            })
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

        $clientCopy = $program->cloneForClient((int) $request->client_id);

        $assignment = ProgramClientAssignment::create([
            'training_program_id' => $clientCopy->id,
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
