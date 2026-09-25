<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProgramDayAssignment;
use App\Models\ProgramClientAssignment;
use App\Models\ClientExerciseLog;
use App\Models\ClientExerciseOverride;
use App\Models\ClientBlockOverride;
use App\Models\PersonalRecord;
use App\Models\WorkoutSessionReview;
use App\Models\WorkoutTemplateExercise;
use App\Models\WorkoutTemplateBlock;
use App\Models\NextSessionTarget;
use App\Models\User;
use App\Notifications\CommonNotification;
use App\Services\MuscleVolumeService;
use App\Services\TemplateIsolationGuard;
use App\Traits\HasYoutubeThumbnail;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;

class SessionDetailController extends Controller
{
    use HasYoutubeThumbnail;

    const DIFFICULTY_LABELS = [1 => 'Okay', 2 => 'Good', 3 => 'Fun', 4 => 'Great', 5 => 'Amazing'];

    /**
     * El resumen de una sesión concreta: bloques con sus ejercicios, serie
     * a serie con 1RM/Volumen calculados y marcados los PRs, más totales
     * generales. Es lo que se abre al hacer clic en un "badge" del
     * calendario, o en un entrenamiento completado del perfil del cliente.
     *
     * Acepta DOS orígenes posibles (uno de los dos, no ambos):
     * - `program_day_assignment_id`: día de un programa asignado en
     *   calendario — el caso original, con anulaciones (overrides) propias.
     * - `workout_template_id` + `date`: un "workout suelto" (sin
     *   asignación de calendario) — `date` (YYYY-MM-DD) es obligatorio
     *   aquí para distinguir sesiones distintas del mismo workout suelto.
     *
     * ACTUALIZADO: el prescrito que se devuelve es SIEMPRE el de ESTE
     * cliente concreto — si existe una anulación (client_exercise_overrides)
     * para (esta sesión + este cliente + este ejercicio), se usa esa;
     * si no, se usa el de la plantilla compartida como base. Así cada
     * cliente ve/edita su propio progreso, sin pisar a los demás.
     */
    public function getSessionDetail(Request $request)
    {
        $request->validate([
            'program_day_assignment_id' => 'required_without:workout_template_id|nullable|exists:program_day_assignments,id',
            'workout_template_id'        => 'required_without:program_day_assignment_id|nullable|exists:workout_templates,id',
            'date'                       => 'required_with:workout_template_id|nullable|date',
            'client_id'                  => 'required|exists:users,id',
        ]);

        $isStandalone = (bool) $request->workout_template_id;

        if ($isStandalone) {
            $workoutTemplate = \App\Models\WorkoutTemplate::with('blocks.exercises.exercise')->find($request->workout_template_id);
            $sessionDate = $request->date;
        } else {
            $assignment = ProgramDayAssignment::with('workoutTemplate.blocks.exercises.exercise')->find($request->program_day_assignment_id);
            $workoutTemplate = $assignment->workoutTemplate;
            $sessionDate = $assignment->scheduled_date?->toDateString() ?? now()->toDateString();
        }

        if (!$workoutTemplate) {
            return json_message_response('Este entrenamiento no tiene una plantilla asociada.', 404);
        }

        $reviewQuery = WorkoutSessionReview::where('user_id', $request->client_id);
        $review = $isStandalone
            ? $reviewQuery->where('workout_template_id', $workoutTemplate->id)->whereDate('completed_at', $sessionDate)->first()
            : $reviewQuery->where('program_day_assignment_id', $request->program_day_assignment_id)->first();

        // Las anulaciones (client_exercise_overrides) solo existen para dias
        // de programa asignado en calendario - un workout suelto no tiene
        // "asignacion" que anular, siempre usa el prescrito de la plantilla.
        $allOverrides = $isStandalone
            ? collect()
            : ClientExerciseOverride::where('program_day_assignment_id', $request->program_day_assignment_id)
                ->where('client_id', $request->client_id)
                ->with('exercise')
                ->get();

        // AISLAMIENTO (auditoría 2026-09-18): $allOverrides mezcla dos cosas
        // -- overrides de un ejercicio real de la plantilla
        // (workout_template_exercise_id NOT NULL, de siempre) y "adiciones"
        // propias de este cliente (NULL, ver SessionDetailController::
        // addExercise/addBlock). Antes esta vista ignoraba 'hidden' por
        // completo y no sabía nada de las adiciones -- un ejercicio
        // "eliminado" o "añadido" desde aquí mismo nunca se reflejaba en la
        // propia pantalla que lo edita.
        $overridesByExercise = $allOverrides->whereNotNull('workout_template_exercise_id')->keyBy('workout_template_exercise_id');
        $sharedBlockAdditions = $allOverrides->where('is_addition', true)->whereNotNull('workout_template_block_id')->groupBy('workout_template_block_id');

        $ownBlocks = $isStandalone
            ? collect()
            : ClientBlockOverride::where('program_day_assignment_id', $request->program_day_assignment_id)
                ->where('client_id', $request->client_id)
                ->with('exercises.exercise')
                ->orderBy('order')
                ->get();

        $additionExerciseIds = $allOverrides->where('is_addition', true)->pluck('exercise_id')->filter()->unique()->values()->all();

        $allExerciseIds = $workoutTemplate->blocks->flatMap(fn ($b) => $b->exercises->pluck('exercise_id')->values())
            ->merge($additionExerciseIds)
            ->unique()->values()->all();
        $allTemplateExerciseIds = $workoutTemplate->blocks->flatMap(fn ($b) => $b->exercises->pluck('id')->values())->unique()->values()->all();

        // Batch-load logs (keyed by workout_template_exercise_id, keeping only latest)
        // orderByDesc('id') en vez de created_at: los timestamps son de
        // precision de segundo, y varias series de un mismo ejercicio se
        // registran (INSERT nuevo cada vez, ver logSets) en la misma sesion
        // a menudo dentro del mismo segundo - con 'created_at' como unico
        // criterio, un empate podia devolver el log con MENOS series en vez
        // del ultimo (con todas), mostrando solo 1 serie en vez de todas.
        $logsQuery = ClientExerciseLog::where('client_id', $request->client_id)
            ->whereIn('workout_template_exercise_id', $allTemplateExerciseIds);
        $logsQuery = $isStandalone
            ? $logsQuery->whereNull('program_day_assignment_id')->whereDate('performed_date', $sessionDate)
            : $logsQuery->where('program_day_assignment_id', $request->program_day_assignment_id);
        $logs = $logsQuery->orderByDesc('id')
            ->get()
            ->unique('workout_template_exercise_id')
            ->keyBy('workout_template_exercise_id');

        // Logs de ejercicios AÑADIDOS (sin workout_template_exercise_id
        // propio) -- se identifican por exercise_id dentro de esta misma
        // sesión, igual que ya hace el "Añadir ejercicio +" ad-hoc del
        // cliente en ClientCalendarController::logSets().
        $additionLogs = ($isStandalone || empty($additionExerciseIds))
            ? collect()
            : ClientExerciseLog::where('client_id', $request->client_id)
                ->where('program_day_assignment_id', $request->program_day_assignment_id)
                ->whereNull('workout_template_exercise_id')
                ->whereIn('exercise_id', $additionExerciseIds)
                ->orderByDesc('id')
                ->get()
                ->unique('exercise_id')
                ->keyBy('exercise_id');

        // Batch-load PRs for this session date (keyed by exercise_id)
        $prsToday = PersonalRecord::where('user_id', $request->client_id)
            ->whereIn('exercise_id', $allExerciseIds)
            ->whereDate('achieved_at', $sessionDate)
            ->get()
            ->groupBy('exercise_id')
            ->map(fn ($group) => $group->count());

        // Motor de Auto-Regulación de Carga: sugerencia mas reciente por
        // ejercicio, mismo criterio (NextSessionTarget::relevantForClient)
        // que ya usa el cliente en su propio calendario
        // (ClientCalendarController::getDayDetail) -- así el coach ve
        // exactamente la misma sugerencia que verá el cliente, y puede
        // aprobarla/editarla/rechazarla desde aquí (ver rutas
        // admin/session-progression/suggestions/{id}/*) sin tener que ir
        // al panel de excepciones aparte. Solo aplica a días de programa
        // asignado -- un workout suelto no pasa por el motor de progresión
        // (no tiene mesociclo/semana que el motor resuelva).
        $loadSuggestions = collect();
        if (!$isStandalone) {
            $client = User::find($request->client_id);
            if ($client && Gate::forUser($client)->allows('paid-tier')) {
                $loadSuggestions = NextSessionTarget::relevantForClient((int) $request->client_id)
                    ->whereIn('exercise_id', $allExerciseIds)
                    ->with('rule')
                    ->orderByDesc('generated_at')
                    ->get()
                    ->unique('exercise_id')
                    ->keyBy('exercise_id');
            }
        }

        // Última vez que el cliente registró CADA ejercicio, para que el
        // coach pueda comparar/ajustar la carga aunque esta sesión concreta
        // todavía esté "Programada" (sin log propio) — mismo campo
        // `last_performance` que ya devuelve WorkoutTemplateController::
        // getDetail al editar una plantilla para un cliente. Se excluyen
        // los logs de ESTA MISMA sesión para no mostrarse a sí misma como
        // "última vez" cuando la sesión ya está completada.
        // latestSnapshots + hasSets (2026-09-24): una sesión cuyo estado
        // final es "todas las series desmarcadas" (logged_sets = []) no
        // es la última vez que hizo el ejercicio -- ni esa foto vacía ni
        // las fotos anteriores de esa misma sesión deben usarse como
        // referencia (ver ClientExerciseLog::scopeLatestSnapshots()).
        $lastPerformances = ClientExerciseLog::where('client_id', $request->client_id)
            ->latestSnapshots((int) $request->client_id)
            ->whereIn('exercise_id', $allExerciseIds)
            ->where(function ($q) use ($isStandalone, $request, $sessionDate) {
                if ($isStandalone) {
                    $q->whereNotNull('program_day_assignment_id')
                        ->orWhereDate('performed_date', '!=', $sessionDate);
                } else {
                    $q->where('program_day_assignment_id', '!=', $request->program_day_assignment_id)
                        ->orWhereNull('program_day_assignment_id');
                }
            })
            ->orderByDesc('id')
            ->get()
            ->filter(fn ($log) => $log->hasSets())
            ->unique('exercise_id')
            ->keyBy('exercise_id');

        $total_sets = 0;
        $total_volume = 0;
        $total_reps = 0;
        $total_prs = 0;

        // Renderiza UN ejercicio (de la plantilla compartida o una adición
        // propia del cliente) con el mismo cálculo de series/1RM/volumen/PRs
        // -- extraído a closure (auditoría 2026-09-18) para no duplicar esta
        // lógica entre los ejercicios de plantilla y las adiciones.
        $renderExercise = function (
            int $exerciseId,
            ?int $workoutTemplateExerciseId,
            ?int $clientExerciseOverrideId,
            $exerciseModel,
            array $prescribed,
            ?string $notes,
            array $enabledMetrics,
            $log,
            bool $isAddition
        ) use ($prsToday, $loadSuggestions, $lastPerformances, &$total_sets, &$total_volume, &$total_reps, &$total_prs) {
            $exercise_image = optional($exerciseModel)->video_url
                ? $this->youtubeThumbnail(optional($exerciseModel)->video_url)
                : getSingleMedia($exerciseModel, 'exercise_image', null);

            // Motor de Auto-Regulación de Carga: null si no hay ninguna
            // sugerencia relevante para este ejercicio (caso normal). 'id'
            // es el NextSessionTarget que el frontend usa para llamar a
            // approve/edit/reject.
            $suggestion = $loadSuggestions->get($exerciseId);
            $load_suggestion = $suggestion ? [
                'id'              => $suggestion->id,
                'status'          => $suggestion->status->value,
                'proposed_weight' => $suggestion->proposed_weight,
                'proposed_reps'   => $suggestion->proposed_reps,
                'resolved_at'     => optional($suggestion->resolved_at)->toIso8601String(),
                'rule_name'       => optional($suggestion->rule)->name,
            ] : null;

            $last_log = $lastPerformances->get($exerciseId);
            $last_performance = $last_log ? ['sets' => $last_log->logged_sets] : null;

            $base = [
                'exercise_id'                  => $exerciseId,
                'workout_template_exercise_id' => $workoutTemplateExerciseId,
                'client_exercise_override_id'  => $clientExerciseOverrideId, // AÑADIDO: id a mandar a removeExercise si es_addition
                'is_addition'                  => $isAddition, // AÑADIDO: para que el frontend lo marque "personalizado para este cliente"
                'prescribed'                   => $prescribed,
                'notes'                        => $notes, // AÑADIDO
                'title'                        => optional($exerciseModel)->title,
                'exercise_image'               => $exercise_image,
                'video_url'                    => optional($exerciseModel)->video_url,
                'enabled_metrics'              => $enabledMetrics,
                'load_suggestion'              => $load_suggestion,
                'last_performance'             => $last_performance,
            ];

            if (!$log) {
                return $base + [
                    'client_note' => null, // AÑADIDO
                    'logged'      => false,
                    'sets'        => [],
                ];
            }

            $sets_detail = [];

            foreach (($log->logged_sets ?? []) as $i => $set) {
                $weight = (float) ($set['carga'] ?? 0);
                $reps   = (int) ($set['reps'] ?? 0);
                $rpe_rir = $set['rpe'] ?? $set['rir'] ?? null;

                $volume = $weight * $reps;
                $one_rm = ($weight > 0 && $reps > 0) ? PersonalRecord::calculateEpley1RM($weight, $reps) : 0;

                if ($weight > 0) $total_sets++;
                $total_volume += $volume;
                $total_reps += $reps;

                $sets_detail[] = [
                    'set'    => $i + 1,
                    'weight' => $weight,
                    'reps'   => $reps,
                    'rpe_rir' => $rpe_rir,
                    'one_rm' => round($one_rm, 1),
                    'volume' => round($volume, 1),
                ];
            }

            // PRs from pre-loaded batch (no query inside loop)
            $prs_today = $prsToday->get($exerciseId, 0);
            $total_prs += $prs_today;

            return $base + [
                'client_note'       => $log->notes ?: null, // AÑADIDO
                'logged'            => true,
                'sets'              => $sets_detail,
                'prs_this_session'  => $prs_today,
                'exercise_volume'   => round(collect($sets_detail)->sum('volume'), 1),
            ];
        };

        $renderAddition = function ($addition) use ($additionLogs, $renderExercise) {
            return $renderExercise(
                $addition->exercise_id,
                null,
                $addition->id,
                $addition->exercise,
                $addition->prescribed_override ?? [],
                $addition->notes,
                $addition->enabled_metrics_override ?? [],
                $additionLogs->get($addition->exercise_id),
                true
            );
        };

        $blocks = $workoutTemplate->blocks->map(function ($block) use ($overridesByExercise, $sharedBlockAdditions, $logs, $renderExercise, $renderAddition) {
            $templateExercises = $block->exercises
                // Modo vida real (2026-08-12, extendido 2026-09-18 a esta
                // vista de admin, que antes lo ignoraba por completo): un
                // ejercicio oculto para este cliente no se le muestra, aunque
                // otros clientes con el mismo workout_template lo sigan viendo.
                ->reject(fn ($ex) => (bool) ($overridesByExercise->get($ex->id)->hidden ?? false))
                ->map(function ($ex) use ($overridesByExercise, $logs, $renderExercise) {
                    $override = $overridesByExercise->get($ex->id);
                    $effective_prescribed = array_merge($ex->prescribed ?? [], $override->prescribed_override ?? []);
                    $enabled_metrics = $override->enabled_metrics_override ?? ($ex->enabled_metrics ?? []);

                    return $renderExercise(
                        $ex->exercise_id,
                        $ex->id,
                        null,
                        $ex->exercise,
                        $effective_prescribed,
                        // BUG REAL (reportado 2026-09-20): esto era solo
                        // "$override->notes ?? null" -- sin ningún override
                        // todavía (el caso normal justo después de asignar
                        // un programa), la nota que el coach escribió al
                        // construir la plantilla base ($ex->notes) se
                        // perdía por completo, tanto en esta vista de admin
                        // como en la app del cliente (getMySessionDetail()
                        // reusa este mismo método). El override, cuando
                        // existe, sigue ganando -- es la anotación
                        // específica de ESTE cliente para ESTA sesión.
                        $override->notes ?? $ex->notes,
                        $enabled_metrics,
                        $logs->get($ex->id),
                        false
                    );
                });

            // AÑADIDO (auditoría 2026-09-18): ejercicios que este cliente en
            // concreto añadió a este bloque compartido -- nunca tocan
            // workout_template_exercises, solo existen para él.
            $additions = ($sharedBlockAdditions->get($block->id) ?? collect())
                ->sortBy('sequence')
                ->map($renderAddition);

            return [
                'block_id'     => $block->id,
                'title'        => $block->title,
                // BUG REAL (reportado 2026-09-20, mismo caso que 'notes' de
                // ejercicio arriba): esta clave no existía en la respuesta,
                // así que las instrucciones del bloque que el coach escribió
                // en la plantilla base (WorkoutTemplateBlock.instructions,
                // ver WorkoutTemplateViewer.tsx "Instrucciones / notas del
                // bloque") nunca llegaban ni al panel admin ni a la app del
                // cliente para ningún día de programa asignado.
                'instructions' => $block->instructions,
                'exercises'    => $templateExercises->concat($additions)->values(),
            ];
        });

        // AÑADIDO (auditoría 2026-09-18): bloques enteros que este cliente
        // añadió -- no existen en la plantilla compartida, se listan
        // después de los bloques compartidos.
        $ownBlockEntries = $ownBlocks->map(function ($block) use ($renderAddition) {
            return [
                'block_id'                  => null,
                'client_block_override_id'  => $block->id,
                'title'                     => $block->title,
                'instructions'              => $block->instructions,
                'is_addition'               => true,
                'exercises'                 => $block->exercises->sortBy('sequence')->map($renderAddition)->values(),
            ];
        });

        $blocks = $blocks->concat($ownBlockEntries)->values();

        return json_custom_response([
            'data' => [
                'title'             => $workoutTemplate->title,
                'date'              => $sessionDate,
                'difficulty_label'  => $review ? (self::DIFFICULTY_LABELS[$review->difficulty_rating] ?? null) : null,
                'comment'           => $review->comment ?? null,
                // Hay reseña => el cliente pulso "Finalizar" (la sesion cuenta
                // como completada aunque no exista NINGUN log de series). Sin
                // esto el admin solo podia deducir "completada" de los logs y
                // mostraba el editor de la sesion en vez de decir que el
                // cliente no apunto nada (caso Ayoub, 2026-09-25).
                'completed'         => (bool) $review,
                'duration_seconds'  => $review->duration_seconds ?? null,
                'calories_burned'   => $review->calories_burned ?? null,
                'total_sets'        => $total_sets,
                'total_volume'      => round($total_volume, 1),
                'total_reps'        => $total_reps,
                'total_prs'         => $total_prs,
                'blocks'            => $blocks,
            ],
        ]);
    }

