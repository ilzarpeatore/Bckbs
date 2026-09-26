<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateBlock;
use App\Models\WorkoutTemplateExercise;
use App\Models\SectionTemplate;
use App\Models\UserFavouriteWorkoutTemplate;
use App\Models\ClientExerciseLog;
use App\Traits\HasYoutubeThumbnail;
use App\Services\PackageAccessService;
use App\Services\TemplateIsolationGuard;

class WorkoutTemplateController extends Controller
{
    use HasYoutubeThumbnail;
    public function getList(Request $request)
    {
        // No se listan aquí las plantillas que ya pertenecen a un día de un
        // training_program (import o generador de semanas): un programa de
        // N semanas × M sesiones/semana genera hasta N×M plantillas (una
        // por combinación única de progresión), y mezclarlas con las
        // plantillas sueltas que el coach guarda para reutilizar hacía esta
        // lista inmanejable. Para verlas agrupadas por programa/semana, el
        // panel ya tiene RealCalendarController::getWeeksGrid()
        // (GET real-calendar-weeks-grid) -- "Calendario del programa" en
        // TrainingProgramsView.tsx -- no hace falta un endpoint nuevo.
        $workouts = WorkoutTemplate::where('coach_id', auth()->id())
            ->whereDoesntHave('programDayAssignments')
            ->with('blocks.exercises')
            ->select('id', 'coach_id', 'title', 'description', 'is_exclusive', 'is_public', 'created_at')
            ->orderByDesc('created_at')
            ->limit($request->get('per_page', 100))
            ->get()
            ->map(function ($w) {
                $media = $w->getFirstMedia('image');
                return [
                    'id'             => $w->id,
                    'title'          => $w->title,
                    'description'    => $w->description,
                    'is_exclusive'   => (bool) $w->is_exclusive,
                    'is_public'      => (bool) $w->is_public,
                    'exercise_count' => $w->blocks->sum(fn ($b) => $b->exercises->count()),
                    'thumbnail'      => $media ? $media->getUrl() : null,
                    'created_at'     => $w->created_at,
                ];
            });

        return json_custom_response(['data' => $workouts]);
    }

