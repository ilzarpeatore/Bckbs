<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Metric;
use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
use App\Models\WorkoutSessionReview;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateBlock;
use App\Models\WorkoutTemplateExercise;
use App\Services\CalendarDateMapper;
use Carbon\Carbon;
use App\Traits\HasYoutubeThumbnail;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Entrenamientos personalizados creados por el PROPIO cliente desde la app
 * (pantalla "Crear entrenamiento personalizado", pedido 2026-09-24).
 *
 * No hay un modelo paralelo: cada sesión es un workout_template normal
 * (bloques = secciones, ejercicios con su prescrito y métricas) colgado de
 * un program_day_assignment en el calendario personal del cliente -- el
 * mismo que usa el coach con ClientProfileCalendarController::assignDirect().
 * Así la sesión en vivo, logSets()/finishSession(), el feedback, el
 * historial, las estadísticas y el calendario del coach funcionan sin
 * ningún cambio. created_by_client_id marca que lo creó el cliente.
 */
class ClientCustomWorkoutController extends Controller
{
    use HasYoutubeThumbnail;

    /** Tope de repeticiones semanales ("repetir todos los lunes") por creación. */
    const MAX_REPEAT_WEEKS = 52;

    /** Tope de ejercicios por entrenamiento (todas las secciones juntas). */
    const MAX_EXERCISES = 60;

    /**
     * POST v1/my-custom-workouts
     *
     * {
     *   "title": "Pierna en casa",
     *   "date": "2026-09-28",
     *   "repeat_weeks": 8,            // opcional, 1 = sin repetir
     *   "blocks": [
     *     { "title": "Calentamiento", "exercises": [
     *       { "exercise_id": 12, "prescribed": {"series": 3, "reps": "10", "carga": "20", "descanso": "90", "rir": "2"},
     *         "enabled_metrics": ["reps", "carga", "descanso", "rir"], "notes": null }
     *     ]}
     *   ]
     * }
     */
    public function store(Request $request)
    {
        $today = Carbon::today();
        // El servidor va en UTC y el cliente en su hora local: el lunes
        // local puede ser todavía domingo en UTC (o al revés). Se calcula la
        // semana en curso desde AYER para no rechazar el lunes local de un
        // cliente que va por delante/detrás de UTC; sigue sin permitir
        // semanas pasadas reales.
        $minDate = $today->copy()->subDay()->startOfWeek(Carbon::MONDAY)->toDateString();

        $request->validate(array_merge([
            // Idempotencia: la app manda un id por pantalla de creación. Si
            // la petición se repite (timeout de red y el usuario vuelve a
            // pulsar Guardar) se devuelve lo ya creado en vez de duplicarlo.
            'client_request_id'                 => ['nullable', 'string', 'max:36', 'regex:/^[A-Za-z0-9_-]+$/'],
            'date'                              => 'required|date_format:Y-m-d|after_or_equal:'.$minDate.'|before_or_equal:'.$today->copy()->addYear()->toDateString(),
            'repeat_weeks'                      => 'nullable|integer|min:1|max:'.self::MAX_REPEAT_WEEKS,
        ], $this->workoutRules()), array_merge([
            'date.required'          => 'Elige un día para el entrenamiento.',
            'date.date_format'       => 'La fecha no es válida.',
            'date.after_or_equal'    => 'Solo puedes crear entrenamientos a partir de la semana en curso.',
            'date.before_or_equal'   => 'Solo puedes planificar hasta un año vista.',
            'repeat_weeks.max'       => 'Puedes repetirlo como máximo '.self::MAX_REPEAT_WEEKS.' semanas.',
            'repeat_weeks.integer'   => 'El número de semanas no es válido.',
            'repeat_weeks.min'       => 'El número de semanas no es válido.',
        ], $this->workoutMessages()));

        $normalizedBlocks = $this->normalizeBlocks((array) $request->blocks);

        $user = auth('sanctum')->user();
        $repeatWeeks = (int) ($request->repeat_weeks ?? 1);
        $firstDate = Carbon::parse($request->date);
        $requestId = $request->client_request_id;
        // Siempre hay un id de serie: el que manda la app (idempotencia) o
        // uno nuevo. Con 1 sola semana simplemente agrupa una única fila.
        $seriesUuid = $requestId ?: (string) Str::uuid();
        $replayed = false;

        $created = DB::transaction(function () use ($user, $request, $normalizedBlocks, $repeatWeeks, $firstDate, $seriesUuid, $requestId, &$replayed) {
            // Serializa las creaciones de un mismo cliente (bloqueo de su
            // fila de users): dos peticiones simultáneas con el mismo
            // client_request_id no pueden pasar las dos la comprobación de
            // abajo, ni crear dos calendarios personales a la vez.
            \App\Models\User::whereKey($user->id)->lockForUpdate()->first();

            if ($requestId) {
                $existing = ProgramDayAssignment::whereHas('workoutTemplate', fn ($q) => $q
                        ->where('client_series_uuid', $requestId)
                        ->where('created_by_client_id', $user->id))
                    ->orderBy('scheduled_date')
                    ->get();
                if ($existing->isNotEmpty()) {
                    $replayed = true;
                    return $existing->map(fn ($a) => [
                        'assignment_id'       => $a->id,
                        'workout_template_id' => $a->workout_template_id,
                        'date'                => Carbon::parse($a->scheduled_date)->toDateString(),
                    ])->all();
                }
            }

            $program = ClientProfileCalendarController::getOrCreatePersonalProgram($user->id, $user->coach_id ?: $user->id);
            $mapper = new CalendarDateMapper();
            $anchor = Carbon::parse(ClientProfileCalendarController::PERSONAL_ANCHOR_DATE);

            $out = [];
            for ($i = 0; $i < $repeatWeeks; $i++) {
                $date = $firstDate->copy()->addWeeks($i);
                $template = $this->createTemplate($user, $request->title, $normalizedBlocks, $seriesUuid);
                $wd = $mapper->toWeekAndDay($anchor, $date);

                $assignment = ProgramDayAssignment::create([
                    'training_program_id' => $program->id,
                    'week_number'         => $wd['week_number'],
                    'day_of_week'         => $wd['day_of_week'],
                    'workout_template_id' => $template->id,
                    'scheduled_date'      => $date->toDateString(),
                ]);

                $out[] = [
                    'assignment_id'       => $assignment->id,
                    'workout_template_id' => $template->id,
                    'date'                => $date->toDateString(),
                ];
            }

            return $out;
        });

        $repeatWeeks = count($created);

        return json_custom_response([
            'replayed' => $replayed,
            'message' => $repeatWeeks > 1
                ? "Entrenamiento creado y repetido durante {$repeatWeeks} semanas."
                : 'Entrenamiento creado.',
            'data' => [
                'series_uuid' => $seriesUuid,
                'assignments' => $created,
            ],
        ]);
    }