    /**
     * Lista de sesiones REALMENTE completadas por un cliente (el cliente
     * pulso "Finalizar entrenamiento" en la app - WorkoutSessionReview,
     * no una fila de calendario con fecha pasada que puede no tener nada
     * registrado). Antes "Entrenamientos completados" en el perfil del
     * cliente listaba cualquier dia de calendario con fecha <= hoy y
     * abria el dialogo de EDICION de la plantilla al hacer clic - no
     * mostraba nada de lo que el cliente registro de verdad. Esto
     * devuelve, para cada sesion, el identificador correcto (asignacion
     * de calendario O workout suelto + fecha) para poder pedir despues
     * el detalle real via getSessionDetail().
     */
    public function listCompletedSessions(Request $request)
    {
        $request->validate(['client_id' => 'required|exists:users,id']);

        $reviews = WorkoutSessionReview::where('user_id', $request->client_id)
            ->whereNotNull('completed_at')
            ->with(['programDayAssignment.workoutTemplate', 'workoutTemplate'])
            ->orderByDesc('completed_at')
            ->limit(100)
            ->get();

        // Sesiones de programa con AL MENOS una serie registrada. Una reseña sin
        // logs (el cliente finalizo sin apuntar nada) se marca has_logs=false
        // para que el panel lo avise en el calendario. null = workout suelto,
        // no se puede saber por asignacion.
        $assignmentsWithLogs = ClientExerciseLog::where('client_id', $request->client_id)
            ->whereIn('program_day_assignment_id', $reviews->pluck('program_day_assignment_id')->filter()->unique()->values())
            ->whereRaw('JSON_LENGTH(logged_sets) > 0')
            ->distinct()
            ->pluck('program_day_assignment_id')
            ->flip();

        $reviews = $reviews
            ->map(function ($r) use ($assignmentsWithLogs) {
                $template = optional($r->programDayAssignment)->workoutTemplate ?? $r->workoutTemplate;
                $media = $template ? $template->getFirstMedia('image') : null;

                return [
                    'id'                        => $r->id,
                    'program_day_assignment_id' => $r->program_day_assignment_id,
                    'workout_template_id'       => $r->workout_template_id,
                    'title'                     => $template->title ?? 'Entrenamiento',
                    'thumbnail'                 => $media ? $media->getUrl() : null,
                    'date'                      => optional($r->completed_at)->toDateTimeString(),
                    'duration_seconds'          => $r->duration_seconds,
                    'volume_kg'                 => $r->volume_kg,
                    'calories_burned'           => $r->calories_burned,
                    'difficulty_rating'         => $r->difficulty_rating,
                    'difficulty_label'          => self::DIFFICULTY_LABELS[$r->difficulty_rating] ?? null,
                    'has_logs'                  => $r->program_day_assignment_id
                        ? $assignmentsWithLogs->has($r->program_day_assignment_id)
                        : null,
                ];
            });

        return json_custom_response(['data' => $reviews]);
    }

