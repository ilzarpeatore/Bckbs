<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
use App\Models\ClientExerciseLog;
use App\Models\ClientExerciseOverride;
use App\Models\ClientBlockOverride;
use App\Models\WorkoutSessionReview;
use App\Models\Exercise;
use App\Models\WorkoutTemplate;
use App\Models\PersonalRecord;
use App\Models\NextSessionTarget;
use App\Services\CalendarDateMapper;
use App\Services\WorkoutSessionStatsService;
use App\Services\MuscleVolumeService;
use App\Traits\HasYoutubeThumbnail;
use App\Jobs\ProcessSessionInterpretation;
use App\Jobs\EvaluateSessionProgressionRules;
use App\Jobs\EvaluateSessionAchievements;
use App\Models\AchievementEvent;
use App\Enums\AchievementEventType;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ClientCalendarController extends Controller
{
    use HasYoutubeThumbnail;

    /**
     * AÑADIDO: resuelve un program_day_assignment_id comprobando que
     * pertenezca a un programa realmente asignado (ProgramClientAssignment
     * activo) del cliente autenticado — mismo criterio de scoping que ya
     * usa getMyMonth(). Se usa en getDayDetail(), logSets() y
     * moveAssignments() en vez de find()/exists: sueltos, que no
     * comprobaban de quién era la asignación (IDOR: cualquier cliente
     * autenticado podía leer/enlazar el día de entrenamiento de otro
     * cliente pasando su id).
     */
    private function resolveOwnedAssignment(int $id): ProgramDayAssignment
    {
        $assignment = ProgramDayAssignment::find($id);
        if (!$assignment) {
            abort(404, 'No encontrado.');
        }

        $owns = ProgramClientAssignment::where('client_id', auth('sanctum')->id())
            ->where('training_program_id', $assignment->training_program_id)
            ->where('activo', true)
            ->exists();

        if (!$owns) {
            abort(403, 'No tienes acceso a este entrenamiento.');
        }

        return $assignment;
    }

    /**
     * Motor de Auto-Regulación de Carga — set de exercise_id del cliente
     * autenticado con una sugerencia relevante para mostrar en calendario
     * (ver NextSessionTarget::scopeRelevantForClient). Vacío directamente
     * para clientes free, sin consultar NextSessionTarget -- mismo gate de
     * tier que el resto del motor (Gate::allows('paid-tier')), comprobado
     * una sola vez aquí y reutilizado tanto por getMyMonth() (badge por
     * entrenamiento) como por getDayDetail() (detalle por ejercicio).
     */
    private function loadSuggestionExerciseIds(int $clientId): \Illuminate\Support\Collection
    {
        if (!Gate::forUser(auth('sanctum')->user())->allows('paid-tier')) {
            return collect();
        }

        return NextSessionTarget::relevantForClient($clientId)->pluck('exercise_id')->unique();
    }

    /**
     * ACTUALIZADO: ahora combina TODOS los programas activos del
     * cliente (antes solo servía el más reciente) — mismo criterio que
     * ClientProfileCalendarController::getMergedMonth en el panel Admin,
     * para que la app y el panel muestren siempre lo mismo.
     */
    public function getMyMonth(Request $request)
    {
        $client_id = auth('sanctum')->id();

        $client_assignments = ProgramClientAssignment::where('client_id', $client_id)
            ->where('activo', true)
            ->with('trainingProgram')
            ->get();

        if ($client_assignments->isEmpty()) {
            return json_custom_response(['data' => ['days' => []], 'message' => 'No tienes ningún entrenamiento asignado todavía.']);
        }

        $mapper = new CalendarDateMapper();
        $year  = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $grid_dates = $mapper->getMonthGridDates($year, $month);

        $days_map = collect($grid_dates)->keyBy(fn ($d) => $d->toDateString())
            ->map(fn ($d) => [
                'date'     => $d->toDateString(),
                'in_month' => $d->month == $month,
                'workouts' => collect(),
            ]);

        // Motor de Auto-Regulación de Carga: exercise_id del cliente con
        // sugerencia relevante (una sola consulta para todo el mes/todos
        // los programas, no por asignación) -- ver loadSuggestionExerciseIds().
        $suggestionExerciseIds = $this->loadSuggestionExerciseIds($client_id);

        foreach ($client_assignments as $client_assignment) {
            $program = $client_assignment->trainingProgram;
            if (!$program) continue;

            $start_date = Carbon::parse($client_assignment->start_date);

            $assignments = ProgramDayAssignment::where('training_program_id', $program->id)
                ->with('workoutTemplate')
                ->get()
                ->groupBy(fn ($a) => $a->week_number.'-'.$a->day_of_week);

            // Modo vida real (2026-08-12): un día cuyos ejercicios están
            // TODOS ocultos para este cliente (semana adaptativa aplicada)
            // no debe listarse como si tuviera entrenamiento — mismo
            // criterio que getDayDetail(), calculado aquí por lotes para no
            // repetir consultas dentro del bucle de fechas.
            $assignmentIds = $assignments->flatten()->pluck('id')->values()->all();
            $templateIds = $assignments->flatten()->pluck('workout_template_id')->filter()->unique()->values()->all();
            $exerciseCounts = \App\Models\WorkoutTemplateExercise::whereIn('workout_template_blocks.workout_template_id', $templateIds)
                ->join('workout_template_blocks', 'workout_template_exercises.workout_template_block_id', '=', 'workout_template_blocks.id')
                ->selectRaw('workout_template_blocks.workout_template_id as wt_id, COUNT(*) as cnt')
                ->groupBy('workout_template_blocks.workout_template_id')
                ->pluck('cnt', 'wt_id');
            $hiddenCounts = \App\Models\ClientExerciseOverride::where('client_id', $client_id)
                ->whereIn('program_day_assignment_id', $assignmentIds)
                ->where('hidden', true)
                ->get()
                ->groupBy('program_day_assignment_id')
                ->map->count();

            // Motor de Auto-Regulación de Carga: qué workout_template_id de
            // este programa contienen al menos un exercise_id con
            // sugerencia -- una sola consulta por programa (no por día ni
            // por asignación), solo si hay alguna sugerencia real que
            // buscar.
            $templatesWithSuggestion = collect();
            if ($suggestionExerciseIds->isNotEmpty() && !empty($templateIds)) {
                $templatesWithSuggestion = \App\Models\WorkoutTemplateExercise::whereIn('workout_template_blocks.workout_template_id', $templateIds)
                    ->join('workout_template_blocks', 'workout_template_exercises.workout_template_block_id', '=', 'workout_template_blocks.id')
                    ->whereIn('workout_template_exercises.exercise_id', $suggestionExerciseIds)
                    ->select('workout_template_blocks.workout_template_id as wt_id')
                    ->distinct()
                    ->pluck('wt_id')
                    ->flip();
            }

            // AÑADIDO (pedido explícito 2026-09-18): thumbnail real de cada
            // plantilla, en lote (una sola consulta para todo el programa,
            // no una por asignación -- mismo criterio que $exerciseCounts
            // de arriba) para exponerlo en el calendario del cliente.
            $templateThumbnails = empty($templateIds)
                ? collect()
                : \Spatie\MediaLibrary\MediaCollections\Models\Media::where('model_type', \App\Models\WorkoutTemplate::class)
                    ->whereIn('model_id', $templateIds)
                    ->where('collection_name', 'image')
                    ->get()
                    ->groupBy('model_id')
                    ->map(fn ($group) => $group->first()->getUrl());

            // Entrenamientos personalizados que se repiten (misma serie
            // semanal, client_series_uuid) -- la app solo ofrece "borrar este
            // y los siguientes" cuando de verdad hay más de uno. Una sola
            // consulta por programa, y solo en el calendario personal.
            $seriesCounts = collect();
            if ($program->is_personal) {
                $uuids = $assignments->flatten()
                    ->map(fn ($a) => optional($a->workoutTemplate)->client_series_uuid)
                    ->filter()->unique()->values()->all();
                if (!empty($uuids)) {
                    $seriesCounts = \App\Models\WorkoutTemplate::where('created_by_client_id', $client_id)
                        ->whereIn('client_series_uuid', $uuids)
                        ->selectRaw('client_series_uuid, COUNT(*) as cnt')
                        ->groupBy('client_series_uuid')
                        ->pluck('cnt', 'client_series_uuid');
                }
            }

            foreach ($grid_dates as $date) {
                $wd = $mapper->toWeekAndDay($start_date, $date);
                if ($wd['week_number'] < 1 || $wd['week_number'] > $program->num_weeks) continue;

                $day_assignments = $assignments->get($wd['week_number'].'-'.$wd['day_of_week'], collect());

                foreach ($day_assignments as $a) {
                    if (!$a->workout_template_id) continue;

                    $totalEx = (int) $exerciseCounts->get($a->workout_template_id, 0);
                    $hiddenEx = (int) $hiddenCounts->get($a->id, 0);
                    if ($totalEx > 0 && $hiddenEx >= $totalEx) continue; // toda la sesión oculta para este cliente -> no listar

                    $days_map[$date->toDateString()]['workouts']->push([
                        'assignment_id' => $a->id, // = program_day_assignment_id, se usa para pedir el detalle del día
                        'id'            => $a->workout_template_id,
                        'title'         => optional($a->workoutTemplate)->title,
                        // AÑADIDO (pedido explícito 2026-09-18): antes este
                        // endpoint no exponía ningún thumbnail real de la
                        // plantilla -- el cliente (my_program_calendar_screen.tsx/
                        // schedule_screen.tsx) siempre caía en un fallback
                        // genérico de stock por palabra clave del título.
                        // Ahora que WorkoutTemplateController::update()
                        // permite subir/cambiar la imagen desde el panel
                        // admin, se puede devolver la real.
                        'image'         => $templateThumbnails->get($a->workout_template_id),
                        // Motor de Auto-Regulación de Carga: true si algún
                        // ejercicio de este entrenamiento tiene una
                        // sugerencia de carga pendiente o recién aplicada --
                        // el detalle real (peso/reps propuestos) se pide
                        // aparte en getDayDetail() cuando el cliente abre el
                        // entrenamiento.
                        // (2026-09-24) Nunca en un entrenamiento creado por el
                        // propio cliente: las sugerencias son sobre el plan
                        // del coach, y el motor no escribe en sus plantillas
                        // (ver SessionProgressionRuleEngine::
                        // resolveNextAssignmentForExercise()).
                        'has_load_suggestion' => optional($a->workoutTemplate)->created_by_client_id === null
                            && $templatesWithSuggestion->has($a->workout_template_id),
                        // AÑADIDO (2026-09-24): programa al que pertenece (la
                        // lista "Mi programa" abierta desde Home > Entrenamientos
                        // filtra por él) y si es un entrenamiento que creó el
                        // propio cliente (badge "Personalizado" + permiso de
                        // borrado, ver ClientCustomWorkoutController).
                        'training_program_id' => $program->id,
                        'is_personal'         => (bool) $program->is_personal,
                        'is_custom'           => optional($a->workoutTemplate)->created_by_client_id !== null
                            && (int) $a->workoutTemplate->created_by_client_id === (int) $client_id,
                        'is_repeating'        => (int) $seriesCounts->get(optional($a->workoutTemplate)->client_series_uuid ?? '', 0) > 1,
                    ]);
                }
            }
        }

        return json_custom_response(['data' => ['days' => $days_map->values()]]);
    }

    /**
     * CORREGIDO (IDOR): antes solo se validaba que el
     * program_day_assignment_id EXISTIERA (exists:program_day_assignments,id)
     * y se cargaba con find() sin comprobar de quién era — cualquier
     * cliente autenticado podía pasar el id de otro cliente y leer su día
     * de entrenamiento. Ahora se resuelve siempre a través de
     * resolveOwnedAssignment(), que aborta con 403/404 si no pertenece al
     * cliente autenticado.
     */
    public function getDayDetail(Request $request)
    {
        $request->validate(['program_day_assignment_id' => 'required|integer']);

        $client_id = auth('sanctum')->id();
        $assignment = $this->resolveOwnedAssignment((int) $request->program_day_assignment_id)
            ->load('workoutTemplate.blocks.exercises.exercise');

        if ($assignment->workout_template_id == null) {
            return json_custom_response(['data' => ['workout_day_id' => $assignment->id, 'sequence' => $assignment->day_of_week, 'is_rest' => 1, 'blocks' => []]]);
        }

        // El día existe pero su plantilla se borró desde el panel (soft
        // delete: workout_template_id sigue relleno pero la relación viene
        // null). Antes esto reventaba con un 500 más abajo
        // ($assignment->workoutTemplate->blocks) y la app lo trataba como un
        // fallo de red ("Reintentar", inútil). 404 = "ya no existe": la app
        // descarta la sesión en curso guardada y lo explica al cliente
        // (workout_session_screen.tsx, 2026-09-24).
        if ($assignment->workoutTemplate === null) {
            abort(404, 'Este entrenamiento ya no existe.');
        }

        // Batch-load overrides and logs BEFORE the loop
        $allOverrides = ClientExerciseOverride::where('program_day_assignment_id', $request->program_day_assignment_id)
            ->where('client_id', $client_id)
            ->with('exercise')
            ->get();

        // AISLAMIENTO (auditoría 2026-09-18): $allOverrides mezcla overrides
        // de un ejercicio real (workout_template_exercise_id NOT NULL, de
        // siempre) con "adiciones" propias de este cliente (NULL, ver
        // SessionDetailController::addExercise/addBlock en el admin) --
        // separarlas antes de usarlas, igual que ya hace
        // SessionDetailController::getSessionDetail().
        $overrides = $allOverrides->whereNotNull('workout_template_exercise_id')->keyBy('workout_template_exercise_id');
        $sharedBlockAdditions = $allOverrides->where('is_addition', true)->whereNotNull('workout_template_block_id')->groupBy('workout_template_block_id');

        $ownBlocks = ClientBlockOverride::where('program_day_assignment_id', $request->program_day_assignment_id)
            ->where('client_id', $client_id)
            ->with('exercises.exercise')
            ->orderBy('order')
            ->get();

        $additionExerciseIds = $allOverrides->where('is_addition', true)->pluck('exercise_id')->filter()->unique()->values()->all();

        $allExerciseIds = $assignment->workoutTemplate->blocks->flatMap(fn ($b) => $b->exercises->pluck('exercise_id')->values())
            ->merge($additionExerciseIds)
            ->unique()->values()->all();
        // orderByDesc('id'): 'created_at' es de precision de segundo y varias
        // series del mismo ejercicio pueden insertarse en el mismo segundo -
        // 'id' refleja el orden real de insercion sin empates.
        //
        // latestSnapshots + hasSets (2026-09-24): una sesión cuyo estado
        // final es "todas las series desmarcadas" (logged_sets = []) no
        // es la última vez que hizo el ejercicio -- ni esa foto vacía ni
        // las fotos anteriores de esa misma sesión deben usarse como
        // referencia (ver ClientExerciseLog::scopeLatestSnapshots()).
        $logs = ClientExerciseLog::where('client_id', $client_id)
            ->latestSnapshots($client_id)
            ->whereIn('exercise_id', $allExerciseIds)
            ->orderByDesc('id')
            ->get()
            ->filter(fn ($log) => $log->hasSets())
            ->unique('exercise_id')
            ->keyBy('exercise_id');

        // Últimas sesiones por ejercicio (no solo la última), para que la app
        // precargue la carga dentro del rango de reps prescrito.
        $recentPerformance = ClientExerciseLog::recentPerformanceFor((int) $client_id, collect($allExerciseIds)->all());

        // Motor de Auto-Regulación de Carga: sugerencia mas reciente por
        // ejercicio de ESTE entrenamiento -- reutiliza el mismo criterio
        // (pendiente siempre, aplicado dentro de la ventana) que
        // getMyMonth(), aqui con el valor completo (peso/reps propuestos,
        // regla) para pintar el detalle real en la ficha del ejercicio.
        // (2026-09-24) Sin sugerencias de carga en un entrenamiento creado
        // por el propio cliente -- mismo criterio que has_load_suggestion en
        // getMyMonth().
        $loadSuggestions = collect();
        if (optional($assignment->workoutTemplate)->created_by_client_id === null
            && Gate::forUser(auth('sanctum')->user())->allows('paid-tier')) {
            $loadSuggestions = NextSessionTarget::relevantForClient($client_id)
                ->whereIn('exercise_id', $allExerciseIds)
                ->with('rule')
                ->orderByDesc('generated_at')
                ->get()
                ->unique('exercise_id')
                ->keyBy('exercise_id');
        }

        $totalExercisesBefore = $assignment->workoutTemplate->blocks->sum(fn ($b) => $b->exercises->count());

        // Id sintético para lo que este cliente añadió (adiciones/bloques
        // propios) -- nunca existe como WorkoutTemplateExercise/Block real,
        // así que no hay un id positivo real que devolver. Offset grande y
        // negativo para no colisionar con el id sintético que la app móvil
        // ya usa para su "Añadir ejercicio +" ad-hoc en vivo (-exercise_id,
        // siempre un número pequeño) -- ver workout_session_screen.tsx.
        $syntheticId = fn (int $overrideId) => -(10_000_000 + $overrideId);

        // Renderiza UN ejercicio (real de la plantilla o adición propia) con
        // la misma forma que ya consume la app móvil -- extraído a closure
        // (auditoría 2026-09-18) para no duplicar esta lógica.
        $renderExercise = function (
            int $id,
            int $exerciseId,
            $exerciseModel,
            array $prescribed,
            ?string $notes,
            array $enabledMetrics,
            ?int $sequence,
            bool $isAddition
        ) use ($logs, $loadSuggestions, $recentPerformance) {
            $last_log = $logs->get($exerciseId);
            $thumb = $exerciseModel && $exerciseModel->video_url
                ? $this->youtubeThumbnail($exerciseModel->video_url)
                : getSingleMedia($exerciseModel, 'exercise_image', null);

            $suggestion = $loadSuggestions->get($exerciseId);

            return [
                'id'              => $id,
                'exercise_id'     => $exerciseId,
                // AÑADIDO (auditoría 2026-09-18): true si este ejercicio lo
                // añadió el coach solo para este cliente (no existe en la
                // plantilla compartida) -- la app lo trata igual que su
                // "Añadir ejercicio +" ad-hoc ya existente (registra series
                // por exercise_id, no por workout_template_exercise_id).
                'is_addition'     => $isAddition,
                'title'           => optional($exerciseModel)->title,
                'video_url'       => optional($exerciseModel)->video_url,
                'exercise_image'  => $thumb,
                'body_part_id'    => $this->primaryBodyPartId($exerciseModel),
                'sets'            => $prescribed,
                'coach_notes'     => $notes,
                'enabled_metrics' => $enabledMetrics,
                'last_performance' => $last_log ? ['sets' => $last_log->logged_sets] : null,
                'recent_performance' => $recentPerformance->get($exerciseId),
                'sequence'        => $sequence,
                'load_suggestion' => $suggestion ? [
                    'id'              => $suggestion->id,
                    'status'          => $suggestion->status->value,
                    'proposed_weight' => $suggestion->proposed_weight,
                    'proposed_reps'   => $suggestion->proposed_reps,
                    'resolved_at'     => optional($suggestion->resolved_at)->toIso8601String(),
                    'rule_name'       => optional($suggestion->rule)->name,
                ] : null,
            ];
        };

        $renderAddition = function ($addition) use ($renderExercise, $syntheticId) {
            return $renderExercise(
                $syntheticId($addition->id),
                $addition->exercise_id,
                $addition->exercise,
                $addition->prescribed_override ?? [],
                $addition->notes,
                $addition->enabled_metrics_override ?? [],
                $addition->sequence,
                true
            );
        };

        $blocks = $assignment->workoutTemplate->blocks->map(function ($block) use ($overrides, $sharedBlockAdditions, $renderExercise, $renderAddition) {
            $templateExercises = $block->exercises
                // Modo vida real (2026-08-12): un ejercicio con
                // override.hidden=true fue recortado (accesorio) o
                // pertenece a una sesión completa saltada por una semana
                // adaptativa aprobada -- no se le muestra a este cliente,
                // aunque otros clientes con el mismo workout_template lo
                // sigan viendo intacto.
                ->reject(fn ($ex) => (bool) ($overrides->get($ex->id)->hidden ?? false))
                ->map(function ($ex) use ($overrides, $renderExercise) {
                    $override = $overrides->get($ex->id);
                    // Defensivo: contenido creado fuera del panel admin normal
                    // (import directo, datos legacy) puede dejar 'prescribed'
                    // con una forma inesperada (ej. doblemente codificado en
                    // JSON, decodificando a un string en vez de un array) -
                    // eso rompia esta pantalla por completo con un 500 en vez
                    // de simplemente mostrar el ejercicio sin datos prescritos.
                    $basePrescribed = is_array($ex->prescribed) ? $ex->prescribed : [];
                    $overridePrescribed = is_array($override->prescribed_override ?? null) ? $override->prescribed_override : [];
                    $prescribed = array_merge($basePrescribed, $overridePrescribed);

                    return $renderExercise(
                        $ex->id,
                        $ex->exercise_id,
                        $ex->exercise,
                        $prescribed,
                        $override->notes ?? null,
                        $ex->enabled_metrics ?? [],
                        $ex->sequence,
                        false
                    );
                });

            // AÑADIDO (auditoría 2026-09-18): ejercicios que este cliente
            // añadió a este bloque compartido -- nunca tocan
            // workout_template_exercises, solo existen para él.
            $additions = ($sharedBlockAdditions->get($block->id) ?? collect())
                ->sortBy('sequence')
                ->map($renderAddition);

            return [
                'block_id' => $block->id,
                'title'    => $block->title,
                'order'    => $block->order,
                'exercises' => $templateExercises->concat($additions)->values(),
            ];
        })->filter(fn ($block) => count($block['exercises']) > 0)->values();

        $totalExercisesAfter = $blocks->sum(fn ($b) => count($b['exercises']));
        // is_adjusted: distinto de is_rest -- is_rest es a nivel de
        // program_day_assignment (plantilla compartida por todos los
        // clientes del programa); is_adjusted es SOLO para este cliente,
        // producto de una semana adaptativa que le aplicó de verdad. Se
        // calcula ANTES de añadir bloques/adiciones propias (justo debajo)
        // para que un cliente con un ejercicio propio añadido no "tape" una
        // semana adaptativa real que sí saltó el resto de la sesión.
        $isAdjusted = $totalExercisesBefore > 0 && $totalExercisesAfter === 0;

        // AÑADIDO (auditoría 2026-09-18): bloques enteros que este cliente
        // añadió -- no existen en la plantilla compartida, se listan después
        // de los bloques compartidos.
        $ownBlockEntries = $ownBlocks->map(function ($block) use ($renderAddition, $syntheticId) {
            return [
                'block_id'  => $syntheticId($block->id),
                'title'     => $block->title,
                'order'     => $block->order,
                'exercises' => $block->exercises->sortBy('sequence')->map($renderAddition)->values(),
            ];
        })->filter(fn ($block) => count($block['exercises']) > 0)->values();

        $blocks = $blocks->concat($ownBlockEntries)->values();

        return json_custom_response([
            'data' => [
                'workout_day_id' => $assignment->id,
                'sequence'       => $assignment->day_of_week,
                'is_rest'        => 0,
                'is_adjusted'    => $isAdjusted,
                'blocks'         => $blocks,
            ],
        ]);
    }

    public function logSets(Request $request)
    {
        try {
            $this->validateLogSets($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Diagnóstico (2026-09-26, caso Ayoub): antes un guardado de series
            // rechazado por validación (ejercicio que ya no existe, session_key
            // mal formada...) no dejaba ningún rastro en el servidor, así que
            // "el cliente dice que las rellenó" no se podía comprobar.
            \Log::warning('[logSets] guardado de series rechazado por validación', [
                'client_id'                    => auth('sanctum')->id(),
                'workout_template_exercise_id' => $request->workout_template_exercise_id,
                'exercise_id'                  => $request->exercise_id,
                'program_day_assignment_id'    => $request->program_day_assignment_id,
                'session_key'                  => $request->session_key,
                'sets_count'                   => is_array($request->logged_sets) ? count($request->logged_sets) : null,
                'errors'                       => $e->errors(),
            ]);
            throw $e;
        }

        return $this->storeLoggedSets($request);
    }

    private function validateLogSets(Request $request): void
    {
        $request->validate([
            'workout_template_exercise_id' => 'nullable|exists:workout_template_exercises,id',
            'exercise_id'                   => 'required_without:workout_template_exercise_id|nullable|exists:exercises,id',
            // 'present' y no 'required' (2026-09-24): 'required' rechaza un
            // array vacío, así que cuando el cliente desmarcaba TODAS las
            // series de un ejercicio la app no podía registrarlo y la última
            // foto (con series) seguía contando en volumen/estadísticas.
            // Ahora [] se guarda como una foto más: 0 series (ver
            // ClientExerciseLog::scopeLatestSnapshots()).
            'logged_sets'                   => 'present|array',
            'program_day_assignment_id'     => 'nullable|integer',
            // Id de sesión que genera la app al empezar el entrenamiento;
            // separa sesiones sueltas del mismo día (ver migración
            // 2026_09_24_120000 y scopeLatestSnapshots()). Opcional: las
            // versiones viejas de la app no lo mandan.
            'session_key'                   => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_:\-]+$/'],
            'notes'                         => 'nullable|string|max:2000',
        ]);
    }

    private function storeLoggedSets(Request $request)
    {
        // CORREGIDO (IDOR): antes se guardaba directamente el
        // program_day_assignment_id recibido (solo validado con
        // exists:program_day_assignments,id) sin comprobar que fuera del
        // propio cliente — el log en sí ya quedaba scoped por client_id,
        // pero quedaba enlazado al día de entrenamiento de otro cliente.
        // Se resuelve siempre vía resolveOwnedAssignment() cuando viene.
        if ($request->program_day_assignment_id) {
            $this->resolveOwnedAssignment((int) $request->program_day_assignment_id);
        }

        $wte = null;
        if ($request->workout_template_exercise_id) {
            $wte = \App\Models\WorkoutTemplateExercise::find($request->workout_template_exercise_id);
            $allowed_keys = $wte->enabled_metrics ?? [];
            $exercise_id = $wte->exercise_id;
        } else {
            // Ejercicio anadido ad-hoc durante la sesion ("Anadir ejercicio +"),
            // sin WorkoutTemplateExercise que lo respalde - no tiene
            // enabled_metrics propio, se acepta cualquier clave real del
            // catalogo de metricas en vez de filtrar por una lista vacia.
            $allowed_keys = \App\Models\Metric::pluck('key')->toArray();
            $exercise_id = $request->exercise_id;
        }

        $clean_sets = collect($request->logged_sets)->map(function ($set) use ($allowed_keys) {
            // `tecnica` y `partes` (bajadas / mini-series) no son métricas del
            // catálogo: se validan aparte (LoggedSetMath, 2026-10-03).
            return collect($set)->only($allowed_keys)->toArray()
                + \App\Support\LoggedSetMath::sanitizeExtras(is_array($set) ? $set : []);
        })->toArray();

        // Versiones de la app sin `tecnica` por serie: se toma del prescrito
        // (plantilla + override del cliente) para que los récords sepan qué
        // series se apuntaron como "total de repeticiones".
        if ($wte) {
            $prescribed = is_array($wte->prescribed) ? $wte->prescribed : [];
            if ($request->program_day_assignment_id) {
                $override = \App\Models\ClientExerciseOverride::where('program_day_assignment_id', $request->program_day_assignment_id)
                    ->where('client_id', auth('sanctum')->id())
                    ->where('workout_template_exercise_id', $wte->id)
                    ->first();
                if (is_array($override->prescribed_override ?? null)) {
                    $prescribed = array_merge($prescribed, $override->prescribed_override);
                }
            }
            $clean_sets = \App\Support\LoggedSetMath::annotate(array_values($clean_sets), $prescribed);
        }

        // RIR/RPE obligatorio (uno u otro) al registrar una serie
        // completada -- solo se exige si el ejercicio tiene alguno de los
        // dos habilitado en enabled_metrics (si todavía no se ha
        // reetiquetado ninguna plantilla vieja, no bloquea nada nuevo).
        $intensityEnabled = in_array('rir', $allowed_keys, true) || in_array('rpe', $allowed_keys, true);
        if ($intensityEnabled) {
            foreach ($clean_sets as $index => $set) {
                $hasReps = isset($set['reps']) && $set['reps'] !== '' && $set['reps'] !== null;
                $hasCarga = isset($set['carga']) && $set['carga'] !== '' && $set['carga'] !== null;
                $hasRir = isset($set['rir']) && $set['rir'] !== '' && $set['rir'] !== null;
                $hasRpe = isset($set['rpe']) && $set['rpe'] !== '' && $set['rpe'] !== null;

                if ($hasReps && $hasCarga && !$hasRir && !$hasRpe) {
                    \Log::warning('[logSets] serie rechazada: falta RIR/RPE', [
                        'client_id'                    => auth('sanctum')->id(),
                        'workout_template_exercise_id' => $request->workout_template_exercise_id,
                        'exercise_id'                  => $exercise_id,
                        'session_key'                  => $request->session_key,
                        'set_index'                    => $index,
                    ]);
                    return json_message_response(
                        "La serie " . ($index + 1) . " necesita RIR o RPE para poder guardarse.",
                        422
                    );
                }
            }
        }

        $log = ClientExerciseLog::create([
            'client_id'                     => auth('sanctum')->id(),
            'workout_template_exercise_id'  => $request->workout_template_exercise_id,
            'exercise_id'                   => $exercise_id,
            'program_day_assignment_id'     => $request->program_day_assignment_id,
            'session_key'                   => $request->session_key,
            'performed_date'                => now()->toDateString(),
            // array_values: [] tiene que guardarse como lista JSON "[]",
            // nunca como objeto.
            'logged_sets'                   => array_values($clean_sets),
            'notes'                         => $request->notes,
        ]);

        return json_custom_response(['data' => $log]);
    }

    /**
     * Cierre de sesion desde la app cliente (pantalla de Feedback al
     * terminar un entrenamiento). Reutiliza la misma tabla
     * workout_session_reviews que ya usa el review de coach/admin
     * (updateOrCreate) - si el coach deja luego su propio
     * difficulty_rating/comment, actualiza la misma fila en vez de crear
     * una duplicada. Acepta program_day_assignment_id (dia de programa
     * asignado) o workout_template_id (Workout suelto sin programa) -
     * al menos uno de los dos.
     */
    public function finishSession(Request $request)
    {
        $request->validate([
            'program_day_assignment_id' => 'nullable|required_without:workout_template_id|exists:program_day_assignments,id',
            'workout_template_id'       => 'nullable|required_without:program_day_assignment_id|exists:workout_templates,id',
            'duration_seconds'          => 'nullable|integer|min:0',
            'volume_kg'                 => 'nullable|numeric|min:0',
            'difficulty_rating'         => 'nullable|integer|min:1|max:5',
            'comment'                   => 'nullable|string',
            // IDs reales de ejercicio (no workout_template_exercise_id) tocados
            // en esta sesion - se usan solo para calcular los logros del
            // resumen (comparar contra la sesion anterior), no se persisten.
            'exercise_ids'              => 'nullable|array',
            'exercise_ids.*'            => 'integer',
        ]);

        $user = auth('sanctum')->user();
        $match = ['user_id' => $user->id];
        if ($request->program_day_assignment_id) {
            $match['program_day_assignment_id'] = $request->program_day_assignment_id;
        } else {
            $match['workout_template_id'] = $request->workout_template_id;
        }

        $caloriesBurned = WorkoutSessionStatsService::computeCalories($user, (int) ($request->duration_seconds ?? 0));

        $review = WorkoutSessionReview::updateOrCreate(
            $match,
            [
                'duration_seconds'  => $request->duration_seconds,
                'volume_kg'         => $request->volume_kg,
                'calories_burned'   => $caloriesBurned,
                'difficulty_rating' => $request->difficulty_rating,
                'comment'           => $request->comment,
                'completed_at'      => now(),
            ]
        );

        // Finalizo sin apuntar ninguna serie -> aviso al coach (Panel de
        // Excepciones + push). Nunca debe romper el cierre de la sesion.
        try {
            app(\App\Services\EmptySessionAlertService::class)->evaluate($review);
        } catch (\Throwable $e) {
            report($e);
        }

        $achievements = WorkoutSessionStatsService::computeAchievements($user->id, $request->exercise_ids ?? []);

        // Motor de Auto-Regulación de Carga (Fase 1) — gate de tier
        // comprobado una única vez, en este punto de entrada (documento
        // §0.3): un cliente free nunca genera exercise_session_metrics.
        // El bloqueo por dolor (pain_reports) NO pasa por este gate, vive
        // en su propio endpoint/observer y sigue funcionando igual para
        // todos los clientes.
        if (Gate::forUser($user)->allows('paid-tier')) {
            // (2026-09-24) Entrenamiento creado por el PROPIO cliente
            // (ClientCustomWorkoutController): el prescrito lo tecleó él, no
            // su coach -- no se interpreta ni se evalúan reglas de
            // progresión (Fases 1-2), o cada sesión personalizada llenaría la
            // cola de sugerencias pendientes del coach con propuestas sobre
            // un plan que no es suyo. Todo lo demás (review, calorías,
            // logros de sesión, racha/compliance) sigue igual.
            if (!$review->isClientCustomSession()) {
                ProcessSessionInterpretation::dispatch($review);
                // Motor de Auto-Regulación de Carga (Fase 2) — corre justo
                // después (QUEUE_CONNECTION=sync -> en línea, en orden), ya
                // que depende de las exercise_session_metrics que el job
                // anterior acaba de generar. Mismo gate, no se repite.
                EvaluateSessionProgressionRules::dispatch($review);
            }

            // Fase 3 (documento §3.2/reconciliación): $achievements ya se
            // calculaba pero era efímero (solo en esta response) — se
            // persiste ahora en achievement_events (tipo progreso_sesion,
            // AÑADIDO: evidencia sesión-vs-sesión-anterior, distinta de un
            // PR real all-time, que ya se cubre aparte en
            // ClientExerciseLogObserver vía pr_carga/mejora_e1rm/pr_reps).
            $this->persistSessionProgressAchievements($user->id, $achievements);

            // Racha de sesiones + hito de compliance (documento §3.2) — job
            // aparte porque no depende de datos efímeros del request (solo
            // client_id), corre justo después, mismo gate.
            EvaluateSessionAchievements::dispatch($review);
        }

        return json_custom_response([
            'data' => $review,
            'achievements' => $achievements,
        ]);
    }

    /**
     * NUEVO: reorganizar el calendario semanal desde la app cliente
     * ("Guardar cambios" tras arrastrar entrenamientos entre días) —
     * POST v1/my-calendar-move-assignments, payload
     * { moves: [{ assignment_id, to_date }] }.
     *
     * Cada assignment_id se resuelve vía resolveOwnedAssignment() (mismo
     * fix de IDOR que getDayDetail/logSets). Solo se permite mover DENTRO
     * de la misma semana ISO en la que ya estaba el entrenamiento — se
     * recalcula (week_number, day_of_week) para to_date con el mismo
     * CalendarDateMapper que usa el resto del calendario, y se rechaza si
     * el week_number cambia (eso sería reprogramar a otra semana, no
     * reorganizar la semana actual). Se actualizan week_number/day_of_week
     * (lo que getMyMonth realmente usa para agrupar los días) y también
     * scheduled_date, por consistencia con el resto de "mover" ya
     * existentes (ver RealCalendarController::moveAssignment()). Todo el
     * lote se aplica en una única transacción: o se guardan todos los
     * moves, o ninguno.
     */
    public function moveAssignments(Request $request)
    {
        $request->validate([
            'moves'                  => 'required|array|min:1',
            'moves.*.assignment_id'  => 'required|integer',
            'moves.*.to_date'        => 'required|date',
        ]);

        $client_id = auth('sanctum')->id();
        $mapper = new CalendarDateMapper();

        $updated = DB::transaction(function () use ($request, $client_id, $mapper) {
            $results = [];

            foreach ($request->moves as $move) {
                $assignment = $this->resolveOwnedAssignment((int) $move['assignment_id']);

                $client_assignment = ProgramClientAssignment::where('client_id', $client_id)
                    ->where('training_program_id', $assignment->training_program_id)
                    ->where('activo', true)
                    ->first();

                if (!$client_assignment) {
                    // Ya lo comprobó resolveOwnedAssignment(), pero por
                    // seguridad ante una carrera (desactivación entre medias).
                    abort(403, 'No tienes acceso a este entrenamiento.');
                }

                $start_date = Carbon::parse($client_assignment->start_date);
                $to_date = Carbon::parse($move['to_date']);
                $wd = $mapper->toWeekAndDay($start_date, $to_date);

                if ($wd['week_number'] !== (int) $assignment->week_number) {
                    abort(422, 'Solo puedes reorganizar entrenamientos dentro de la misma semana — esa fecha cae en otra semana distinta.');
                }

                $assignment->update([
                    'day_of_week'    => $wd['day_of_week'],
                    'scheduled_date' => $to_date->toDateString(),
                ]);

                $results[] = $assignment;
            }

            return $results;
        });

        return json_custom_response(['data' => $updated]);
    }

    /**
     * Fase 3 (documento §3.2/reconciliación) — persiste en achievement_events
     * las mejoras sesión-vs-sesión-anterior que WorkoutSessionStatsService::
     * computeAchievements() ya detecta (weight_up/reps_up/better_rpe,
     * extendidos con *_details para incluir exercise_id/value/previous_best
     * de forma aditiva, ver WorkoutSessionStatsService). Solo se llama
     * desde dentro del bloque ya gateado a paid-tier en finishSession().
     */
    private function persistSessionProgressAchievements(int $clientId, array $achievements): void
    {
        foreach (['weight_up_details', 'reps_up_details', 'better_rpe_details'] as $key) {
            foreach ($achievements[$key] ?? [] as $detail) {
                AchievementEvent::create([
                    'client_id'                 => $clientId,
                    'type'                       => AchievementEventType::PROGRESO_SESION->value,
                    'exercise_id'                => $detail['exercise_id'],
                    'value'                      => $detail['value'],
                    'previous_best'              => $detail['previous_best'],
                    'significancia_verificada'   => true,
                ]);
            }
        }
    }

    /**
     * Volumen por grupo muscular del cliente autenticado — para la sección
     * progreso semanal/mensual y para las pantallas de Estadísticas
     * (selector de día / navegador de semana usan end_date para fijar el
     * final de la ventana de $days en vez de asumir "hasta hoy").
     */
    public function getMyMuscleVolume(Request $request)
    {
        $user = auth('sanctum')->user();
        $days = (int) $request->input('days', 30);
        $multiplierEnabled = $request->boolean('multiplier_enabled', true);
        $endDate = $request->input('end_date');

        $data = MuscleVolumeService::computeForClient($user->id, $days, $multiplierEnabled, $endDate);

        return json_custom_response(['data' => $data]);
    }

    /**
     * KPIs de sesion (entrenamientos completados, duracion y volumen) en una
     * ventana de $days terminando en $endDate (hoy si no se especifica) —
     * usa workout_session_reviews (una fila por sesion cerrada via
     * finishSession/finishCalendarSession) en vez de client_exercise_logs
     * porque es la fuente real de "sesion completada" y duracion; el
     * recuento de series se saca aparte de MuscleVolumeService, que si lee
     * las series sueltas. Pantalla Estadisticas (grid 2x2 de KPIs).
     */
    public function getMyPeriodStats(Request $request)
    {
        $user = auth('sanctum')->user();
        $days = (int) $request->input('days', 30);
        $end = $request->input('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay() : Carbon::now();
        $start = $days > 0 ? $end->copy()->subDays($days - 1)->startOfDay() : Carbon::createFromTimestamp(0);

        $row = WorkoutSessionReview::where('user_id', $user->id)
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('COUNT(*) as sessions, COALESCE(SUM(duration_seconds), 0) as duration, COALESCE(SUM(volume_kg), 0) as volume')
            ->first();

        // avgDurationSeconds = duracion MEDIA por sesion (SUM/COUNT), pedido
        // por el usuario para la tarjeta "Duracion" de la pantalla
        // "Distribucion de los musculos" - antes esa tarjeta mostraba
        // durationSeconds (el TOTAL sumado del periodo), que crecia sin
        // limite cuanto mas largo el rango y no representaba la duracion
        // tipica de un entrenamiento. Se mantiene durationSeconds (total)
        // para no romper otras pantallas que ya lo consumen como total
        // (ej. Informe mensual).
        $avgDuration = $row->sessions > 0 ? (int) round($row->duration / $row->sessions) : 0;

        return json_custom_response(['data' => [
            'sessionsCount' => (int) $row->sessions,
            'durationSeconds' => (int) $row->duration,
            'avgDurationSeconds' => $avgDuration,
            'volumeKg' => round((float) $row->volume, 2),
        ]]);
    }

    /**
     * Adherencia de entrenamiento — % de sesiones programadas realmente
     * completadas en una ventana de días, reusando a propósito el mismo
     * patrón que Habit::getCurrentStreakAttribute/HabitController::
     * getClientProgress (racha de días consecutivos + ratio de cumplimiento)
     * en vez de inventar un cálculo nuevo, tal y como pidió el usuario.
     *
     * Dos modos, según si el cliente tiene un TrainingProgram real asignado:
     * - 'program': cruza los días programados (resueltos con el mismo
     *   CalendarDateMapper que ya usa getMyMonth) contra WorkoutSessionReview
     *   por program_day_assignment_id.
     * - 'freeform': cliente sin programa asignado (mayoría de clientes free)
     *   — no hay "programado" con qué comparar, así que solo se cuentan
     *   sesiones reales completadas + racha de días consecutivos con
     *   entrenamiento, igual de válido pero sin ratio de cumplimiento.
     */
    public function getMyAdherence(Request $request)
    {
        $days = min((int) $request->input('days', 30), 90);

        return json_custom_response(['data' => self::computeAdherence(auth('sanctum')->id(), $days)]);
    }

    /**
     * Extraído a estático para que Admin también pueda pedir la adherencia
     * de un client_id explícito (ver ClientProfileCalendarController::
     * getAdherence) sin duplicar esta lógica — mismo cálculo, mismo
     * resultado, visto por el cliente o por el coach.
     */
    /**
     * AÑADIDO 2026-08-12 ($asOf): Score de Riesgo de Abandono necesita
     * "compliance de la ventana de 4 semanas terminando hace 4 semanas"
     * (compliance_anterior), no solo "terminando hoy" -- parámetro opcional
     * con default null=now() para no romper ninguna llamada existente
     * (racha de sesiones, hito_compliance, endpoint HTTP de adherencia).
     */
    public static function computeAdherence(int $userId, int $days, ?Carbon $asOf = null): array
    {
        $end = ($asOf ?? Carbon::now())->copy()->startOfDay();
        $start = $end->copy()->subDays($days - 1);

        $assignments = ProgramClientAssignment::where('client_id', $userId)
            ->where('activo', true)
            ->with('trainingProgram')
            ->get()
            ->filter(fn ($a) => $a->trainingProgram !== null);

        if ($assignments->isEmpty()) {
            $sessions = WorkoutSessionReview::where('user_id', $userId)
                ->whereBetween('completed_at', [$start, $end->copy()->endOfDay()])
                ->orderBy('completed_at')
                ->get(['completed_at']);

            $sessionDates = $sessions->map(fn ($s) => $s->completed_at->toDateString())->unique()->flip();

            $streak = 0;
            $cursor = $end->copy();
            while ($sessionDates->has($cursor->toDateString())) {
                $streak++;
                $cursor->subDay();
            }

            return [
                'mode' => 'freeform',
                'sessionsCount' => $sessions->count(),
                'daysActive' => $sessionDates->count(),
                'currentStreak' => $streak,
                'periodDays' => $days,
            ];
        }

        $mapper = new CalendarDateMapper();
        $scheduled = []; // 'Y-m-d' => program_day_assignment_id

        foreach ($assignments as $assignment) {
            $program = $assignment->trainingProgram;
            $startDate = Carbon::parse($assignment->start_date);

            // coachPlanned() (2026-09-24): los entrenamientos que se crea el
            // propio cliente (ClientCustomWorkoutController) NO son sesiones
            // programadas -- saltarse uno nunca baja la adherencia, ni corta
            // la racha, ni sube el riesgo de abandono (compliance de
            // RetentionRiskCalculationService) ni cuenta para hito_compliance
            // (EvaluateSessionAchievements), que salen todos de aquí. Hacer
            // uno tampoco sube el ratio (no está en el denominador ni en el
            // numerador). Como actividad sí cuenta donde se mide "entrenó
            // hace poco": el modo freeform de abajo y la inactividad del
            // riesgo de abandono leen workout_session_reviews sin filtrar.
            // Además evita que un personalizado el mismo día que una sesión
            // del coach "tape" a esta en $scheduled (una entrada por fecha).
            $dayRows = ProgramDayAssignment::where('training_program_id', $program->id)
                ->coachPlanned()
                ->whereNotNull('workout_template_id')
                ->get()
                ->groupBy(fn ($a) => $a->week_number.'-'.$a->day_of_week);

            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $wd = $mapper->toWeekAndDay($startDate, $cursor);
                if ($wd['week_number'] >= 1 && $wd['week_number'] <= $program->num_weeks) {
                    foreach ($dayRows->get($wd['week_number'].'-'.$wd['day_of_week'], collect()) as $row) {
                        $scheduled[$cursor->toDateString()] = $row->id;
                    }
                }
                $cursor->addDay();
            }
        }

        if (empty($scheduled)) {
            return [
                'mode' => 'program', 'scheduledCount' => 0, 'completedCount' => 0,
                'ratio' => null, 'currentStreak' => 0, 'periodDays' => $days, 'days' => [],
            ];
        }

        $completedAssignmentIds = WorkoutSessionReview::where('user_id', $userId)
            ->whereIn('program_day_assignment_id', array_unique(array_values($scheduled)))
            ->pluck('program_day_assignment_id')
            ->flip();

        ksort($scheduled);
        $dayLog = [];
        foreach ($scheduled as $date => $assignmentId) {
            $dayLog[] = ['date' => $date, 'completed' => $completedAssignmentIds->has($assignmentId)];
        }

        $completedCount = collect($dayLog)->where('completed', true)->count();

        $streak = 0;
        foreach (array_reverse($dayLog) as $d) {
            if (!$d['completed']) break;
            $streak++;
        }

        return [
            'mode' => 'program',
            'scheduledCount' => count($dayLog),
            'completedCount' => $completedCount,
            'ratio' => round($completedCount / count($dayLog), 4),
            'currentStreak' => $streak,
            'periodDays' => $days,
            'days' => $dayLog,
        ];
    }

    /**
     * Ranking de ejercicios por frecuencia en una ventana de $days
     * terminando en $endDate — pantalla "Ejercicios principales" de
     * Estadísticas. No existía nada reutilizable (a diferencia de
     * my-muscle-volume/my-period-stats): agrega client_exercise_logs por
     * exercise_id contando filas (=sesiones en las que aparece) y series
     * totales. Se agrega en PHP en vez de SQL (JSON_LENGTH) porque el
     * volumen de filas por cliente/ventana es pequeño y evita atarse al
     * driver de BD.
     */
    public function getMyTopExercises(Request $request)
    {
        $user = auth('sanctum')->user();
        $days = (int) $request->input('days', 30);
        $end = $request->input('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay() : Carbon::now();
        $limit = (int) $request->input('limit', 20);
        $bodyPartId = $request->input('body_part_id') !== null ? (int) $request->input('body_part_id') : null;

        // latestSnapshots: una fila por ejercicio y sesión (ver ClientExerciseLog),
        // si no "sesiones" y "series" salían multiplicadas.
        $query = ClientExerciseLog::where('client_id', $user->id)->latestSnapshots($user->id);
        $query->where('performed_date', '<=', $end->toDateString());
        if ($days > 0) {
            $query->where('performed_date', '>=', $end->copy()->subDays($days - 1)->toDateString());
        }
        $logs = $query->get(['exercise_id', 'logged_sets']);

        $agg = [];
        foreach ($logs as $log) {
            // Sesión que acabó con todas las series desmarcadas
            // (logged_sets = [], 2026-09-24): ese ejercicio no se hizo --
            // ni suma series ni cuenta como sesión.
            if (!$log->hasSets()) {
                continue;
            }
            $agg[$log->exercise_id]['sessions'] = ($agg[$log->exercise_id]['sessions'] ?? 0) + 1;
            $agg[$log->exercise_id]['sets'] = ($agg[$log->exercise_id]['sets'] ?? 0) + count($log->logged_sets ?? []);
        }

        $exercises = Exercise::whereIn('id', array_keys($agg))->get(['id', 'title', 'bodypart_ids'])->keyBy('id');

        $data = [];
        foreach ($agg as $exerciseId => $counts) {
            $exercise = $exercises->get($exerciseId);
            if (!$exercise) {
                continue;
            }
            if ($bodyPartId !== null && MuscleVolumeService::firstBodyPartId($exercise->bodypart_ids) !== $bodyPartId) {
                continue;
            }
            $data[] = [
                'exercise_id' => $exerciseId,
                'title'       => $exercise->title,
                'image'       => getSingleMedia($exercise, 'exercise_image', null),
                'sessions'    => $counts['sessions'],
                'sets'        => $counts['sets'],
            ];
        }
        usort($data, fn ($a, $b) => $b['sessions'] <=> $a['sessions'] ?: $b['sets'] <=> $a['sets']);

        // Sin filtro de musculo, se recorta al limite (paginado simple de la
        // pantalla "Ejercicios principales"); filtrando por musculo se
        // devuelven todos los que coincidan, sin recortar.
        $result = $bodyPartId !== null ? array_values($data) : array_slice(array_values($data), 0, $limit);

        return json_custom_response(['data' => $result]);
    }

    /**
     * Datos que "my-period-stats" y "my-muscle-volume" no cubren para el
     * Informe mensual: lista de sesiones completadas ese mes (con titulo
     * resuelto via workout_template, sea directo o via
     * program_day_assignment) y los PRs conseguidos ese mes concreto — para
     * "ejercicios con progreso" / "cuantos PR se han hecho".
     */
    public function getMyMonthlyExtras(Request $request)
    {
        $user = auth('sanctum')->user();
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        $reviews = WorkoutSessionReview::where('user_id', $user->id)
            ->whereBetween('completed_at', [$start, $end])
            ->with('programDayAssignment')
            ->orderBy('completed_at')
            ->get();

        $templateIds = $reviews
            ->map(fn ($r) => $r->workout_template_id ?? optional($r->programDayAssignment)->workout_template_id)
            ->filter()
            ->unique();
        $templateTitles = WorkoutTemplate::whereIn('id', $templateIds)->pluck('title', 'id');

        $sessions = $reviews->map(function ($r) use ($templateTitles) {
            $templateId = $r->workout_template_id ?? optional($r->programDayAssignment)->workout_template_id;
            return [
                'date'             => $r->completed_at->toDateString(),
                'duration_seconds' => (int) $r->duration_seconds,
                'volume_kg'        => (float) $r->volume_kg,
                'title'            => $templateId ? ($templateTitles[$templateId] ?? 'Entrenamiento') : 'Entrenamiento',
            ];
        })->values();

        $prRows = PersonalRecord::where('user_id', $user->id)
            ->whereBetween('achieved_at', [$start, $end])
            ->orderByDesc('achieved_at')
            ->get(['exercise_id', 'record_type', 'value', 'achieved_at']);

        $exerciseTitles = Exercise::whereIn('id', $prRows->pluck('exercise_id')->unique())->pluck('title', 'id');

        $prEvents = $prRows->map(fn ($p) => [
            'exercise_id'  => $p->exercise_id,
            'title'        => $exerciseTitles[$p->exercise_id] ?? 'Ejercicio',
            'record_type'  => $p->record_type,
            'value'        => (float) $p->value,
            'achieved_at'  => optional($p->achieved_at)->toDateString(),
        ])->values();

        return json_custom_response(['data' => [
            'sessions'  => $sessions,
            'prEvents'  => $prEvents,
        ]]);
    }

    /**
     * Calculo puntual de volumen por grupo muscular a partir de una lista de
     * sets dada directamente en el request (no lee client_exercise_logs) —
     * pensado para el heatmap justo al terminar una sesion, cuando la app ya
     * tiene en memoria exactamente que se registro sin ambiguedad de fechas.
     */
    public function computeMuscleVolume(Request $request)
    {
        $request->validate([
            'sets'               => 'required|array|min:1',
            'sets.*.exercise_id' => 'required|integer|exists:exercises,id',
            'sets.*.weight'      => 'nullable|numeric|min:0',
            'sets.*.reps'        => 'nullable|integer|min:0',
        ]);

        $multiplierEnabled = $request->boolean('multiplier_enabled', true);
        $bodyweightKg = optional(auth('sanctum')->user()?->userProfile)->weight_in_kg;
        $bodyweightKg = is_numeric($bodyweightKg) ? (float) $bodyweightKg : null;
        $data = MuscleVolumeService::computeVolume($request->input('sets'), $multiplierEnabled, $bodyweightKg);

        return json_custom_response(['data' => $data]);
    }

    /**
     * Primer body_part.id de exercises.bodypart_ids, tolerando filas legacy
     * donde el mutator guardo un escalar suelto (ej. `"2"`) en vez del array
     * `[2]` esperado - decodificar ese JSON da un string/int, no un array,
     * asi que is_array() por si solo descartaba silenciosamente esos casos
     * (visto en el catalogo real: exercise #12 "Peso muerto").
     */
    private function primaryBodyPartId($exercise): ?int
    {
        if (!$exercise) return null;
        $ids = $exercise->bodypart_ids;
        if (is_array($ids)) return isset($ids[0]) ? (int) $ids[0] : null;
        if (is_numeric($ids)) return (int) $ids;
        return null;
    }
}