    /**
     * POST v1/my-custom-workouts-delete
     * { "program_day_assignment_id": 123, "scope": "single" | "following" }
     *
     * Solo lo que creó el propio cliente (nunca lo que asignó su coach) y
     * solo ocurrencias sin completar -- el historial real no se toca.
     * "following" borra esta y todas las repeticiones posteriores de la
     * misma serie semanal.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'program_day_assignment_id' => 'required|integer',
            'scope'                     => 'nullable|in:single,following',
        ]);

        $clientId = auth('sanctum')->id();
        $assignment = ProgramDayAssignment::with('workoutTemplate', 'trainingProgram')->find($request->program_day_assignment_id);

        if (!$assignment
            || !$assignment->trainingProgram
            || !$assignment->trainingProgram->is_personal
            || (int) $assignment->trainingProgram->personal_client_id !== (int) $clientId
            || !$assignment->workoutTemplate
            || (int) $assignment->workoutTemplate->created_by_client_id !== (int) $clientId) {
            return json_message_response('Solo puedes eliminar entrenamientos que hayas creado tú.', 403);
        }

        $targets = collect([$assignment]);
        $seriesUuid = $assignment->workoutTemplate->client_series_uuid;
        if ($request->scope === 'following' && $seriesUuid) {
            $targets = ProgramDayAssignment::with('workoutTemplate')
                ->where('training_program_id', $assignment->training_program_id)
                ->where('scheduled_date', '>=', $assignment->scheduled_date)
                ->whereHas('workoutTemplate', fn ($q) => $q
                    ->where('client_series_uuid', $seriesUuid)
                    ->where('created_by_client_id', $clientId))
                ->get();
        }

        $completedIds = WorkoutSessionReview::where('user_id', $clientId)
            ->whereIn('program_day_assignment_id', $targets->pluck('id'))
            ->pluck('program_day_assignment_id')
            ->flip();

        $deleted = 0;
        DB::transaction(function () use ($targets, $completedIds, &$deleted) {
            foreach ($targets as $t) {
                if ($completedIds->has($t->id)) continue;
                $template = $t->workoutTemplate;
                $t->delete();
                $template?->delete();
                $deleted++;
            }
        });

        if ($deleted === 0) {
            return json_message_response('Este entrenamiento ya está completado y no se puede eliminar.', 422);
        }

        return json_custom_response([
            'message' => $deleted > 1 ? "Se han eliminado {$deleted} entrenamientos." : 'Entrenamiento eliminado.',
            'data'    => ['deleted' => $deleted],
        ]);
    }

    /**
     * GET v1/my-custom-workout-detail?program_day_assignment_id=123
     *
     * Estructura completa de un entrenamiento personalizado para abrirlo en
     * el editor de la app (2026-09-24). Solo su dueño: calendario personal
     * del cliente autenticado Y plantilla creada por él (nunca lo que le
     * asignó su coach) -> si no, 403; si no existe, 404.
     *
     * { "data": { "assignment_id", "date", "title", "is_repeating",
     *   "is_completed", "blocks": [ { "title", "exercises": [ { "exercise_id",
     *   "title", "image", "prescribed", "enabled_metrics", "notes" } ] } ] } }
     */
    public function detail(Request $request)
    {
        $request->validate(['program_day_assignment_id' => 'required|integer']);

        $clientId = (int) auth('sanctum')->id();
        $assignment = $this->resolveOwnedCustomAssignment((int) $request->program_day_assignment_id, $clientId);
        $template = $assignment->workoutTemplate->load([
            'blocks' => fn ($q) => $q->orderBy('order'),
            'blocks.exercises' => fn ($q) => $q->orderBy('sequence'),
            'blocks.exercises.exercise',
        ]);

        $seriesUuid = $template->client_series_uuid;
        $isRepeating = $seriesUuid !== null && WorkoutTemplate::where('created_by_client_id', $clientId)
            ->where('client_series_uuid', $seriesUuid)
            ->count() > 1;

        return json_custom_response(['data' => [
            'assignment_id' => $assignment->id,
            'date'          => $assignment->scheduled_date ? Carbon::parse($assignment->scheduled_date)->toDateString() : null,
            'title'         => $template->title,
            'is_repeating'  => $isRepeating,
            'is_completed'  => WorkoutSessionReview::where('user_id', $clientId)
                ->where('program_day_assignment_id', $assignment->id)
                ->exists(),
            'blocks' => $template->blocks->map(fn ($block) => [
                'title'     => $block->title,
                'exercises' => $block->exercises->map(fn ($ex) => [
                    'exercise_id'     => (int) $ex->exercise_id,
                    'title'           => optional($ex->exercise)->title,
                    'image'           => $this->exerciseImage($ex->exercise),
                    'prescribed'      => is_array($ex->prescribed) ? (object) $ex->prescribed : (object) [],
                    'enabled_metrics' => is_array($ex->enabled_metrics) ? array_values($ex->enabled_metrics) : [],
                    'notes'           => $ex->notes,
                ])->values(),
            ])->values(),
        ]]);
    }