    /**
     * Espejo cliente de listCompletedSessions/getSessionDetail — mismo
     * cálculo exacto que ya usa el admin en "Entrenamientos completados",
     * solo que aquí client_id se fuerza al usuario autenticado en vez de
     * venir del query string. Pedido por el cliente: poder ver su propio
     * historial de entrenamientos realizados igual que ya lo ve su coach.
     */
    public function listMyCompletedSessions(Request $request)
    {
        $request->merge(['client_id' => auth('sanctum')->id()]);
        return $this->listCompletedSessions($request);
    }

    public function getMySessionDetail(Request $request)
    {
        $request->merge(['client_id' => auth('sanctum')->id()]);
        return $this->getSessionDetail($request);
    }

    /**
     * Historial de ejercicios de un cliente concreto para el admin (pestaña
     * "Historial de ejercicios" del perfil del cliente) - una fila por serie
     * realmente registrada (client_exercise_logs, sistema V2), no por
     * PersonalRecord. Antes esta pestaña llamaba a
     * PersonalRecordController::getExerciseHistory, que es un endpoint DE
     * CLIENTE (usa auth()->id(), ignora el client_id que mandaba el admin)
     * y encima exige `exercise_id` (que el admin nunca envia) - por eso
     * la tabla salia siempre vacia, sin ningun error visible.
     */
    public function getClientExerciseHistory(Request $request)
    {
        $request->validate(['client_id' => 'required|exists:users,id']);

        // latestSnapshots: sin esto cada serie aparecía repetida una vez por
        // cada serie marcada después (filas acumuladas, ver ClientExerciseLog).
        $logs = ClientExerciseLog::where('client_id', $request->client_id)
            ->latestSnapshots((int) $request->client_id)
            ->with('exercise:id,title')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $rows = [];
        foreach ($logs as $log) {
            $date = optional($log->performed_date)->toDateString() ?? $log->created_at->toDateString();
            foreach (($log->logged_sets ?? []) as $i => $set) {
                $weight = isset($set['carga']) && is_numeric($set['carga']) ? (float) $set['carga'] : null;
                $reps = isset($set['reps']) && is_numeric($set['reps']) ? (int) $set['reps'] : null;
                $one_rm = ($weight && $reps) ? round(PersonalRecord::calculateEpley1RM($weight, $reps), 1) : null;

                $rows[] = [
                    'id'          => "{$log->id}-{$i}",
                    'exercise_id' => $log->exercise_id,
                    'exercise'    => ['title' => optional($log->exercise)->title ?? 'Ejercicio'],
                    'date'        => $date,
                    'weight'      => $weight,
                    'reps'        => $reps,
                    'one_rm'      => $one_rm,
                ];
            }
        }

        return json_custom_response(['data' => $rows]);
    }

