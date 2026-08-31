<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
use App\Models\ClientExerciseLog;
use App\Models\WorkoutSessionReview;
use App\Models\Exercise;
use App\Models\WorkoutTemplate;
use App\Models\PersonalRecord;
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

        // Batch-load overrides and logs BEFORE the loop
        $overrides = \App\Models\ClientExerciseOverride::where('program_day_assignment_id', $request->program_day_assignment_id)
            ->where('client_id', $client_id)
            ->get()
            ->keyBy('workout_template_exercise_id');

        $allExerciseIds = $assignment->workoutTemplate->blocks->flatMap(fn ($b) => $b->exercises->pluck('exercise_id')->values())->unique()->values()->all();
        // orderByDesc('id'): 'created_at' es de precision de segundo y varias
        // series del mismo ejercicio pueden insertarse en el mismo segundo -
        // 'id' refleja el orden real de insercion sin empates.
        $logs = ClientExerciseLog::where('client_id', $client_id)
            ->whereIn('exercise_id', $allExerciseIds)
            ->orderByDesc('id')
            ->get()
            ->unique('exercise_id')
            ->keyBy('exercise_id');

        $totalExercisesBefore = $assignment->workoutTemplate->blocks->sum(fn ($b) => $b->exercises->count());

        $blocks = $assignment->workoutTemplate->blocks->map(function ($block) use ($overrides, $logs) {
            return [
                'block_id' => $block->id,
                'title'    => $block->title,
                'order'    => $block->order,
                // Modo vida real (2026-08-12): un ejercicio con
                // override.hidden=true fue recortado (accesorio) o
                // pertenece a una sesión completa saltada por una semana
                // adaptativa aprobada -- no se le muestra a este cliente,
                // aunque otros clientes con el mismo workout_template lo
                // sigan viendo intacto.
                'exercises' => $block->exercises->reject(fn ($ex) => (bool) ($overrides->get($ex->id)->hidden ?? false))->map(function ($ex) use ($overrides, $logs) {
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

                    $last_log = $logs->get($ex->exercise_id);
                    $exercise = $ex->exercise;
                    $thumb = $exercise && $exercise->video_url
                        ? $this->youtubeThumbnail($exercise->video_url)
                        : getSingleMedia($exercise, 'exercise_image', null);

                    return [
                        'id'              => $ex->id,
                        'exercise_id'     => $ex->exercise_id,
                        'title'           => optional($exercise)->title,
                        'video_url'       => optional($exercise)->video_url,
                        'exercise_image'  => $thumb,
                        // Primer body_part de exercises.bodypart_ids — para pintar el
                        // heatmap de musculo aislado en la tarjeta (ExerciseThumb),
                        // mismo criterio de "primario" que ya usa ExerciseInfoController.
                        'body_part_id'    => $this->primaryBodyPartId($exercise),
                        'sets'            => $prescribed,
                        'coach_notes'     => $override->notes ?? null,
                        'enabled_metrics' => $ex->enabled_metrics ?? [],
                        'last_performance' => $last_log ? ['sets' => $last_log->logged_sets] : null,
                        'sequence'        => $ex->sequence,
                    ];
                })->values(),
            ];
        })->filter(fn ($block) => count($block['exercises']) > 0)->values();

        $totalExercisesAfter = $blocks->sum(fn ($b) => count($b['exercises']));
        // is_adjusted: distinto de is_rest -- is_rest es a nivel de
        // program_day_assignment (plantilla compartida por todos los
        // clientes del programa); is_adjusted es SOLO para este cliente,
        // producto de una semana adaptativa que le aplicó de verdad.
        $isAdjusted = $totalExercisesBefore > 0 && $totalExercisesAfter === 0;

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
        $request->validate([
            'workout_template_exercise_id' => 'nullable|exists:workout_template_exercises,id',
            'exercise_id'                   => 'required_without:workout_template_exercise_id|nullable|exists:exercises,id',
            'logged_sets'                   => 'required|array',
            'program_day_assignment_id'     => 'nullable|integer',
            'notes'                         => 'nullable|string|max:2000',
        ]);

        // CORREGIDO (IDOR): antes se guardaba directamente el
        // program_day_assignment_id recibido (solo validado con
        // exists:program_day_assignments,id) sin comprobar que fuera del
        // propio cliente — el log en sí ya quedaba scoped por client_id,
        // pero quedaba enlazado al día de entrenamiento de otro cliente.
        // Se resuelve siempre vía resolveOwnedAssignment() cuando viene.
        if ($request->program_day_assignment_id) {
            $this->resolveOwnedAssignment((int) $request->program_day_assignment_id);
        }

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
            return collect($set)->only($allowed_keys)->toArray();
        })->toArray();

        $log = ClientExerciseLog::create([
            'client_id'                     => auth('sanctum')->id(),
            'workout_template_exercise_id'  => $request->workout_template_exercise_id,
            'exercise_id'                   => $exercise_id,
            'program_day_assignment_id'     => $request->program_day_assignment_id,
            'performed_date'                => now()->toDateString(),
            'logged_sets'                   => $clean_sets,
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

        $achievements = WorkoutSessionStatsService::computeAchievements($user->id, $request->exercise_ids ?? []);

        // Motor de Auto-Regulación de Carga (Fase 1) — gate de tier
        // comprobado una única vez, en este punto de entrada (documento
        // §0.3): un cliente free nunca genera exercise_session_metrics.
        // El bloqueo por dolor (pain_reports) NO pasa por este gate, vive
        // en su propio endpoint/observer y sigue funcionando igual para
        // todos los clientes.
        if (Gate::forUser($user)->allows('paid-tier')) {
            ProcessSessionInterpretation::dispatch($review);
            // Motor de Auto-Regulación de Carga (Fase 2) — corre justo
            // después (QUEUE_CONNECTION=sync -> en línea, en orden), ya
            // que depende de las exercise_session_metrics que el job
            // anterior acaba de generar. Mismo gate, no se repite.
            EvaluateSessionProgressionRules::dispatch($review);

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

            $dayRows = ProgramDayAssignment::where('training_program_id', $program->id)
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

        $query = ClientExerciseLog::where('client_id', $user->id);
        $query->where('performed_date', '<=', $end->toDateString());
        if ($days > 0) {
            $query->where('performed_date', '>=', $end->copy()->subDays($days - 1)->toDateString());
        }
        $logs = $query->get(['exercise_id', 'logged_sets']);

        $agg = [];
        foreach ($logs as $log) {
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