    /**
     * POST v1/my-custom-workouts-update
     *
     * { "program_day_assignment_id": 123, "scope": "single" | "following",
     *   "title": "...", "blocks": [ misma forma que store() ] }
     *
     * Sustituye el título y TODA la estructura (secciones + ejercicios) de
     * esa ocurrencia; con scope=following también la de cada ocurrencia
     * POSTERIOR de la misma serie semanal (client_series_uuid,
     * scheduled_date >= esta). La fecha no se edita aquí. Mismas reglas,
     * normalización, límites y mensajes que store() (workoutRules() /
     * workoutMessages() / normalizeBlocks() compartidos).
     *
     * - Nunca toca una ocurrencia ya completada (con WorkoutSessionReview):
     *   se salta y se cuenta en skipped_completed. Si TODAS lo están -> 422.
     * - Cada ocurrencia conserva su propia fila de plantilla (nunca se
     *   comparten plantillas entre asignaciones, mismo criterio que store()
     *   y cloneStructure()).
     * - Bloques/ejercicios viejos: soft delete (WorkoutTemplateBlock y
     *   WorkoutTemplateExercise usan SoftDeletes) y se crean los nuevos,
     *   todo en una transacción. NO se borran de verdad porque
     *   client_exercise_logs.workout_template_exercise_id tiene FK con
     *   onDelete('cascade'): un borrado físico se llevaría por delante las
     *   series ya registradas de una sesión empezada y sin cerrar. Con soft
     *   delete esas filas siguen existiendo (y el historial/volumen que las
     *   lee por exercise_id sigue viéndolas). Los ClientExerciseOverride que
     *   apuntaran a los ejercicios viejos quedan huérfanos e inofensivos
     *   (getDayDetail solo recorre los ejercicios vivos de la plantilla).
     *
     * 200: { "message": "Entrenamiento actualizado." | "Se han actualizado N
     *   entrenamientos.", "data": { "updated": N, "skipped_completed": M } }
     */
    public function update(Request $request)
    {
        $request->validate(array_merge([
            'program_day_assignment_id' => 'required|integer',
            'scope'                     => 'nullable|in:single,following',
        ], $this->workoutRules()), array_merge([
            'scope.in' => 'Revisa los datos del entrenamiento.',
        ], $this->workoutMessages()));

        $clientId = (int) auth('sanctum')->id();
        $assignment = $this->resolveOwnedCustomAssignment((int) $request->program_day_assignment_id, $clientId);
        $normalizedBlocks = $this->normalizeBlocks((array) $request->blocks);

        $targets = collect([$assignment]);
        $seriesUuid = $assignment->workoutTemplate->client_series_uuid;
        if ($request->scope === 'following' && $seriesUuid) {
            $targets = ProgramDayAssignment::with('workoutTemplate')
                ->where('training_program_id', $assignment->training_program_id)
                ->where('scheduled_date', '>=', $assignment->scheduled_date)
                ->whereHas('workoutTemplate', fn ($q) => $q
                    ->where('client_series_uuid', $seriesUuid)
                    ->where('created_by_client_id', $clientId))
                ->get();
        }

        $completedIds = WorkoutSessionReview::where('user_id', $clientId)
            ->whereIn('program_day_assignment_id', $targets->pluck('id'))
            ->pluck('program_day_assignment_id')
            ->flip();

        $editable = $targets->reject(fn ($t) => $completedIds->has($t->id))->values();
        $skipped = $targets->count() - $editable->count();

        if ($editable->isEmpty()) {
            return json_message_response('Este entrenamiento ya está completado y no se puede editar.', 422);
        }

        $title = $request->title;
        DB::transaction(function () use ($editable, $title, $normalizedBlocks) {
            foreach ($editable as $t) {
                $template = $t->workoutTemplate;
                $template->update(['title' => $title]);

                $oldBlocks = WorkoutTemplateBlock::where('workout_template_id', $template->id)->get();
                WorkoutTemplateExercise::whereIn('workout_template_block_id', $oldBlocks->pluck('id'))->delete();
                WorkoutTemplateBlock::whereIn('id', $oldBlocks->pluck('id'))->delete();

                $this->createBlocks($template, $normalizedBlocks);
            }
        });

        $updated = $editable->count();

        return json_custom_response([
            'message' => $updated > 1 ? "Se han actualizado {$updated} entrenamientos." : 'Entrenamiento actualizado.',
            'data'    => ['updated' => $updated, 'skipped_completed' => $skipped],
        ]);
    }