    /**
     * Volumen de trabajo por grupo muscular de un cliente — misma logica de
     * reparto EMG que antes vivia solo en el frontend del admin
     * (src/lib/muscle-volume.ts), ahora servida desde MuscleVolumeService
     * para que app movil y admin panel consuman siempre el mismo calculo.
     */
    public function getMuscleVolume(Request $request)
    {
        $request->validate(['client_id' => 'required|exists:users,id']);
        $days = (int) $request->input('days', 0);
        $multiplierEnabled = $request->boolean('multiplier_enabled', true);

        $data = MuscleVolumeService::computeForClient((int) $request->client_id, $days, $multiplierEnabled);

        return json_custom_response(['data' => $data]);
    }

    /**
     * AÑADIDO: guarda UN campo del prescrito, SOLO PARA ESTE CLIENTE en
     * ESTA sesión concreta — nunca toca la plantilla compartida
     * (workout_template_exercises). Esto es lo que usa el modal de
     * detalle de sesión (que siempre tiene un cliente de por medio).
     */
    public function updatePrescribedOverride(Request $request)
    {
        $request->validate([
            'program_day_assignment_id'     => 'required|exists:program_day_assignments,id',
            'client_id'                      => 'required|exists:users,id',
            'workout_template_exercise_id'   => 'required_without:client_exercise_override_id|nullable|exists:workout_template_exercises,id',
            'client_exercise_override_id'    => 'required_without:workout_template_exercise_id|nullable|exists:client_exercise_overrides,id',
            'field'                          => 'required|string',
            'value'                          => 'nullable',
        ]);

        $this->assertClientOwnsAssignment((int) $request->program_day_assignment_id, (int) $request->client_id);

        $override = $this->resolveEditableOverride($request);

        $prescribed = $override->prescribed_override ?? [];
        $prescribed[$request->field] = $request->value;
        $override->prescribed_override = $prescribed;
        $override->save();

        return json_custom_response(['data' => $override]);
    }

