<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TrainingProgram;
use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
use App\Models\WorkoutTemplateExercise;
use App\Services\CalendarDateMapper;
use Carbon\Carbon;

class ClientProfileCalendarController extends Controller
{
    /** Fecha ancla fija para todos los calendarios personales — estable, no depende de cuándo se creó. */
    const PERSONAL_ANCHOR_DATE = '2020-01-06'; // un lunes

    /**
     * Encuentra (o crea la primera vez) el calendario personal de este
     * cliente. num_weeks muy alto = efectivamente "sin límite" para uso
     * normal (unos 19 años).
     */
    private function getOrCreatePersonalProgram(int $client_id): TrainingProgram
    {
        $program = TrainingProgram::where('personal_client_id', $client_id)
            ->where('is_personal', true)
            ->first();

        if ($program) {
            // Asegura que el programa personal tenga su fila en
            // program_client_assignments (programas creados antes de esa
            // lógica no la tienen), o getMergedMonth nunca lo recorrería.
            ProgramClientAssignment::firstOrCreate(
                ['training_program_id' => $program->id, 'client_id' => $client_id],
                ['start_date' => self::PERSONAL_ANCHOR_DATE, 'activo' => true]
            );

            return $program;
        }

        $program = TrainingProgram::create([
            'title'              => 'Calendario personal',
            'is_personal'        => true,
            'personal_client_id' => $client_id,
            'coach_id'           => auth()->id(),
            'num_weeks'          => 1000,
            'fecha_inicio'       => self::PERSONAL_ANCHOR_DATE,
            'activo'             => true,
        ]);

        ProgramClientAssignment::create([
            'training_program_id' => $program->id,
            'client_id'            => $client_id,
            'start_date'           => self::PERSONAL_ANCHOR_DATE,
            'activo'               => true,
        ]);

        return $program;
    }