    /**
     * Asignación de un entrenamiento personalizado del propio cliente, o
     * aborta: 404 si no existe, 403 si no es suyo (otro cliente, o algo que
     * le asignó su coach en su calendario personal).
     */
    private function resolveOwnedCustomAssignment(int $assignmentId, int $clientId): ProgramDayAssignment
    {
        $assignment = ProgramDayAssignment::with('workoutTemplate', 'trainingProgram')->find($assignmentId);
        if (!$assignment) {
            abort(response()->json(['message' => 'Este entrenamiento ya no existe.'], 404));
        }

        if (!$assignment->trainingProgram
            || !$assignment->trainingProgram->is_personal
            || (int) $assignment->trainingProgram->personal_client_id !== $clientId
            || !$assignment->workoutTemplate
            || (int) $assignment->workoutTemplate->created_by_client_id !== $clientId) {
            abort(response()->json(['message' => 'Solo puedes editar entrenamientos que hayas creado tú.'], 403));
        }

        return $assignment;
    }

    /**
     * Imagen del ejercicio para el editor: la misma imagen subida que
     * devuelve el catálogo (exercise-list, ExerciseResource: colección
     * 'exercise_image'); si no tiene, la miniatura de su vídeo de YouTube
     * (mismo criterio que my-calendar-day-detail); si tampoco, null (no la
     * imagen genérica por defecto de getSingleMedia()).
     */
    private function exerciseImage($exercise): ?string
    {
        if (!$exercise) return null;

        $media = $exercise->getFirstMedia('exercise_image');
        if ($media && file_exists($media->getPath())) {
            return $media->getFullUrl();
        }

        return $exercise->video_url ? $this->youtubeThumbnail($exercise->video_url) : null;
    }