    /**
     * Resuelve la fila de override que hay que editar/crear -- un ejercicio
     * real de la plantilla (workout_template_exercise_id, comportamiento de
     * siempre) o una adición ya creada de este cliente
     * (client_exercise_override_id, AÑADIDO auditoría 2026-09-18, para que
     * el coach pueda editar prescrito/notas de algo que él mismo añadió sin
     * un endpoint aparte). Exactamente uno de los dos debe venir en el
     * request -- validado por el llamador.
     */
    private function resolveEditableOverride(Request $request): ClientExerciseOverride
    {
        if ($request->client_exercise_override_id) {
            return ClientExerciseOverride::where('id', $request->client_exercise_override_id)
                ->where('program_day_assignment_id', $request->program_day_assignment_id)
                ->where('client_id', $request->client_id)
                ->whereNull('workout_template_exercise_id')
                ->firstOrFail();
        }

        return ClientExerciseOverride::firstOrNew([
            'program_day_assignment_id'   => $request->program_day_assignment_id,
            'client_id'                    => $request->client_id,
            'workout_template_exercise_id' => $request->workout_template_exercise_id,
        ]);
    }

    /** AÑADIDO: guardar la nota del coach para este ejercicio, solo para este cliente/sesión. */
    public function updateOverrideNotes(Request $request)
    {
        $request->validate([
            'program_day_assignment_id'     => 'required|exists:program_day_assignments,id',
            'client_id'                      => 'required|exists:users,id',
            'workout_template_exercise_id'   => 'required_without:client_exercise_override_id|nullable|exists:workout_template_exercises,id',
            'client_exercise_override_id'    => 'required_without:workout_template_exercise_id|nullable|exists:client_exercise_overrides,id',
            'notes'                          => 'nullable|string',
        ]);

        $this->assertClientOwnsAssignment((int) $request->program_day_assignment_id, (int) $request->client_id);

        $override = $this->resolveEditableOverride($request);
        $previousNotes = $override->notes;
        $override->notes = $request->notes;
        $override->save();

        $newNotes = trim((string) $request->notes);
        if ($newNotes !== '' && $newNotes !== trim((string) $previousNotes)) {
            $client = User::find($request->client_id);
            if ($client) {
                $client->notify(new CommonNotification('coach_feedback', [
                    'id'      => $override->id,
                    'type'    => 'coach_feedback',
                    'subject' => 'Feedback de tu coach',
                    'message' => 'Tu coach ha dejado una nota en uno de tus ejercicios.',
                ]));
            }
        }

        return json_custom_response(['data' => $override]);
    }

