<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
use App\Models\TrainingProgram;
use App\Models\ClientExerciseLog;
use App\Models\WorkoutSessionReview;
use App\Services\CalendarDateMapper;
use App\Services\WorkoutSessionStatsService;
use App\Traits\HasYoutubeThumbnail;
use Carbon\Carbon;

class ClientCalendarController extends Controller
{
    use HasYoutubeThumbnail;

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
            ->with('trainingProgram.progressionRules')
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
            $rule_multiplier_cache = [];

            $assignments = ProgramDayAssignment::where('training_program_id', $program->id)
                ->with('workoutTemplate')
                ->get()
                ->groupBy(fn ($a) => $a->week_number.'-'.$a->day_of_week);

            foreach ($grid_dates as $date) {
                $wd = $mapper->toWeekAndDay($start_date, $date);
                if ($wd['week_number'] < 1 || $wd['week_number'] > $program->num_weeks) continue;

                $day_assignments = $assignments->get($wd['week_number'].'-'.$wd['day_of_week'], collect());

                foreach ($day_assignments as $a) {
                    if (!$a->workout_template_id) continue;

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
     * Sin cambios respecto a la versión anterior — sigue funcionando
     * igual, ya que recibe directamente el program_day_assignment_id
     * (que es único independientemente de a qué programa pertenezca).
     */
    public function getDayDetail(Request $request)
    {
        $request->validate(['program_day_assignment_id' => 'required|exists:program_day_assignments,id']);

        $client_id = auth('sanctum')->id();
        $assignment = ProgramDayAssignment::with('workoutTemplate.blocks.exercises.exercise')->find($request->program_day_assignment_id);

        if ($assignment->workout_template_id == null) {
            return json_custom_response(['data' => ['workout_day_id' => $assignment->id, 'sequence' => $assignment->day_of_week, 'is_rest' => 1, 'blocks' => []]]);
        }

        $program = TrainingProgram::with('progressionRules')->find($assignment->training_program_id);
        $rule = $program->progressionRules->firstWhere('week_number', $assignment->week_number);
        $multiplier = $rule->load_multiplier ?? 1.00;

        // Batch-load overrides and logs BEFORE the loop
        $overrides = \App\Models\ClientExerciseOverride::where('program_day_assignment_id', $request->program_day_assignment_id)
            ->where('client_id', $client_id)
            ->get()
            ->keyBy('workout_template_exercise_id');

        $allExerciseIds = $assignment->workoutTemplate->blocks->flatMap(fn ($b) => $b->exercises->pluck('exercise_id')->values())->unique()->values()->all();
        $logs = ClientExerciseLog::where('client_id', $client_id)
            ->whereIn('exercise_id', $allExerciseIds)
            ->orderByDesc('created_at')
            ->get()
            ->unique('exercise_id')
            ->keyBy('exercise_id');

        $blocks = $assignment->workoutTemplate->blocks->map(function ($block) use ($multiplier, $overrides, $logs) {
            return [
                'block_id' => $block->id,
                'title'    => $block->title,
                'order'    => $block->order,
                'exercises' => $block->exercises->map(function ($ex) use ($multiplier, $overrides, $logs) {
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

                    if (isset($prescribed['carga']) && is_numeric($prescribed['carga'])) {
                        $prescribed['carga'] = round($prescribed['carga'] * $multiplier, 2);
                    }

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
                        'sets'            => $prescribed,
                        'coach_notes'     => $override->notes ?? null,
                        'enabled_metrics' => $ex->enabled_metrics ?? [],
                        'last_performance' => $last_log ? ['sets' => $last_log->logged_sets] : null,
                        'sequence'        => $ex->sequence,
                    ];
                })->values(),
            ];
        })->values();

        return json_custom_response([
            'data' => [
                'workout_day_id' => $assignment->id,
                'sequence'       => $assignment->day_of_week,
                'is_rest'        => 0,
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
            'program_day_assignment_id'     => 'nullable|exists:program_day_assignments,id',
            'notes'                         => 'nullable|string|max:2000',
        ]);

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

        return json_custom_response([
            'data' => $review,
            'achievements' => $achievements,
        ]);
    }
}