    /**
     * Reglas de título + secciones + ejercicios, compartidas por store() y
     * update() (un único sitio para límites y formato).
     */
    private function workoutRules(): array
    {
        return [
            'title'                             => 'required|string|max:120',
            'blocks'                            => 'required|array|min:1|max:20',
            'blocks.*.title'                    => 'nullable|string|max:120',
            'blocks.*.exercises'                => 'present|array|max:40',
            'blocks.*.exercises.*.exercise_id'  => 'required|integer|exists:exercises,id',
            'blocks.*.exercises.*.prescribed'   => 'nullable|array',
            'blocks.*.exercises.*.enabled_metrics'   => 'nullable|array',
            'blocks.*.exercises.*.enabled_metrics.*' => 'string',
            'blocks.*.exercises.*.notes'        => 'nullable|string|max:1000',
        ];
    }

    private function workoutMessages(): array
    {
        return [
            'title.required'         => 'El entrenamiento necesita un nombre.',
            'title.max'              => 'El nombre es demasiado largo (máximo 120 caracteres).',
            'blocks.required'        => 'Añade al menos una sección con ejercicios.',
            'blocks.max'             => 'Como máximo puedes tener 20 secciones.',
            'blocks.*.exercises.max' => 'Como máximo puedes tener 40 ejercicios por sección.',
            'blocks.*.exercises.*.exercise_id.exists' => 'Uno de los ejercicios ya no existe. Quítalo y vuelve a añadirlo.',
            // Genéricos (el locale del backend es 'en'): cualquier otro fallo
            // de validación se muestra en castellano en la app.
            'required'               => 'Revisa los datos del entrenamiento: falta información.',
            'present'                => 'Revisa los datos del entrenamiento: falta información.',
            'array'                  => 'Revisa los datos del entrenamiento.',
            'string'                 => 'Revisa los datos del entrenamiento.',
            'integer'                => 'Revisa los datos del entrenamiento.',
            'regex'                  => 'Petición no válida. Cierra y vuelve a abrir el creador.',
            'max'                    => 'Alguno de los textos es demasiado largo.',
            'exists'                 => 'Uno de los ejercicios ya no existe. Quítalo y vuelve a añadirlo.',
        ];
    }