    /**
     * "Copiar y pegar en otro día" — duplica la asignación (mismo
     * workout_template) en una fecha distinta, sin quitarla de la
     * original (a diferencia de "mover", que sí la quita de donde estaba).
     */
    public function duplicateToDate(Request $request)
    {
        $request->validate([
            'assignment_id' => 'required|exists:program_day_assignments,id',
            'new_date'      => 'required|date',
            'client_id'     => 'nullable|exists:users,id',
        ]);

        $source = ProgramDayAssignment::find($request->assignment_id);
        if ($source === null) {
            return json_message_response('Esa sesión ya no existe.', 404);
        }

        // AISLAMIENTO: desde el calendario de un cliente solo se duplica dentro de SU programa
        // (copia o calendario personal), nunca sobre un programa de la biblioteca o de otro cliente.
        if ($request->filled('client_id') && $blocked = TemplateIsolationGuard::assignmentOutsideClient($source, (int) $request->client_id)) {
            return $blocked;
        }

        $program = \App\Models\TrainingProgram::find($source->training_program_id);
        $mapper = new \App\Services\CalendarDateMapper();

        $start_date = null;
        if ($request->client_id) {
            $ca = \App\Models\ProgramClientAssignment::where('training_program_id', $program->id)
                ->where('client_id', $request->client_id)->first();
            $start_date = $ca ? Carbon::parse($ca->start_date) : null;
        }
        $start_date = $start_date ?? ($program->fecha_inicio ? Carbon::parse($program->fecha_inicio) : Carbon::today());

        $wd = $mapper->toWeekAndDay($start_date, Carbon::parse($request->new_date));

        if ($wd['week_number'] < 1 || $wd['week_number'] > $program->num_weeks) {
            return json_message_response('Esa fecha queda fuera del rango del programa.', 422);
        }

        $new_assignment = ProgramDayAssignment::create([
            'training_program_id' => $program->id,
            'week_number'          => $wd['week_number'],
            'day_of_week'          => $wd['day_of_week'],
            'workout_template_id'  => $source->workout_template_id,
            'scheduled_date'       => $request->new_date,
        ]);

        return json_custom_response(['data' => $new_assignment]);
    }