    /**
     * El calendario del mes, combinando TODOS los programas activos
     * asignados a este cliente (el personal + cualquier plantilla
     * importada), fusionados en una sola vista por fecha.
     */
    public function getMergedMonth(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:users,id',
            'year'      => 'required|integer',
            'month'     => 'required|integer|min:1|max:12',
        ]);

        $personal_program = $this->getOrCreatePersonalProgram($request->client_id);

        $client_assignments = ProgramClientAssignment::where('client_id', $request->client_id)
            ->where('activo', true)
            ->with('trainingProgram.progressionRules')
            ->get();

        $mapper = new CalendarDateMapper();
        $grid_dates = $mapper->getMonthGridDates((int) $request->year, (int) $request->month);
        $personal_anchor = Carbon::parse(self::PERSONAL_ANCHOR_DATE);

        // AÑADIDO: week_number calculado sobre el calendario PERSONAL (fecha
        // ancla fija) — sirve para etiquetar la fila aunque el día combine
        // entrenamientos de varios programas distintos.
        $days_map = collect($grid_dates)->keyBy(fn ($d) => $d->toDateString())
            ->map(function ($d) use ($mapper, $personal_anchor, $request) {
                $personal_wd = $mapper->toWeekAndDay($personal_anchor, $d);
                return [
                    'date'                 => $d->toDateString(),
                    'in_month'             => $d->month == $request->month,
                    'personal_week_number' => $personal_wd['week_number'],
                    'workouts'             => collect(),
                ];
            });

        foreach ($client_assignments as $client_assignment) {
            $program = $client_assignment->trainingProgram;
            if (!$program) continue;

            $start_date = Carbon::parse($client_assignment->start_date);

            $assignments = ProgramDayAssignment::where('training_program_id', $program->id)
                ->with('workoutTemplate')
                ->get()
                ->groupBy(fn ($a) => $a->week_number.'-'.$a->day_of_week);

            // Batch-load exercise counts for ALL workout templates in this program
            $allTemplateIds = $assignments->flatten()->pluck('workout_template_id')->filter()->unique()->values()->all();
            $exerciseCounts = WorkoutTemplateExercise::whereIn('workout_template_blocks.workout_template_id', $allTemplateIds)
                ->join('workout_template_blocks', 'workout_template_exercises.workout_template_block_id', '=', 'workout_template_blocks.id')
                ->selectRaw('workout_template_blocks.workout_template_id as wt_id, COUNT(*) as cnt')
                ->groupBy('workout_template_blocks.workout_template_id')
                ->pluck('cnt', 'wt_id');

            // Batch-load media for all templates
            $allMedia = \App\Models\WorkoutTemplate::whereIn('id', $allTemplateIds)
                ->get()
                ->mapWithKeys(fn ($wt) => [$wt->id => $wt->getFirstMedia('image')?->getUrl()]);

            foreach ($grid_dates as $date) {
                $wd = $mapper->toWeekAndDay($start_date, $date);
                if ($wd['week_number'] < 1 || $wd['week_number'] > $program->num_weeks) continue;

                $day_assignments = $assignments->get($wd['week_number'].'-'.$wd['day_of_week'], collect());

                foreach ($day_assignments as $a) {
                    if (!$a->workout_template_id) continue;

                    $wt = $a->workoutTemplate;

                    $days_map[$date->toDateString()]['workouts']->push([
                        'assignment_id'         => $a->id,
                        'id'                    => $a->workout_template_id,
                        'title'                 => $wt?->title,
                        'program_title'         => $program->is_personal ? null : $program->title,
                        'is_personal'           => $program->is_personal,
                        'training_program_id'   => $program->is_personal ? null : $program->id,
                        'thumbnail'             => $allMedia->get($a->workout_template_id),
                        'exercise_count'        => $exerciseCounts->get($a->workout_template_id, 0),
                    ]);
                }
            }
        }

        return json_custom_response([
            'data' => [
                'days'                     => $days_map->values(),
                'personal_training_program_id' => $personal_program->id, // AÑADIDO
            ],
        ]);
    }

    /** Asignación directa — siempre va al calendario PERSONAL del cliente. */
    public function assignDirect(Request $request)
    {
        $request->validate([
            'client_id'           => 'required|exists:users,id',
            'date'                => 'required|date',
            'workout_template_id' => 'required|exists:workout_templates,id',
        ]);

        $program = $this->getOrCreatePersonalProgram($request->client_id);
        $mapper = new CalendarDateMapper();
        $wd = $mapper->toWeekAndDay(Carbon::parse(self::PERSONAL_ANCHOR_DATE), Carbon::parse($request->date));

        $assignment = ProgramDayAssignment::create([
            'training_program_id' => $program->id,
            'week_number'          => $wd['week_number'],
            'day_of_week'          => $wd['day_of_week'],
            'workout_template_id'  => $request->workout_template_id,
            'scheduled_date'       => $request->date,
        ]);

        return json_custom_response(['data' => $assignment]);
    }

    /**
     * "Importar programa completo" — asigna una plantilla de la
     * biblioteca a este cliente con la fecha de inicio elegida. A
     * partir de ahí, sus días se sincronizan solos en este calendario
     * combinado (es justo `ProgramClientAssignment`, ya existente).
     */
    public function importProgram(Request $request)
    {
        $request->validate([
            'client_id'            => 'required|exists:users,id',
            'training_program_id'  => 'required|exists:training_programs,id',
            'start_date'           => 'required|date',
        ]);

        $assignment = ProgramClientAssignment::create([
            'training_program_id' => $request->training_program_id,
            'client_id'            => $request->client_id,
            'start_date'           => $request->start_date,
            'activo'               => true,
        ]);

        return json_custom_response(['data' => $assignment]);
    }

    public function removeAssignment(Request $request)
    {
        $request->validate(['assignment_id' => 'required|exists:program_day_assignments,id']);
        ProgramDayAssignment::where('id', $request->assignment_id)->delete();
        return json_message_response('Entrenamiento quitado.');
    }

    /**
     * Visibilidad admin del feedback post-entrenamiento (workout_feedback_screen.tsx
     * -> finishSession) y de las notas por ejercicio que el cliente escribe
     * durante la sesion (workout_session_screen.tsx) - antes ninguno de los
     * dos era visible desde el panel, solo se guardaban en el backend.
     */
    public function getSessionFeedback(Request $request)
    {
        $request->validate(['client_id' => 'required|exists:users,id']);

        $reviews = \App\Models\WorkoutSessionReview::where('user_id', $request->client_id)
            ->whereNotNull('completed_at')
            ->with(['programDayAssignment.workoutTemplate:id,title', 'workoutTemplate:id,title'])
            ->orderByDesc('completed_at')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'id'                => $r->id,
                'date'              => optional($r->completed_at)->toDateTimeString(),
                'workout_title'     => optional($r->programDayAssignment?->workoutTemplate)->title
                                        ?? optional($r->workoutTemplate)->title,
                'duration_seconds'  => $r->duration_seconds,
                'volume_kg'         => $r->volume_kg,
                'calories_burned'   => $r->calories_burned,
                'difficulty_rating' => $r->difficulty_rating,
                'comment'           => $r->comment,
            ]);

        $notes = \App\Models\ClientExerciseLog::where('client_id', $request->client_id)
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->with('exercise:id,title')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($log) => [
                'id'            => $log->id,
                'date'          => optional($log->performed_date)->toDateString(),
                'exercise_title' => optional($log->exercise)->title ?? 'Ejercicio',
                'notes'         => $log->notes,
            ]);

        return json_custom_response([
            'data' => [
                'reviews' => $reviews,
                'exercise_notes' => $notes,
            ],
        ]);
    }
}