    /**
     * Secciones ya validadas -> estructura lista para createBlocks():
     * descarta secciones vacías, aplica el tope total de ejercicios,
     * filtra métricas/claves de prescrito y normaliza cada valor. Corta la
     * petición con 422 (mensaje en castellano) si no queda ningún ejercicio
     * o se pasa del tope. Compartido por store() y update().
     */
    private function normalizeBlocks(array $rawBlocks): array
    {
        $blocks = collect($rawBlocks)
            ->map(fn ($b) => ['title' => $b['title'] ?? null, 'exercises' => array_values($b['exercises'] ?? [])])
            ->filter(fn ($b) => count($b['exercises']) > 0)
            ->values();

        if ($blocks->isEmpty()) {
            throw new HttpResponseException(json_message_response('Añade al menos un ejercicio al entrenamiento.', 422));
        }
        if ($blocks->sum(fn ($b) => count($b['exercises'])) > self::MAX_EXERCISES) {
            throw new HttpResponseException(json_message_response('Como máximo puedes tener '.self::MAX_EXERCISES.' ejercicios en un entrenamiento.', 422));
        }

        $allowedMetrics = Metric::pluck('key')->all();
        // Mismas claves que ya usa el prescrito del coach (ver
        // workout_session_screen.tsx: METRIC_DISPLAY_RANK). 'series' no es
        // una métrica registrable, solo el nº de filas iniciales.
        $allowedPrescribedKeys = array_unique(array_merge($allowedMetrics, ['series']));

        $normalizedBlocks = [];
        foreach ($blocks as $bIdx => $block) {
            $exercises = [];
            foreach ($block['exercises'] as $eIdx => $ex) {
                $metrics = array_values(array_intersect((array) ($ex['enabled_metrics'] ?? []), $allowedMetrics));
                if ($metrics === []) {
                    $metrics = ['reps', 'carga', 'descanso', 'rir'];
                } elseif (!in_array('rir', $metrics, true) && !in_array('rpe', $metrics, true)) {
                    // Misma regla que WorkoutTemplateController::saveExercise():
                    // sensación subjetiva obligatoria (la usa el motor de carga).
                    $metrics[] = 'rir';
                }

                $prescribed = collect((array) ($ex['prescribed'] ?? []))
                    ->only($allowedPrescribedKeys)
                    ->map(fn ($v, $k) => self::normalizePrescribedValue((string) $k, $v))
                    ->filter(fn ($v) => $v !== null)
                    ->all();
                $prescribed['series'] = $prescribed['series'] ?? '3';

                $exercises[] = [
                    'exercise_id'     => (int) $ex['exercise_id'],
                    'sequence'        => $eIdx + 1,
                    'prescribed'      => $prescribed,
                    'enabled_metrics' => $metrics,
                    'notes'           => $ex['notes'] ?? null,
                ];
            }
            $normalizedBlocks[] = [
                'title'     => trim((string) ($block['title'] ?? '')) ?: 'Sección '.($bIdx + 1),
                'order'     => $bIdx + 1,
                'exercises' => $exercises,
            ];
        }

        return $normalizedBlocks;
    }

    /**
     * GET v1/my-active-programs[?today=Y-m-d]
     *
     * Programas asignados por el coach (sin el calendario personal) para la
     * sección "Entrenamientos" del Home: nombre, semana en curso y sesiones
     * de esta semana (hechas / totales). Cada item lleva "kind": "program".
     *
     * AÑADIDO (2026-09-24) "Plan de tu entrenador": un cliente 1:1 al que su
     * coach solo le asigna sesiones sueltas (assignDirect() en su calendario
     * personal, sin programa) no veía nada aquí. Si en la semana en curso
     * (lunes-domingo) su calendario personal tiene al menos una sesión que
     * NO se creó él mismo, se añade UN item extra al final:
     *   { "kind": "coach_calendar", "program_client_assignment_id": <pca
     *     personal>, "training_program_id": <programa personal>, "title":
     *     "Plan de tu entrenador", "start_date": <inicio del pca personal>,
     *     "end_date": null, "num_weeks": 0, "current_week": 0,
     *     "sessions_this_week": N, "completed_this_week": M }
     * N/M cuentan solo esas sesiones del coach (coachPlanned()), nunca los
     * personalizados del cliente. El entrenamiento demo que se asigna al
     * registrarse (is_demo, UserController::assignDemoWorkoutIfNeeded) no es
     * un plan del coach y tampoco cuenta.
     *
     * ?today (2026-09-24): el servidor va en UTC y la semana se calculaba
     * con su fecha -- el lunes de madrugada en España todavía era domingo en
     * UTC y se mostraba la semana anterior. La app manda su fecha local; se
     * usa solo si está a ±1 día de la fecha UTC del servidor (cualquier zona
     * horaria real cae ahí); si no, o si viene mal formada, se ignora y se
     * usa la del servidor, sin error: el Home nunca debe romperse por esto.
     */
    public function activePrograms(Request $request)
    {
        $clientId = auth('sanctum')->id();
        $today = self::resolveLocalToday($request->query('today'));
        $mapper = new CalendarDateMapper();

        $assignments = ProgramClientAssignment::where('client_id', $clientId)
            ->where('activo', true)
            ->whereHas('trainingProgram', fn ($q) => $q->where('is_personal', false))
            ->with('trainingProgram')
            ->orderByDesc('start_date')
            ->get();

        $items = $assignments->map(function ($ca) use ($clientId, $today, $mapper) {
            $program = $ca->trainingProgram;
            $start = Carbon::parse($ca->start_date);
            $wd = $mapper->toWeekAndDay($start, $today);
            $started = $today->gte($start->copy()->startOfWeek(Carbon::MONDAY));
            $currentWeek = $started ? max(1, min((int) $program->num_weeks, $wd['week_number'])) : 0;

            $weekIds = $currentWeek > 0
                ? ProgramDayAssignment::where('training_program_id', $program->id)
                    ->where('week_number', $currentWeek)
                    ->whereNotNull('workout_template_id')
                    ->pluck('id')
                : collect();

            return [
                'kind'                         => 'program',
                'program_client_assignment_id' => $ca->id,
                'training_program_id'          => $program->id,
                'title'                        => $program->title ?: 'Mi programa',
                'start_date'                   => Carbon::parse($ca->start_date)->toDateString(),
                'end_date'                     => $ca->fecha_fin ? Carbon::parse($ca->fecha_fin)->toDateString() : null,
                'num_weeks'                    => (int) $program->num_weeks,
                'current_week'                 => $currentWeek,
                'sessions_this_week'           => $weekIds->count(),
                'completed_this_week'          => self::countCompleted($clientId, $weekIds),
            ];
        })->values();

        $coachCalendar = $this->coachCalendarItem($clientId, $today, $mapper);
        if ($coachCalendar !== null) {
            $items->push($coachCalendar);
        }

        return json_custom_response(['data' => $items]);
    }

