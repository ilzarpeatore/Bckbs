<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProgramDayAssignment;
use App\Models\ClientExerciseLog;
use App\Models\ClientExerciseOverride;
use App\Models\PersonalRecord;
use App\Models\WorkoutSessionReview;
use App\Models\WorkoutTemplateExercise;
use App\Models\WorkoutTemplateBlock;
use App\Traits\HasYoutubeThumbnail;
use Carbon\Carbon;

class SessionDetailController extends Controller
{
    use HasYoutubeThumbnail;

    const DIFFICULTY_LABELS = [1 => 'Okay', 2 => 'Good', 3 => 'Fun', 4 => 'Great', 5 => 'Amazing'];

    /**
     * El resumen de una sesión concreta (una asignación de calendario +
     * un cliente): bloques con sus ejercicios, serie a serie con
     * 1RM/Volumen calculados y marcados los PRs, más totales generales.
     * Es lo que se abre al hacer clic en un "badge" del calendario.
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
            'program_day_assignment_id' => 'required|exists:program_day_assignments,id',
            'client_id'                  => 'required|exists:users,id',
        ]);

        $assignment = ProgramDayAssignment::with('workoutTemplate.blocks.exercises.exercise')->find($request->program_day_assignment_id);

        if (!$assignment->workoutTemplate) {
            return json_message_response('Este día no tiene un entrenamiento asignado.', 404);
        }

        $review = WorkoutSessionReview::where('program_day_assignment_id', $assignment->id)
            ->where('user_id', $request->client_id)
            ->first();

        // Batch-load overrides, logs, and PRs BEFORE the loop to avoid N+1
        $overrides = ClientExerciseOverride::where('program_day_assignment_id', $request->program_day_assignment_id)
            ->where('client_id', $request->client_id)
            ->get()
            ->keyBy('workout_template_exercise_id');

        $allExerciseIds = $assignment->workoutTemplate->blocks->flatMap(fn ($b) => $b->exercises->pluck('exercise_id')->values())->unique()->values()->all();
        $allTemplateExerciseIds = $assignment->workoutTemplate->blocks->flatMap(fn ($b) => $b->exercises->pluck('id')->values())->unique()->values()->all();

        // Batch-load logs (keyed by workout_template_exercise_id, keeping only latest)
        $logs = ClientExerciseLog::where('client_id', $request->client_id)
            ->where('program_day_assignment_id', $request->program_day_assignment_id)
            ->whereIn('workout_template_exercise_id', $allTemplateExerciseIds)
            ->orderByDesc('created_at')
            ->get()
            ->unique('workout_template_exercise_id')
            ->keyBy('workout_template_exercise_id');

        // Batch-load PRs for this session date (keyed by exercise_id)
        $sessionDate = $assignment->scheduled_date?->toDateString() ?? now()->toDateString();
        $prsToday = PersonalRecord::where('user_id', $request->client_id)
            ->whereIn('exercise_id', $allExerciseIds)
            ->whereDate('achieved_at', $sessionDate)
            ->get()
            ->groupBy('exercise_id')
            ->map(fn ($group) => $group->count());

        $total_sets = 0;
        $total_volume = 0;
        $total_reps = 0;
        $total_prs = 0;

        $blocks = $assignment->workoutTemplate->blocks->map(function ($block) use ($request, $overrides, $logs, $prsToday, &$total_sets, &$total_volume, &$total_reps, &$total_prs) {
            $exercises = $block->exercises->map(function ($ex) use ($request, $overrides, $logs, $prsToday, &$total_sets, &$total_volume, &$total_reps, &$total_prs) {
                $override = $overrides->get($ex->id);
                $effective_prescribed = array_merge($ex->prescribed ?? [], $override->prescribed_override ?? []);
                $notes = $override->notes ?? null;

                $log = $logs->get($ex->id);

                $exercise_image = optional($ex->exercise)->video_url
                    ? $this->youtubeThumbnail(optional($ex->exercise)->video_url)
                    : getSingleMedia($ex->exercise, 'exercise_image', null);

                $enabled_metrics = $override->enabled_metrics_override ?? ($ex->enabled_metrics ?? []);

                if (!$log) {
                    return [
                        'exercise_id'                  => $ex->exercise_id,
                        'workout_template_exercise_id' => $ex->id,
                        'prescribed'                   => $effective_prescribed, // ACTUALIZADO
                        'notes'                        => $notes, // AÑADIDO
                        'title'                        => optional($ex->exercise)->title,
                        'exercise_image'               => $exercise_image,
                        'video_url'                    => optional($ex->exercise)->video_url,
                        'enabled_metrics'              => $enabled_metrics,
                        'logged'                       => false,
                        'sets'                         => [],
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
                $prs_today = $prsToday->get($ex->exercise_id, 0);
                $total_prs += $prs_today;

                return [
                    'exercise_id'                  => $ex->exercise_id,
                    'workout_template_exercise_id' => $ex->id,
                    'prescribed'                   => $effective_prescribed, // ACTUALIZADO
                    'notes'                        => $notes, // AÑADIDO
                    'title'         => optional($ex->exercise)->title,
                    'exercise_image' => $exercise_image,
                    'video_url'      => optional($ex->exercise)->video_url,
                    'enabled_metrics' => $enabled_metrics,
                    'logged'        => true,
                    'sets'          => $sets_detail,
                    'prs_this_session' => $prs_today,
                    'exercise_volume'  => round(collect($sets_detail)->sum('volume'), 1),
                ];
            });

            return [
                'block_id'  => $block->id,
                'title'     => $block->title,
                'exercises' => $exercises->values(),
            ];
        });

        return json_custom_response([
            'data' => [
                'title'             => $assignment->workoutTemplate->title,
                'date'              => $assignment->scheduled_date,
                'difficulty_label'  => $review ? (self::DIFFICULTY_LABELS[$review->difficulty_rating] ?? null) : null,
                'comment'           => $review->comment ?? null,
                'total_sets'        => $total_sets,
                'total_volume'      => round($total_volume, 1),
                'total_reps'        => $total_reps,
                'total_prs'         => $total_prs,
                'blocks'            => $blocks,
            ],
        ]);
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
            'workout_template_exercise_id'   => 'required|exists:workout_template_exercises,id',
            'field'                          => 'required|string',
            'value'                          => 'nullable',
        ]);

        $override = ClientExerciseOverride::firstOrNew([
            'program_day_assignment_id'   => $request->program_day_assignment_id,
            'client_id'                    => $request->client_id,
            'workout_template_exercise_id' => $request->workout_template_exercise_id,
        ]);

        $prescribed = $override->prescribed_override ?? [];
        $prescribed[$request->field] = $request->value;
        $override->prescribed_override = $prescribed;
        $override->save();

        return json_custom_response(['data' => $override]);
    }

    /** AÑADIDO: guardar la nota del coach para este ejercicio, solo para este cliente/sesión. */
    public function updateOverrideNotes(Request $request)
    {
        $request->validate([
            'program_day_assignment_id'     => 'required|exists:program_day_assignments,id',
            'client_id'                      => 'required|exists:users,id',
            'workout_template_exercise_id'   => 'required|exists:workout_template_exercises,id',
            'notes'                          => 'nullable|string',
        ]);

        $override = ClientExerciseOverride::updateOrCreate(
            [
                'program_day_assignment_id'   => $request->program_day_assignment_id,
                'client_id'                    => $request->client_id,
                'workout_template_exercise_id' => $request->workout_template_exercise_id,
            ],
            ['notes' => $request->notes]
        );

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
     * Añadir un ejercicio a un bloque de la plantilla asociada a una sesión.
     * POST /admin/session-detail-add-exercise
     */
    public function addExercise(Request $request)
    {
        $request->validate([
            'program_day_assignment_id' => 'required|exists:program_day_assignments,id',
            'workout_template_block_id'  => 'required|exists:workout_template_blocks,id',
            'exercise_id'                => 'required|exists:exercises,id',
        ]);

        $assignment = $this->resolveTemplate($request->program_day_assignment_id);

        $block = WorkoutTemplateBlock::where('id', $request->workout_template_block_id)
            ->where('workout_template_id', $assignment->workout_template_id)
            ->first();

        if (!$block) {
            abort(422, 'El bloque no pertenece a esta plantilla.');
        }

        $order = WorkoutTemplateExercise::where('workout_template_block_id', $block->id)->max('sequence') ?? 0;

        $exercise = WorkoutTemplateExercise::create([
            'workout_template_block_id' => $block->id,
            'exercise_id'               => $request->exercise_id,
            'sequence'                  => $order + 1,
            'prescribed'                => $request->input('prescribed', ['sets' => '']),
            'enabled_metrics'           => $request->input('enabled_metrics', ['reps', 'weight', 'rest', 'rpe', 'rir']),
            'notes'                     => $request->input('notes'),
        ]);

        return json_custom_response(['data' => $exercise]);
    }

    /**
     * Añadir un bloque (sección) a la plantilla asociada a una sesión.
     * POST /admin/session-detail-add-block
     */
    public function addBlock(Request $request)
    {
        $request->validate([
            'program_day_assignment_id' => 'required|exists:program_day_assignments,id',
            'title'                     => 'required|string',
        ]);

        $assignment = $this->resolveTemplate($request->program_day_assignment_id);

        $order = WorkoutTemplateBlock::where('workout_template_id', $assignment->workout_template_id)->max('order') ?? 0;

        $block = WorkoutTemplateBlock::create([
            'workout_template_id' => $assignment->workout_template_id,
            'title'               => $request->title,
            'instructions'        => $request->input('instructions'),
            'order'               => $order + 1,
        ]);

        return json_custom_response(['data' => $block]);
    }

    /**
     * Eliminar un ejercicio de la plantilla asociada a una sesión.
     * POST /admin/session-detail-remove-exercise
     */
    public function removeExercise(Request $request)
    {
        $request->validate([
            'program_day_assignment_id'      => 'required|exists:program_day_assignments,id',
            'workout_template_exercise_id'   => 'required|exists:workout_template_exercises,id',
        ]);

        $assignment = $this->resolveTemplate($request->program_day_assignment_id);

        $exercise = WorkoutTemplateExercise::where('id', $request->workout_template_exercise_id)
            ->whereHas('block', fn ($q) => $q->where('workout_template_id', $assignment->workout_template_id))
            ->first();

        if (!$exercise) {
            abort(422, 'El ejercicio no pertenece a esta plantilla.');
        }

        $exercise->delete();

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
            'workout_template_exercise_id' => 'required|exists:workout_template_exercises,id',
            'prescribed'                   => 'nullable|array',
            'enabled_metrics'              => 'nullable|array',
            'notes'                        => 'nullable|string',
        ]);

        $override = ClientExerciseOverride::firstOrNew([
            'program_day_assignment_id'   => $request->program_day_assignment_id,
            'client_id'                    => $request->client_id,
            'workout_template_exercise_id' => $request->workout_template_exercise_id,
        ]);

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
