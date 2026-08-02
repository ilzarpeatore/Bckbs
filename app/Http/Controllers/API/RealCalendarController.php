<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TrainingProgram;
use App\Models\ProgramDayAssignment;
use App\Models\ProgramClientAssignment;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use App\Services\CalendarDateMapper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RealCalendarController extends Controller
{
    /**
     * ACTUALIZADO: cada día devuelve un ARRAY de entrenamientos
     * (`workouts`, antes `workout` en singular) — ya se puede tener más
     * de uno el mismo día.
     */
    public function getMonth(Request $request)
    {
        $request->validate([
            'training_program_id' => 'required|exists:training_programs,id',
            'year'                 => 'required|integer',
            'month'                => 'required|integer|min:1|max:12',
        ]);

        $program = TrainingProgram::with('progressionRules')->findOrFail($request->training_program_id);
        $mapper = new CalendarDateMapper();
        $start_date = $this->resolveStartDate($request, $program);

        // AÑADIDO: ahora puede haber varias filas por (week_number, day_of_week)
        $assignments = ProgramDayAssignment::where('training_program_id', $program->id)
            ->with('workoutTemplate')
            ->get()
            ->groupBy(fn ($a) => $a->week_number.'-'.$a->day_of_week);

        $grid_dates = $mapper->getMonthGridDates((int) $request->year, (int) $request->month);

        $days = collect($grid_dates)->map(function (Carbon $date) use ($mapper, $start_date, $assignments, $program, $request) {
            $wd = $mapper->toWeekAndDay($start_date, $date);

            if ($wd['week_number'] < 1 || $wd['week_number'] > $program->num_weeks) {
                return [
                    'date'        => $date->toDateString(),
                    'in_month'    => $date->month == $request->month,
                    'in_range'    => false,
                    'week_number' => $wd['week_number'], // ACTUALIZADO: siempre se devuelve, para etiquetar la fila aunque esté fuera de rango
                    'day_of_week' => $wd['day_of_week'],
                    'workouts'    => [],
                ];
            }

            $day_assignments = $assignments->get($wd['week_number'].'-'.$wd['day_of_week'], collect());
            $rule = $program->progressionRules->firstWhere('week_number', $wd['week_number']);

            return [
                'date'         => $date->toDateString(),
                'in_month'     => $date->month == $request->month,
                'in_range'     => true,
                'week_number'  => $wd['week_number'],
                'day_of_week'  => $wd['day_of_week'],
                'is_deload'    => $rule->is_deload ?? false,
                // AÑADIDO: array, no un solo objeto
                'workouts'     => $day_assignments->filter(fn ($a) => $a->workout_template_id)->map(fn ($a) => [
                    'assignment_id' => $a->id,
                    'id'            => $a->workout_template_id,
                    'title'         => optional($a->workoutTemplate)->title,
                ])->values(),
            ];
        });

        return json_custom_response([
            'data' => [
                'program_title' => $program->title,
                'start_date'    => $start_date->toDateString(),
                'num_weeks'     => $program->num_weeks,
                'days'          => $days,
            ],
        ]);
    }

    /**
     * ACTUALIZADO: ya no usa updateOrCreate (que reemplazaba lo que
     * hubiera) — ahora cada asignación es una fila nueva independiente,
     * así que un día puede acumular varias.
     */
    public function assignDate(Request $request)
    {
        $request->validate([
            'training_program_id' => 'required|exists:training_programs,id',
            'date'                  => 'required|date',
            'workout_template_id'   => 'required|exists:workout_templates,id',
        ]);

        $program = TrainingProgram::findOrFail($request->training_program_id);
        $mapper = new CalendarDateMapper();
        $start_date = $this->resolveStartDate($request, $program);

        $target_date = Carbon::parse($request->date);
        $wd = $mapper->toWeekAndDay($start_date, $target_date);

        if ($wd['week_number'] < 1 || $wd['week_number'] > $program->num_weeks) {
            return json_message_response('Esa fecha queda fuera del rango de semanas del programa.', 422);
        }

        $assignment = ProgramDayAssignment::create([
            'training_program_id' => $program->id,
            'week_number'          => $wd['week_number'],
            'day_of_week'          => $wd['day_of_week'],
            'workout_template_id'  => $request->workout_template_id,
            'scheduled_date'       => $target_date->toDateString(),
        ]);

        return json_custom_response(['data' => $assignment]);
    }

    /** NUEVO: quitar una asignación concreta (por su id), sin tocar las demás del mismo día. */
    public function removeAssignment(Request $request)
    {
        $request->validate(['assignment_id' => 'required|exists:program_day_assignments,id']);

        ProgramDayAssignment::where('id', $request->assignment_id)->delete();

        return json_message_response('Entrenamiento quitado del día.');
    }

    /**
     * NUEVO: mover una asignación de una fecha a otra (para el
     * drag&drop entre días del calendario).
     */
    public function moveAssignment(Request $request)
    {
        $request->validate([
            'assignment_id' => 'required|exists:program_day_assignments,id',
            'new_date'      => 'required|date',
        ]);

        $assignment = ProgramDayAssignment::find($request->assignment_id);
        $program = TrainingProgram::find($assignment->training_program_id);
        $mapper = new CalendarDateMapper();
        $start_date = $this->resolveStartDate($request, $program);

        $wd = $mapper->toWeekAndDay($start_date, Carbon::parse($request->new_date));

        if ($wd['week_number'] < 1 || $wd['week_number'] > $program->num_weeks) {
            return json_message_response('Esa fecha queda fuera del rango de semanas del programa.', 422);
        }

        $assignment->update([
            'week_number'    => $wd['week_number'],
            'day_of_week'    => $wd['day_of_week'],
            'scheduled_date' => $request->new_date,
        ]);

        return json_custom_response(['data' => $assignment]);
    }

    private function resolveStartDate(Request $request, TrainingProgram $program): Carbon
    {
        if ($request->has('client_id') && $request->client_id) {
            $client_assignment = ProgramClientAssignment::where('training_program_id', $program->id)
                ->where('client_id', $request->client_id)
                ->first();

            if ($client_assignment) {
                return Carbon::parse($client_assignment->start_date);
            }
        }

        return $program->fecha_inicio ? Carbon::parse($program->fecha_inicio) : Carbon::today();
    }

    // =======================================================================
    // AÑADIDO: modo "semanas abstractas" — para editar una PLANTILLA (sin
    // cliente todavía), sin fechas reales de por medio.
    // =======================================================================

    public function getWeeksGrid(Request $request)
    {
        $request->validate(['training_program_id' => 'required|exists:training_programs,id']);

        $program = TrainingProgram::with('progressionRules')->findOrFail($request->training_program_id);

        // AÑADIDO: paginación — solo se calculan/devuelven las semanas
        // pedidas, no el programa entero de golpe.
        $weeks_per_page = (int) $request->input('weeks_per_page', 4);
        $start_week = max(1, (int) $request->input('start_week', 1));
        $end_week = min($program->num_weeks, $start_week + $weeks_per_page - 1);

        $assignments = ProgramDayAssignment::where('training_program_id', $program->id)
            ->whereBetween('week_number', [$start_week, $end_week])
            ->with('workoutTemplate')
            ->get()
            ->groupBy(fn ($a) => $a->week_number.'-'.$a->day_of_week);

        // Batch-load exercise counts and media for all workout templates
        $allTemplateIds = $assignments->flatten()->pluck('workout_template_id')->filter()->unique()->values()->all();
        $exerciseCounts = WorkoutTemplateExercise::whereIn('workout_template_blocks.workout_template_id', $allTemplateIds)
            ->join('workout_template_blocks', 'workout_template_exercises.workout_template_block_id', '=', 'workout_template_blocks.id')
            ->selectRaw('workout_template_blocks.workout_template_id as wt_id, COUNT(*) as cnt')
            ->groupBy('workout_template_blocks.workout_template_id')
            ->pluck('cnt', 'wt_id');
        $allMedia = WorkoutTemplate::whereIn('id', $allTemplateIds)
            ->get()
            ->mapWithKeys(fn ($wt) => [$wt->id => $wt->getFirstMedia('image')?->getUrl()]);

        $weeks = [];
        for ($week = $start_week; $week <= $end_week; $week++) {
            $rule = $program->progressionRules->firstWhere('week_number', $week);
            $days = [];

            for ($day = 1; $day <= 7; $day++) {
                $day_assignments = $assignments->get($week.'-'.$day, collect());

                $days[] = [
                    'week_number' => $week,
                    'day_of_week' => $day,
                        'workouts'    => $day_assignments->filter(fn ($a) => $a->workout_template_id)->map(function ($a) use ($allMedia, $exerciseCounts) {
                        $wt = $a->workoutTemplate;
                        return [
                            'assignment_id'   => $a->id,
                            'id'              => $a->workout_template_id,
                            'title'           => $wt?->title,
                            'thumbnail'       => $allMedia->get($a->workout_template_id),
                            'exercise_count'  => $exerciseCounts->get($a->workout_template_id, 0),
                        ];
                    })->values(),
                ];
            }

            $weeks[] = [
                'week_number'     => $week,
                'is_deload'       => $rule->is_deload ?? false,
                'load_multiplier' => $rule->load_multiplier ?? 1.00,
                'days'            => $days,
            ];
        }

        return json_custom_response([
            'data' => [
                'program_title' => $program->title,
                'weeks'         => $weeks,
                'start_week'    => $start_week,
                'end_week'      => $end_week,
                'num_weeks'     => $program->num_weeks,
            ],
        ]);
    }

    /** NUEVO: duplica todos los entrenamientos de una semana en otra. */
    public function duplicateWeek(Request $request)
    {
        $request->validate([
            'training_program_id' => 'required|exists:training_programs,id',
            'source_week'          => 'required|integer|min:1',
            'target_week'          => 'required|integer|min:1',
        ]);

        $source_assignments = ProgramDayAssignment::where('training_program_id', $request->training_program_id)
            ->where('week_number', $request->source_week)
            ->get();

        if ($source_assignments->isEmpty()) {
            return json_message_response('Esa semana no tenía ningún entrenamiento que duplicar.', 200);
        }

        DB::transaction(function () use ($source_assignments, $request) {
            foreach ($source_assignments as $a) {
                ProgramDayAssignment::create([
                    'training_program_id' => $request->training_program_id,
                    'week_number'          => $request->target_week,
                    'day_of_week'          => $a->day_of_week,
                    'workout_template_id'  => $a->workout_template_id,
                ]);
            }
        });

        return json_message_response('Semana duplicada ('.$source_assignments->count().' entrenamiento(s)).');
    }

    /** NUEVO: vacía todos los entrenamientos de una semana (sin borrar la semana en sí, solo su contenido). */
    public function clearWeek(Request $request)
    {
        $request->validate([
            'training_program_id' => 'required|exists:training_programs,id',
            'week_number'          => 'required|integer|min:1',
        ]);

        $deleted = ProgramDayAssignment::where('training_program_id', $request->training_program_id)
            ->where('week_number', $request->week_number)
            ->delete();

        if ($deleted === 0) {
            return json_message_response('Esa semana ya estaba vacía.', 200);
        }

        return json_message_response('Semana vaciada ('.$deleted.' entrenamiento(s) quitados).');
    }

    /** NUEVO: intercambia el contenido completo de dos semanas (mover arriba/abajo). */
    public function swapWeeks(Request $request)
    {
        $request->validate([
            'training_program_id' => 'required|exists:training_programs,id',
            'week_a'               => 'required|integer|min:1',
            'week_b'               => 'required|integer|min:1',
        ]);

        // AÑADIDO: contar ANTES de mover, para poder avisar si no había nada en ninguna de las dos
        $count_a = ProgramDayAssignment::where('training_program_id', $request->training_program_id)->where('week_number', $request->week_a)->count();
        $count_b = ProgramDayAssignment::where('training_program_id', $request->training_program_id)->where('week_number', $request->week_b)->count();

        if ($count_a === 0 && $count_b === 0) {
            return json_message_response('Ninguna de las dos semanas tenía entrenamientos en este calendario — no había nada que intercambiar aquí (recuerda: estas acciones solo afectan a lo asignado directo, no a lo importado de otros programas).', 200);
        }

        // CORREGIDO: la columna week_number es UNSIGNED, no admite -1 — se usa 999999 como centinela temporal (nunca colisiona con una semana real).
        DB::transaction(function () use ($request) {
            ProgramDayAssignment::where('training_program_id', $request->training_program_id)
                ->where('week_number', $request->week_a)
                ->update(['week_number' => 999999]);

            ProgramDayAssignment::where('training_program_id', $request->training_program_id)
                ->where('week_number', $request->week_b)
                ->update(['week_number' => $request->week_a]);

            ProgramDayAssignment::where('training_program_id', $request->training_program_id)
                ->where('week_number', 999999)
                ->update(['week_number' => $request->week_b]);
        });

        return json_message_response('Semanas intercambiadas ('.$count_a.' y '.$count_b.' entrenamiento(s) respectivamente).');
    }

    public function assignWeekDay(Request $request)
    {
        $request->validate([
            'training_program_id' => 'required|exists:training_programs,id',
            'week_number'          => 'required|integer|min:1',
            'day_of_week'          => 'required|integer|min:1|max:7',
            'workout_template_id'  => 'required|exists:workout_templates,id',
        ]);

        $assignment = ProgramDayAssignment::create([
            'training_program_id' => $request->training_program_id,
            'week_number'          => $request->week_number,
            'day_of_week'          => $request->day_of_week,
            'workout_template_id'  => $request->workout_template_id,
        ]);

        return json_custom_response(['data' => $assignment]);
    }

    public function moveWeekDay(Request $request)
    {
        $request->validate([
            'assignment_id'    => 'required|exists:program_day_assignments,id',
            'new_week_number'  => 'required|integer|min:1',
            'new_day_of_week'  => 'required|integer|min:1|max:7',
        ]);

        ProgramDayAssignment::where('id', $request->assignment_id)->update([
            'week_number' => $request->new_week_number,
            'day_of_week' => $request->new_day_of_week,
        ]);

        return json_message_response('Movido.');
    }

    public function duplicateWeekDay(Request $request)
    {
        $request->validate([
            'assignment_id'    => 'required|exists:program_day_assignments,id',
            'new_week_number'  => 'required|integer|min:1',
            'new_day_of_week'  => 'required|integer|min:1|max:7',
        ]);

        $source = ProgramDayAssignment::find($request->assignment_id);

        $new_assignment = ProgramDayAssignment::create([
            'training_program_id' => $source->training_program_id,
            'week_number'          => $request->new_week_number,
            'day_of_week'          => $request->new_day_of_week,
            'workout_template_id'  => $source->workout_template_id,
        ]);

        return json_custom_response(['data' => $new_assignment]);
    }
}