    /**
     * Item "Plan de tu entrenador" (ver activePrograms()) o null si esta
     * semana su calendario personal no tiene ninguna sesión del coach.
     */
    private function coachCalendarItem(int $clientId, Carbon $today, CalendarDateMapper $mapper): ?array
    {
        $personalCa = ProgramClientAssignment::where('client_id', $clientId)
            ->where('activo', true)
            ->whereHas('trainingProgram', fn ($q) => $q
                ->where('is_personal', true)
                ->where('personal_client_id', $clientId))
            ->orderBy('id')
            ->first();
        if (!$personalCa) {
            return null;
        }

        // Mismo cálculo de semana que getMyMonth() para este calendario
        // (week_number desde el start_date del pca, ancla fija lunes).
        $weekNumber = $mapper->toWeekAndDay(Carbon::parse($personalCa->start_date), $today)['week_number'];

        $weekIds = ProgramDayAssignment::where('training_program_id', $personalCa->training_program_id)
            ->where('week_number', $weekNumber)
            ->whereNotNull('workout_template_id')
            ->coachPlanned()
            ->whereDoesntHave('workoutTemplate', fn ($t) => $t->where('is_demo', true))
            ->pluck('id');
        if ($weekIds->isEmpty()) {
            return null;
        }

        return [
            'kind'                         => 'coach_calendar',
            'program_client_assignment_id' => $personalCa->id,
            'training_program_id'          => $personalCa->training_program_id,
            'title'                        => 'Plan de tu entrenador',
            'start_date'                   => Carbon::parse($personalCa->start_date)->toDateString(),
            'end_date'                     => null,
            'num_weeks'                    => 0,
            'current_week'                 => 0,
            'sessions_this_week'           => $weekIds->count(),
            'completed_this_week'          => self::countCompleted($clientId, $weekIds),
        ];
    }

    private static function countCompleted(int $clientId, $assignmentIds): int
    {
        if (collect($assignmentIds)->isEmpty()) {
            return 0;
        }

        return WorkoutSessionReview::where('user_id', $clientId)
            ->whereIn('program_day_assignment_id', $assignmentIds)
            ->distinct('program_day_assignment_id')
            ->count('program_day_assignment_id');
    }

    /**
     * Fecha "de hoy" del cliente: la que manda la app (Y-m-d, su zona
     * horaria) si está a ±1 día de la fecha UTC del servidor; si no, la del
     * servidor. Ver activePrograms().
     */
    public static function resolveLocalToday($candidate): Carbon
    {
        $serverToday = Carbon::today();
        if (!is_string($candidate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate)) {
            return $serverToday;
        }

        try {
            $local = Carbon::createFromFormat('!Y-m-d', $candidate);
        } catch (\Throwable $e) {
            return $serverToday;
        }
        // createFromFormat acepta desbordes ("2026-02-31" -> 3 de marzo):
        // solo vale si la fecha re-formateada es exactamente la recibida.
        if (!$local || $local->format('Y-m-d') !== $candidate) {
            return $serverToday;
        }

        return abs($serverToday->diffInDays($local, false)) <= 1 ? $local->startOfDay() : $serverToday;
    }