    // ═══ Add / Remove exercises from a session ═══════════════════════

    /**
     * Helper: resolve workout_template_id from a program_day_assignment.
     */
    private function resolveTemplate(int $assignmentId)
    {
        $assignment = ProgramDayAssignment::find($assignmentId);
        if (!$assignment || !$assignment->workout_template_id) {
            abort(404, 'Este día no tiene un entrenamiento asignado.');
        }
        return $assignment;
    }

    /**
     * SEGURIDAD/AISLAMIENTO (auditoría 2026-09-18): las 3 rutas de abajo
     * antes mutaban directamente la plantilla compartida
     * (WorkoutTemplate/Block/Exercise) usando solo program_day_assignment_id
     * -- como un mismo ProgramDayAssignment puede estar compartido por TODOS
     * los clientes de un programa de varias semanas (sin fila por cliente,
     * ver ProgramClientAssignment), "personalizar la sesión de hoy" de un
     * cliente mutaba en silencio la de todos los demás. Mismo criterio de
     * propiedad que ClientCalendarController::resolveOwnedAssignment(), pero
     * parametrizado por $clientId (estas rutas son de admin/coach, no del
     * propio cliente autenticado).
     */
    private function assertClientOwnsAssignment(int $assignmentId, int $clientId): ProgramDayAssignment
    {
        $assignment = $this->resolveTemplate($assignmentId);

        $owns = ProgramClientAssignment::where('client_id', $clientId)
            ->where('training_program_id', $assignment->training_program_id)
            ->where('activo', true)
            ->exists();

        if (!$owns) {
            abort(403, 'Este cliente no tiene acceso a este entrenamiento.');
        }

        // AISLAMIENTO: además de estar asignado, el programa no puede ser la copia de OTRO cliente.
        $program = \App\Models\TrainingProgram::find($assignment->training_program_id);
        if ($program !== null) {
            $owner = TemplateIsolationGuard::ownerKey($program);
            if ($owner !== TemplateIsolationGuard::LIBRARY && $owner !== 'client:'.$clientId) {
                abort(403, 'Este entrenamiento pertenece al programa de otro cliente.');
            }
        }

        return $assignment;
    }

    /**
     * Añadir un ejercicio SOLO PARA ESTE CLIENTE — nunca a la plantilla
     * compartida. Se guarda como fila de "adición" en
     * client_exercise_overrides (workout_template_exercise_id NULL,
     * exercise_id + workout_template_block_id presentes) — ver migración
     * add_addition_columns_to_client_exercise_overrides_table.
     * POST /admin/session-detail-add-exercise
     */
    public function addExercise(Request $request)
    {
        $request->validate([
            'program_day_assignment_id' => 'required|exists:program_day_assignments,id',
            'client_id'                  => 'required|exists:users,id',
            'workout_template_block_id'  => 'required|exists:workout_template_blocks,id',
            'exercise_id'                => 'required|exists:exercises,id',
        ]);

        $assignment = $this->assertClientOwnsAssignment((int) $request->program_day_assignment_id, (int) $request->client_id);

        $block = WorkoutTemplateBlock::where('id', $request->workout_template_block_id)
            ->where('workout_template_id', $assignment->workout_template_id)
            ->first();

        if (!$block) {
            abort(422, 'El bloque no pertenece a esta plantilla.');
        }

        $sharedMax = WorkoutTemplateExercise::where('workout_template_block_id', $block->id)->max('sequence') ?? 0;
        $ownMax = ClientExerciseOverride::where('program_day_assignment_id', $request->program_day_assignment_id)
            ->where('client_id', $request->client_id)
            ->where('workout_template_block_id', $block->id)
            ->max('sequence') ?? 0;

        $addition = ClientExerciseOverride::create([
            'program_day_assignment_id'  => $request->program_day_assignment_id,
            'client_id'                   => $request->client_id,
            'workout_template_block_id'   => $block->id,
            'exercise_id'                 => $request->exercise_id,
            'sequence'                    => max($sharedMax, $ownMax) + 1,
            'prescribed_override'         => $request->input('prescribed', ['series' => '']),
            'enabled_metrics_override'    => $request->input('enabled_metrics', ['reps', 'weight', 'rest', 'rpe', 'rir']),
            'notes'                       => $request->input('notes'),
        ]);

        return json_custom_response(['data' => $addition]);
    }

