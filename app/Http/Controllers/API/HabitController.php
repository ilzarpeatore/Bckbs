<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Habit;
use App\Models\HabitLog;

class HabitController extends Controller
{
    public function getList(Request $request)
    {
        $user = auth('sanctum')->user();
        $client_id = $request->filled('client_id') ? $request->client_id : $user->id;

        $habit = Habit::forClient($client_id)->with(['logs' => function ($q) {
            $q->where('date', '>=', now()->subDays(7));
        }]);

        $habit = $habit->get();

        // "Weekly Progress %" y streak se calculan aquí, no se guardan.
        $data = $habit->map(function ($h) {
            return [
                'id'             => $h->id,
                'title'          => $h->title,
                'icon'           => $h->icon,
                'target_value'   => $h->target_value,
                'target_unit'    => $h->target_unit,
                'frequency'      => $h->frequency,
                'current_streak' => $h->current_streak,
                'last_7_days'    => $h->logs,
            ];
        });

        return json_custom_response(['data' => $data]);
    }

    /**
     * Registrar/actualizar el log de un hábito para una fecha (por defecto hoy).
     * Un único registro por hábito+fecha (constraint unique en la migración).
     */
    public function logHabit(Request $request)
    {
        $request->validate([
            'habit_id' => 'required|exists:habits,id',
        ]);

        $date = $request->date ?? now()->toDateString();

        $log = HabitLog::updateOrCreate(
            ['habit_id' => $request->habit_id, 'date' => $date],
            [
                'value_logged' => $request->value_logged,
                'is_completed' => $request->is_completed ?? true,
            ]
        );

        return json_custom_response(['data' => $log]);
    }

    /** Alta de hábito — normalmente desde el panel Admin (coach asigna a un cliente). */
    public function store(Request $request)
    {
        $request->validate([
            'title'    => 'required|string|max:255',
            'client_id' => 'required|exists:users,id',
            'frequency' => 'required|in:daily,weekly',
        ]);

        $habit = Habit::create([
            'coach_id'     => auth('sanctum')->id(),
            'client_id'    => $request->client_id,
            'title'        => $request->title,
            'icon'         => $request->icon,
            'target_value' => $request->target_value,
            'target_unit'  => $request->target_unit,
            'frequency'    => $request->frequency,
        ]);

        return json_message_response(__('message.save_form', ['form' => 'Habit']));
    }

    public function destroy(Request $request)
    {
        $habit = Habit::find($request->id);

        if ($habit == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Habit']));
        }

        $habit->delete();

        return json_message_response(__('message.delete_form', ['form' => 'Habit']));
    }
}