    /**
     * Deja cada valor prescrito en un formato que la sesión en vivo, el
     * registro de series y el motor de carga saben leer -- aunque llegue de
     * una versión vieja de la app o de un cliente manipulado. null = se
     * descarta la clave.
     */
    public static function normalizePrescribedValue(string $key, $value): ?string
    {
        if (!is_scalar($value)) return null;
        $raw = trim(str_replace(',', '.', (string) $value));
        if ($raw === '') return null;

        $number = function (string $v, float $min, float $max, int $decimals): ?string {
            if (!preg_match('/^\d+(\.\d+)?$/', $v)) return null;
            $n = round(min($max, max($min, (float) $v)), $decimals);
            $out = number_format($n, $decimals, '.', '');
            // Quita ceros decimales sobrantes ("22.50" -> "22.5", "60.00" ->
            // "60") -- solo si hay parte decimal: "120" debe seguir siendo 120.
            return str_contains($out, '.') ? rtrim(rtrim($out, '0'), '.') : $out;
        };

        switch ($key) {
            case 'series':
                return preg_match('/^\d+$/', $raw) ? (string) max(1, min(20, (int) $raw)) : '3';
            case 'reps':
                // "10" o rango "8-10" (el orden se corrige); cualquier otra
                // cosa se queda con su primer número, o se descarta.
                if (preg_match('/^(\d{1,3})\s*-\s*(\d{1,3})$/', $raw, $m)) {
                    [$a, $b] = [max(1, (int) $m[1]), max(1, (int) $m[2])];
                    return $a === $b ? (string) $a : min($a, $b).'-'.max($a, $b);
                }
                return preg_match('/\d{1,3}/', $raw, $m) ? (string) max(1, (int) $m[0]) : null;
            case 'carga':
                return $number($raw, 0, 1000, 2);
            case 'descanso':
            case 'tiempo':
                if (preg_match('/^(\d{1,3}):(\d{1,2})$/', $raw, $m)) {
                    $raw = (string) ((int) $m[1] * 60 + (int) $m[2]);
                }
                return $number($raw, 0, $key === 'descanso' ? 3600 : 36000, 0);
            case 'rir':
                return $number($raw, 0, 10, 1);
            case 'rpe':
                return $number($raw, 1, 10, 1);
            default:
                return mb_substr($raw, 0, 20);
        }
    }

    private function createTemplate($user, string $title, array $blocks, ?string $seriesUuid): WorkoutTemplate
    {
        $template = WorkoutTemplate::create([
            // coach_id es NOT NULL: el coach del cliente (o el propio
            // cliente si no tiene). No aparece en la biblioteca del coach:
            // WorkoutTemplateController::getList() excluye las plantillas
            // con program_day_assignments, e is_public=false la deja fuera
            // del catálogo de clientes.
            'coach_id'             => $user->coach_id ?: $user->id,
            'created_by_client_id' => $user->id,
            'client_series_uuid'   => $seriesUuid,
            'title'                => $title,
            'is_exclusive'         => false,
            'is_public'            => false,
        ]);

        $this->createBlocks($template, $blocks);

        return $template;
    }

    private function createBlocks(WorkoutTemplate $template, array $blocks): void
    {
        foreach ($blocks as $block) {
            $newBlock = WorkoutTemplateBlock::create([
                'workout_template_id' => $template->id,
                'title'               => $block['title'],
                'order'               => $block['order'],
            ]);

            foreach ($block['exercises'] as $ex) {
                WorkoutTemplateExercise::create([
                    'workout_template_block_id' => $newBlock->id,
                    'exercise_id'               => $ex['exercise_id'],
                    'sequence'                  => $ex['sequence'],
                    'prescribed'                => $ex['prescribed'],
                    'enabled_metrics'           => $ex['enabled_metrics'],
                    'notes'                     => $ex['notes'],
                ]);
            }
        }
    }
}