    /**
     * Añadir un bloque (sección) SOLO PARA ESTE CLIENTE — nunca a la
     * plantilla compartida. Crea una fila en client_block_overrides.
     * POST /admin/session-detail-add-block
     */
    public function addBlock(Request $request)
    {
        $request->validate([
            'program_day_assignment_id' => 'required|exists:program_day_assignments,id',
            'client_id'                  => 'required|exists:users,id',
            'title'                      => 'required|string',
        ]);

        $assignment = $this->assertClientOwnsAssignment((int) $request->program_day_assignment_id, (int) $request->client_id);

        $sharedMax = WorkoutTemplateBlock::where('workout_template_id', $assignment->workout_template_id)->max('order') ?? 0;
        $ownMax = ClientBlockOverride::where('program_day_assignment_id', $request->program_day_assignment_id)
            ->where('client_id', $request->client_id)
            ->max('order') ?? 0;

        $block = ClientBlockOverride::create([
            'program_day_assignment_id' => $request->program_day_assignment_id,
            'client_id'                  => $request->client_id,
            'title'                      => $request->title,
            'instructions'               => $request->input('instructions'),
            'order'                      => max($sharedMax, $ownMax) + 1,
        ]);

        return json_custom_response(['data' => $block]);
    }

    /**
     * Eliminar un ejercicio de la sesión de ESTE CLIENTE.
     * - Si es un ejercicio real de la plantilla compartida: se marca
     *   `hidden` en su override (mismo patrón que ya usa
     *   AdaptiveWeekPlanner::applyPlan() para recortar ejercicios) — nunca
     *   se borra de la plantilla, así los demás clientes lo siguen viendo.
     * - Si es una adición propia de este cliente (override sin
     *   workout_template_exercise_id): se borra esa fila directamente, no
     *   hay nada compartido que preservar.
     * POST /admin/session-detail-remove-exercise
     */
    public function removeExercise(Request $request)
    {
        $request->validate([
            'program_day_assignment_id'      => 'required|exists:program_day_assignments,id',
            'client_id'                       => 'required|exists:users,id',
            'workout_template_exercise_id'    => 'required_without:client_exercise_override_id|nullable|exists:workout_template_exercises,id',
            'client_exercise_override_id'     => 'required_without:workout_template_exercise_id|nullable|exists:client_exercise_overrides,id',
        ]);

        $assignment = $this->assertClientOwnsAssignment((int) $request->program_day_assignment_id, (int) $request->client_id);

        if ($request->workout_template_exercise_id) {
            $exercise = WorkoutTemplateExercise::where('id', $request->workout_template_exercise_id)
                ->whereHas('block', fn ($q) => $q->where('workout_template_id', $assignment->workout_template_id))
                ->first();

            if (!$exercise) {
                abort(422, 'El ejercicio no pertenece a esta plantilla.');
            }

            ClientExerciseOverride::updateOrCreate([
                'program_day_assignment_id'   => $request->program_day_assignment_id,
                'client_id'                    => $request->client_id,
                'workout_template_exercise_id' => $exercise->id,
            ], ['hidden' => true]);

            return json_message_response('Ejercicio eliminado.');
        }

        $addition = ClientExerciseOverride::where('id', $request->client_exercise_override_id)
            ->where('program_day_assignment_id', $request->program_day_assignment_id)
            ->where('client_id', $request->client_id)
            ->whereNull('workout_template_exercise_id')
            ->first();

        if (!$addition) {
            abort(422, 'Esta adición no pertenece a esta sesión/cliente.');
        }

        $addition->delete();

        return json_message_response('Ejercicio eliminado.');
    }

    // ═══ Batch override update ═══════════════════════════════════════

    /**
     * Actualizar varios campos del prescrito de golpe para un ejercicio
     * concreto en esta sesión/cliente.
     * POST /admin/session-detail-batch-update-overrides
     */
    public function batchUpdateOverrides(Request $request)
    {
        $request->validate([
            'program_day_assignment_id'   => 'required|exists:program_day_assignments,id',
            'client_id'                    => 'required|exists:users,id',
            'workout_template_exercise_id' => 'required_without:client_exercise_override_id|nullable|exists:workout_template_exercises,id',
            'client_exercise_override_id'  => 'required_without:workout_template_exercise_id|nullable|exists:client_exercise_overrides,id',
            'prescribed'                   => 'nullable|array',
            'enabled_metrics'              => 'nullable|array',
            'notes'                        => 'nullable|string',
        ]);

        $this->assertClientOwnsAssignment((int) $request->program_day_assignment_id, (int) $request->client_id);

        $override = $this->resolveEditableOverride($request);

        if ($request->has('prescribed')) {
            $override->prescribed_override = array_merge($override->prescribed_override ?? [], $request->prescribed);
        }

        if ($request->has('enabled_metrics')) {
            $override->enabled_metrics_override = $request->enabled_metrics;
        }

        if ($request->has('notes')) {
            $override->notes = $request->notes;
        }

        $override->save();

        return json_custom_response(['data' => $override]);
    }
}
