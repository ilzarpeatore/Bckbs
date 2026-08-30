<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProgramDayAssignment;
use App\Models\TrainingProgram;
use App\Services\ProgramCalendarGeneratorService;

class ProgramCalendarController extends Controller
{
    /** El calendario completo de un programa, agrupado por semana. */
    public function getCalendar(Request $request)
    {
        $request->validate(['training_program_id' => 'required|exists:training_programs,id']);

        $program = TrainingProgram::find($request->training_program_id);

        $assignments = ProgramDayAssignment::where('training_program_id', $request->training_program_id)
            ->with('workoutTemplate.blocks.exercises.exercise')
            ->orderBy('week_number')
            ->orderBy('day_of_week')
            ->get()
            ->groupBy('week_number');

        $calendar = $assignments->map(function ($days, $week) {
            return [
                'week_number'      => (int) $week,
                'days'             => $days->map(function ($day) {
                    return [
                        'assignment_id'   => $day->id,
                        'day_of_week'     => $day->day_of_week,
                        'scheduled_date'  => $day->scheduled_date,
                        'is_rest'         => $day->is_rest,
                        'workout'         => $day->workout_template_id
                            ? $this->serializeWorkout($day->workoutTemplate)
                            : null,
                    ];
                })->values(),
            ];
        })->values();

        return json_custom_response(['data' => $calendar]);
    }

    private function serializeWorkout($workout): array
    {
        return [
            'id'     => $workout->id,
            'title'  => $workout->title,
            'blocks' => $workout->blocks->map(function ($block) {
                return [
                    'id'    => $block->id,
                    'title' => $block->title,
                    'exercises' => $block->exercises->map(function ($ex) {
                        return [
                            'id'              => $ex->id,
                            'exercise_id'     => $ex->exercise_id,
                            'title'           => optional($ex->exercise)->title,
                            'prescribed'      => $ex->prescribed ?? [],
                            'enabled_metrics' => $ex->enabled_metrics,
                        ];
                    })->values(),
                ];
            })->values(),
        ];
    }

    /** Asignar (o quitar) un workout_template a un día concreto del calendario. */
    public function assignDay(Request $request)
    {
        $request->validate([
            'training_program_id'  => 'required|exists:training_programs,id',
            'week_number'          => 'required|integer|min:1',
            'day_of_week'          => 'required|integer|min:1|max:7',
            'workout_template_id'  => 'nullable|exists:workout_templates,id',
        ]);

        $assignment = ProgramDayAssignment::updateOrCreate(
            [
                'training_program_id' => $request->training_program_id,
                'week_number'          => $request->week_number,
                'day_of_week'          => $request->day_of_week,
            ],
            [
                'workout_template_id' => $request->workout_template_id,
                'scheduled_date'       => $request->scheduled_date,
            ]
        );

        return json_custom_response(['data' => $assignment]);
    }

    /** Genera las semanas 2..N replicando la Semana 1 (mismo workout_template, no clonado). */
    public function generateWeeks(Request $request)
    {
        $program = TrainingProgram::find($request->training_program_id);

        if ($program == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Program']));
        }

        $created = (new ProgramCalendarGeneratorService())->generateFromWeekOne($program);

        return json_custom_response(['data' => $created, 'message' => count($created).' días generados']);
    }
}