    // SEGURIDAD (barrido sistematico 2026-09-01, PLAUSIBLE/MEDIO-ALTO): estos
    // metodos de gestion (no los *Client*, que son deliberadamente publicos
    // para clientes navegando el catalogo) hacian find()/findOrFail() sin
    // comprobar coach_id -- cualquier coach con cuenta de panel podia leer/
    // editar/borrar el workout template de OTRO coach. getList()/store() ya
    // escopaban correctamente; se aplica el mismo criterio en cascada hasta
    // el nivel de bloque/ejercicio.
    public function getDetail(Request $request)
    {
        $workout = WorkoutTemplate::with([
            'blocks' => fn ($q) => $q->orderBy('order'),
            'blocks.exercises' => fn ($q) => $q->orderBy('sequence'),
            'blocks.exercises.exercise',
        ])->where('coach_id', auth()->id())->find($request->id);

        if ($workout == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Workout']));
        }

        $media = $workout->getFirstMedia('image');

        // Cuando el admin edita el plan de UN cliente concreto (client_id
        // opcional, viene del perfil del cliente en /users/:id) se incluye
        // lo que ese cliente usó realmente la última vez en cada ejercicio
        // - para poder ajustar la carga prescrita sin tener que ir a
        // "Historial de ejercicios" o "Entrenamientos completados" aparte.
        $logsByExercise = collect();
        if ($request->client_id) {
            $allExerciseIds = $workout->blocks->flatMap(fn ($b) => $b->exercises->pluck('exercise_id')->values())->unique()->values()->all();
            // latestSnapshots + hasSets (2026-09-24): una sesión cuyo estado
            // final es "todas las series desmarcadas" (logged_sets = []) no
            // es la última vez que hizo el ejercicio -- ni esa foto vacía ni
            // las fotos anteriores de esa misma sesión deben usarse como
            // referencia (ver ClientExerciseLog::scopeLatestSnapshots()).
            $logsByExercise = \App\Models\ClientExerciseLog::where('client_id', $request->client_id)
                ->latestSnapshots((int) $request->client_id)
                ->whereIn('exercise_id', $allExerciseIds)
                ->orderByDesc('id')
                ->get()
                ->filter(fn ($log) => $log->hasSets())
                ->unique('exercise_id')
                ->keyBy('exercise_id');
        }

        $blocks = $workout->blocks->map(function ($block) use ($logsByExercise) {
            return [
                'id'           => $block->id,
                'title'        => $block->title,
                'instructions' => $block->instructions,
                'order'        => $block->order,
                'exercises'    => $block->exercises->map(function ($e) use ($logsByExercise) {
                    $exercise = $e->exercise;
                    $thumb = $exercise && $exercise->video_url
                        ? $this->youtubeThumbnail($exercise->video_url)
                        : getSingleMedia($exercise, 'exercise_image', null);

                    $last_log = $logsByExercise->get($e->exercise_id);

                    return [
                        'id'                => $e->id,
                        'exercise_id'       => $e->exercise_id,
                        'sequence'          => $e->sequence,
                        'prescribed'        => $e->prescribed,
                        'enabled_metrics'   => $e->enabled_metrics,
                        'notes'             => $e->notes,
                        'title'             => optional($exercise)->title,
                        'exercise_image'    => $thumb,
                        'video_url'         => optional($exercise)->video_url,
                        'last_performance'  => $last_log ? ['sets' => $last_log->logged_sets] : null,
                    ];
                }),
            ];
        });

        return json_custom_response([
            'data' => [
                'id'          => $workout->id,
                'title'       => $workout->title,
                'description' => $workout->description,
                'coach_id'    => $workout->coach_id,
                'is_public'   => (bool) $workout->is_public,
                'thumbnail'   => $media ? $media->getUrl() : null,
                'blocks'      => $blocks,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'is_exclusive' => 'sometimes|boolean',
            'is_public' => 'sometimes|boolean',
        ]);

        // is_public por defecto false (pedido explícito del coach,
        // 2026-09-18): un workout nace privado -- ni siquiera un borrador a
        // medio terminar aparece en el catálogo público hasta que el coach
        // lo marque a mano en el panel Admin.
        $workout = WorkoutTemplate::create([
            'coach_id'     => auth()->id(),
            'title'        => $request->title,
            'description'  => $request->description,
            'is_exclusive' => $request->boolean('is_exclusive'),
            'is_public'    => $request->boolean('is_public'),
        ]);

        if ($request->hasFile('image')) {
            $workout->addMediaFromRequest('image')->toMediaCollection('image');
        }

        return json_custom_response(['data' => $workout]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:workout_templates,id',
            'is_exclusive' => 'sometimes|boolean',
            'is_public' => 'sometimes|boolean',
        ]);

        $workout = WorkoutTemplate::where('coach_id', auth()->id())->findOrFail($request->id);

        // AISLAMIENTO: nunca se modifica una plantilla que use más de un cliente / la biblioteca.
        if ($blocked = TemplateIsolationGuard::violation($workout->id)) {
            return $blocked;
        }

        $workout->update($request->only(['title', 'description', 'is_exclusive', 'is_public']));

        // AÑADIDO (pedido explícito 2026-09-18): antes solo store() aceptaba
        // 'image' -- no había forma de poner/cambiar la foto de una
        // plantilla YA creada desde el panel admin. Sin esto, el calendario
        // del cliente (ClientCalendarController::getMyMonth) nunca tenía un
        // thumbnail real y caía siempre en el fallback genérico de stock
        // del lado del cliente.
        if ($request->hasFile('image')) {
            $workout->clearMediaCollection('image');
            $workout->addMediaFromRequest('image')->toMediaCollection('image');
        }

        return json_message_response(__('message.save_form', ['form' => 'Workout']));
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:workout_templates,id',
        ]);

        $workout = WorkoutTemplate::where('coach_id', auth()->id())->findOrFail($request->id);

        if ($blocked = TemplateIsolationGuard::violation($workout->id)) {
            return $blocked;
        }

        $workout->delete();

        return json_message_response(__('message.delete_form', ['form' => 'Workout']));
    }

    /** Crear un bloque nuevo (vacío) dentro del workout. */
    public function storeBlock(Request $request)
    {
        $request->validate([
            'workout_template_id' => 'required|exists:workout_templates,id',
            'title'                => 'required|string|max:255',
        ]);

        $workout = WorkoutTemplate::where('coach_id', auth()->id())->find($request->workout_template_id);
        if ($workout == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Workout']));
        }

        if ($blocked = TemplateIsolationGuard::violation($workout->id)) {
            return $blocked;
        }

        $order = WorkoutTemplateBlock::where('workout_template_id', $request->workout_template_id)->max('order') ?? 0;

        $block = WorkoutTemplateBlock::create([
            'workout_template_id' => $request->workout_template_id,
            'title'                => $request->title,
            'instructions'         => $request->instructions,
            'order'                => $order + 1,
        ]);

        return json_custom_response(['data' => $block]);
    }

    /**
     * "Importar sección" — el botón clave que pediste: clona un
     * section_template completo (título + ejercicios) dentro de este
     * workout, como bloque nuevo.
     */
    public function importSection(Request $request)
    {
        $request->validate([
            'workout_template_id'  => 'required|exists:workout_templates,id',
            'section_template_id'  => 'required|exists:section_templates,id',
        ]);

        $workout = WorkoutTemplate::where('coach_id', auth()->id())->find($request->workout_template_id);
        $section = SectionTemplate::with('exercises')->where('coach_id', auth()->id())->find($request->section_template_id);

        if ($workout == null || $section == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Workout']));
        }

        if ($blocked = TemplateIsolationGuard::violation($workout->id)) {
            return $blocked;
        }

        $order = WorkoutTemplateBlock::where('workout_template_id', $workout->id)->max('order') ?? 0;

        $block = $section->cloneInto($workout, $order + 1);

        return json_custom_response(['data' => $block->load('exercises.exercise')]);
    }

    /**
     * Inverso de importSection(): guarda un bloque de este workout como una
     * plantilla de sección reutilizable (section_templates). Copia, no enlace.
     */
    public function saveBlockAsSection(Request $request)
    {
        $request->validate([
            'id'    => 'required|exists:workout_template_blocks,id',
            'title' => 'nullable|string|max:255',
        ]);

        $block = WorkoutTemplateBlock::with('exercises')->whereHas('workoutTemplate', function ($q) {
            $q->where('coach_id', auth()->id());
        })->findOrFail($request->id);

        $section = SectionTemplate::createFromBlock($block, auth()->id(), $request->title);

        return json_custom_response([
            'data'    => $section->load('exercises'),
            'message' => 'Sección guardada',
        ]);
    }

    public function updateBlock(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:workout_template_blocks,id',
        ]);

        $block = WorkoutTemplateBlock::whereHas('workoutTemplate', function ($q) {
            $q->where('coach_id', auth()->id());
        })->findOrFail($request->id);

        if ($blocked = TemplateIsolationGuard::violation($block->workout_template_id)) {
            return $blocked;
        }

        $block->update($request->only(['title', 'instructions', 'order']));

        return json_message_response(__('message.save_form', ['form' => 'Block']));
    }

    public function destroyBlock(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:workout_template_blocks,id',
        ]);

        $block = WorkoutTemplateBlock::whereHas('workoutTemplate', function ($q) {
            $q->where('coach_id', auth()->id());
        })->findOrFail($request->id);

        if ($blocked = TemplateIsolationGuard::violation($block->workout_template_id)) {
            return $blocked;
        }

        $block->delete();

        return json_message_response(__('message.delete_form', ['form' => 'Block']));
    }

    /** Añadir un ejercicio a un bloque, con su prescrito y métricas. */
    public function saveExercise(Request $request)
    {
        $request->validate([
            'workout_template_block_id' => 'required|exists:workout_template_blocks,id',
            'exercise_id'                => 'required|exists:exercises,id',
        ]);

        $block = WorkoutTemplateBlock::whereHas('workoutTemplate', function ($q) {
            $q->where('coach_id', auth()->id());
        })->find($request->workout_template_block_id);
        if ($block == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Block']));
        }

        if ($blocked = TemplateIsolationGuard::violation($block->workout_template_id)) {
            return $blocked;
        }

        if ($request->filled('id')) {
            $ownsExercise = WorkoutTemplateExercise::where('id', $request->id)
                ->where('workout_template_block_id', $block->id)
                ->exists();
            if (!$ownsExercise) {
                return json_message_response(__('message.not_found_entry', ['name' => 'Exercise']));
            }
        }

        $updateData = [
            'workout_template_block_id' => $request->workout_template_block_id,
            'exercise_id'                 => $request->exercise_id,
        ];

        if ($request->filled('sequence')) {
            $updateData['sequence'] = $request->sequence;
        } elseif (!$request->filled('id')) {
            // Solo ejercicio nuevo sin sequence explicito va al final del
            // bloque -- una actualizacion sobre un "id" existente (editar
            // metricas, sustituir ejercicio...) sin sequence en el payload
            // NO debe reordenarlo silenciosamente al final (bug real: antes
            // esto se recalculaba siempre, así que cualquier guardado sin
            // sequence saltaba el ejercicio al final del bloque).
            $order = WorkoutTemplateExercise::where('workout_template_block_id', $request->workout_template_block_id)->max('sequence') ?? 0;
            $updateData['sequence'] = $order + 1;
        }

        if ($request->has('prescribed')) {
            $updateData['prescribed'] = $request->prescribed;
        }

        if ($request->has('enabled_metrics')) {
            $metrics = (array) $request->enabled_metrics;
            if ($metrics === []) {
                // Ejercicio recién añadido sin métricas elegidas todavía
                // (ver handleAddExercise en el admin) -- valor por defecto
                // que ya incluye sensación subjetiva, no un array vacío.
                $metrics = ['reps', 'carga', 'descanso', 'rir'];
            } elseif (!in_array('rir', $metrics, true) && !in_array('rpe', $metrics, true)) {
                return json_message_response('enabled_metrics debe incluir "rir" o "rpe" (sensación subjetiva obligatoria).', 422);
            }
            $updateData['enabled_metrics'] = $metrics;
        } elseif (!$request->filled('id') || !WorkoutTemplateExercise::find($request->id)?->enabled_metrics) {
            // Ejercicio nuevo (o existente sin métricas todavía) sin
            // enabled_metrics explícito -- valor por defecto que ya
            // incluye sensación subjetiva, nunca queda sin ella.
            $updateData['enabled_metrics'] = ['reps', 'carga', 'descanso', 'rir'];
        }

        if ($request->has('notes')) {
            $updateData['notes'] = $request->notes;
        }

        $exercise = WorkoutTemplateExercise::updateOrCreate(
            ['id' => $request->id ?? null],
            $updateData
        );

        return json_custom_response(['data' => $exercise]);
    }

    /**
     * Guardado rápido de un solo campo del prescrito (para edición en
     * línea tipo HubFit: cambias "Reps" en la tabla y se guarda solo,
     * sin recargar página).
     */
    public function updatePrescribedField(Request $request)
    {
        $request->validate([
            'id'    => 'required|exists:workout_template_exercises,id',
            'field' => 'required|string',
            'value' => 'nullable',
        ]);

        $exercise = WorkoutTemplateExercise::whereHas('block.workoutTemplate', function ($q) {
            $q->where('coach_id', auth()->id());
        })->find($request->id);

        if ($exercise == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Exercise']));
        }

        if ($blocked = TemplateIsolationGuard::violation($exercise->block->workout_template_id)) {
            return $blocked;
        }

        $prescribed = $exercise->prescribed ?? [];
        $prescribed[$request->field] = $request->value;
        $exercise->update(['prescribed' => $prescribed]);

        return json_custom_response(['data' => $exercise]);
    }

    public function deleteExercise(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:workout_template_exercises,id',
        ]);

        $exercise = WorkoutTemplateExercise::whereHas('block.workoutTemplate', function ($q) {
            $q->where('coach_id', auth()->id());
        })->findOrFail($request->id);

        if ($blocked = TemplateIsolationGuard::violation($exercise->block->workout_template_id)) {
            return $blocked;
        }

        $exercise->delete();

        return json_message_response(__('message.delete_form', ['form' => 'Exercise']));
    }

    public function updateExerciseNotes(Request $request)
    {
        $request->validate([
            'id'    => 'required|exists:workout_template_exercises,id',
            'notes' => 'nullable|string',
        ]);

        $exercise = WorkoutTemplateExercise::whereHas('block.workoutTemplate', function ($q) {
            $q->where('coach_id', auth()->id());
        })->find($request->id);

        if ($exercise == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Exercise']));
        }

        if ($blocked = TemplateIsolationGuard::violation($exercise->block->workout_template_id)) {
            return $blocked;
        }

        $exercise->update(['notes' => $request->notes]);

        return json_custom_response(['data' => $exercise]);
    }

    public function updateBlockInstructions(Request $request)
    {
        $request->validate([
            'id'           => 'required|exists:workout_template_blocks,id',
            'instructions' => 'nullable|string',
        ]);

        $block = WorkoutTemplateBlock::whereHas('workoutTemplate', function ($q) {
            $q->where('coach_id', auth()->id());
        })->find($request->id);

        if ($block == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Block']));
        }

        if ($blocked = TemplateIsolationGuard::violation($block->workout_template_id)) {
            return $blocked;
        }

        $block->update(['instructions' => $request->instructions]);

        return json_custom_response(['data' => $block]);
    }

    public function getClientDetail(Request $request)
    {
        $workout = WorkoutTemplate::with([
            'blocks.exercises.exercise',
            'blocks' => fn ($q) => $q->orderBy('order'),
            'blocks.exercises' => fn ($q) => $q->orderBy('sequence'),
        ])->find($request->id);

        if ($workout == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Workout']));
        }

        $user = auth()->user();

        // Mismo bug de privacidad que getClientList() (ver comentario ahí):
        // sin esto, cualquier cliente que conociera/adivinara el id podía
        // abrir el detalle del workout PERSONALIZADO de otro cliente, aunque
        // ya no saliera en ningún listado. Se deja pasar si es público, o si
        // es justo el suyo propio (asignado a su calendario personal o a un
        // programa en el que esté inscrito) -- así "Mi Programa" sigue
        // abriendo con normalidad su propio entrenamiento aunque no sea
        // público.
        if (!$workout->is_public && !($user && $workout->isAssignedToClient($user->id))) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Workout']));
        }

        $isAccessible = !$workout->is_exclusive || ($user && PackageAccessService::canAccessPremiumWorkouts($user));

        $media = $workout->getFirstMedia('image');

        $isFavourite = $user && UserFavouriteWorkoutTemplate::where('user_id', $user->id)
            ->where('workout_template_id', $workout->id)
            ->exists();

        // "Última vez" tambien para workouts sueltos (antes solo lo tenia
        // getDayDetail, del sistema de calendario) - mismo patron: ultimo
        // log por exercise_id, sin importar de que dia/asignacion vino.
        $logs = collect();
        $recentPerformance = collect();
        if ($isAccessible && $user) {
            $allExerciseIds = $workout->blocks->flatMap(fn ($b) => $b->exercises->pluck('exercise_id')->values())->unique()->values()->all();
            // orderByDesc('id'): 'created_at' es de precision de segundo y
            // puede empatar entre varias series de la misma sesion - 'id'
            // refleja el orden real de insercion sin empates.
            // latestSnapshots + hasSets (2026-09-24): una sesión cuyo estado
            // final es "todas las series desmarcadas" (logged_sets = []) no
            // es la última vez que hizo el ejercicio -- ni esa foto vacía ni
            // las fotos anteriores de esa misma sesión deben usarse como
            // referencia (ver ClientExerciseLog::scopeLatestSnapshots()).
            $logs = ClientExerciseLog::where('client_id', $user->id)
                ->latestSnapshots($user->id)
                ->whereIn('exercise_id', $allExerciseIds)
                ->orderByDesc('id')
                ->get()
                ->filter(fn ($log) => $log->hasSets())
                ->unique('exercise_id')
                ->keyBy('exercise_id');
            $recentPerformance = ClientExerciseLog::recentPerformanceFor((int) $user->id, $allExerciseIds);
        }

        $blocks = !$isAccessible ? [] : $workout->blocks->map(function ($block) use ($logs, $recentPerformance) {
            return [
                'id'           => $block->id,
                'title'        => $block->title,
                'instructions' => $block->instructions,
                'order'        => $block->order,
                'exercises'    => $block->exercises->map(function ($e) use ($logs, $recentPerformance) {
                    $exercise = $e->exercise;
                    $thumb = $exercise && $exercise->video_url
                        ? $this->youtubeThumbnail($exercise->video_url)
                        : getSingleMedia($exercise, 'exercise_image', null);

                    $last_log = $logs->get($e->exercise_id);

                    return [
                        'id'              => $e->id,
                        'exercise_id'     => $e->exercise_id,
                        'exercise'        => $e->exercise,
                        'sequence'        => $e->sequence,
                        'prescribed'      => $e->prescribed,
                        'enabled_metrics' => $e->enabled_metrics,
                        'notes'           => $e->notes,
                        'title'           => optional($exercise)->title,
                        'exercise_image'  => $thumb,
                        'video_url'       => optional($exercise)->video_url,
                        // Primer body_part de exercises.bodypart_ids — para el heatmap
                        // de musculo aislado en ExerciseThumb (mismo criterio que
                        // ClientCalendarController::getDayDetail).
                        'body_part_id'    => $this->primaryBodyPartId($exercise),
                        'last_performance' => $last_log ? ['sets' => $last_log->logged_sets] : null,
                        'recent_performance' => $recentPerformance->get($e->exercise_id),
                    ];
                }),
            ];
        });

        return json_custom_response([
            'data' => [
                'id'            => $workout->id,
                'title'         => $workout->title,
                'description'   => $workout->description,
                'thumbnail'     => $media ? $media->getUrl() : null,
                'is_exclusive'  => (bool) $workout->is_exclusive,
                'is_accessible' => $isAccessible,
                'is_favourite'  => (bool) $isFavourite,
                'blocks'        => $blocks,
            ],
        ]);
    }

    /**
     * Guardar/quitar de favoritos un WorkoutTemplate desde la app cliente
     * (boton bookmark en workout_preview_screen.tsx) - mismo patron
     * toggle que WorkoutController::userFavouriteWorkout (v1).
     */
    public function toggleFavourite(Request $request)
    {
        $request->validate(['workout_template_id' => 'required|exists:workout_templates,id']);

        $user_id = auth('sanctum')->id();
        $existing = UserFavouriteWorkoutTemplate::where('user_id', $user_id)
            ->where('workout_template_id', $request->workout_template_id)
            ->first();

        if ($existing) {
            $existing->delete();
            return json_custom_response(['data' => ['is_favourite' => false]]);
        }

        UserFavouriteWorkoutTemplate::create([
            'user_id'             => $user_id,
            'workout_template_id' => $request->workout_template_id,
        ]);

        return json_custom_response(['data' => ['is_favourite' => true]]);
    }

    // AÑADIDO 2026-07-30: navegación libre de "Workouts sueltos" para clientes
    // free (antes solo existía getClientDetail por id, sin ningún listado).
    public function getClientList(Request $request)
    {
        $user = auth()->user();

        // BUG REAL DE PRIVACIDAD (reportado 2026-09-18): sin este filtro se
        // listaban TAMBIÉN los workouts personalizados de otros clientes
        // (asignados a su calendario personal vía assignDirect(), ver
        // ClientProfileCalendarController) -- cualquier cliente podía verlos
        // en Home > Entrenamientos o en este mismo catálogo. is_public es
        // false por defecto (ver migración add_is_public_to_...); solo entra
        // aquí lo que el coach marcó a mano como público. Su propio
        // entrenamiento personalizado (aunque no sea público) lo sigue
        // viendo igual desde "Mi Programa", que no pasa por este endpoint
        // -- ver WorkoutTemplate::isAssignedToClient(), usado en
        // getClientDetail() más abajo para esa pantalla en concreto.
        $query = WorkoutTemplate::select('id', 'title', 'description', 'is_exclusive')
            ->where('is_public', true)
            ->orderByDesc('created_at');

        // AÑADIDO: filtro para MigratedFavourite (favourite_screen.tsx) - antes esta
        // pantalla leia workoutsApi.getFavourite() (Workout v1 legacy), pero el
        // cliente favorita templates v2 desde workout_preview_screen.tsx via
        // toggleFavourite() - no habia forma de listar esos favoritos hasta ahora.
        if ($request->boolean('only_favourites')) {
            $favouriteIds = UserFavouriteWorkoutTemplate::where('user_id', $user->id)
                ->pluck('workout_template_id');
            $query->whereIn('id', $favouriteIds);
        }

        $per_page = config('constant.PER_PAGE_LIMIT');
        if ($request->filled('per_page')) {
            if ($request->per_page == -1) {
                $per_page = $query->count();
            } elseif (is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            }
        }

        $workouts = $query->paginate($per_page);

        $items = collect($workouts->items())->map(function ($w) use ($user) {
            $isAccessible = !$w->is_exclusive || ($user && PackageAccessService::canAccessPremiumWorkouts($user));
            $media = $w->getFirstMedia('image');

            return [
                'id'            => $w->id,
                'title'         => $w->title,
                'description'   => $w->description,
                'thumbnail'     => $media ? $media->getUrl() : null,
                'is_exclusive'  => (bool) $w->is_exclusive,
                'is_accessible' => $isAccessible,
            ];
        });

        return json_custom_response([
            'pagination' => json_pagination_response($workouts),
            'data' => $items,
        ]);
    }

    /**
     * Primer body_part.id de exercises.bodypart_ids, tolerando filas legacy
     * donde quedo guardado un escalar suelto (ej. `"2"`) en vez del array
     * `[2]` esperado (mismo criterio que ClientCalendarController).
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
