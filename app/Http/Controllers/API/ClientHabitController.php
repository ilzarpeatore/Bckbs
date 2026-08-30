<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Habit;
use App\Models\HabitLog;

/**
 * Cliente: hábitos propios (asignados directo por el coach + adoptados de la
 * biblioteca global + 100% personales). Separado de HabitController (admin)
 * para no arriesgar ese flujo ya probado, y porque aquí sí hace falta
 * comprobar propiedad (un cliente no debe poder loguear/borrar el hábito
 * de otro cliente) — el controlador admin no lo comprueba porque solo lo
 * llama un admin de confianza.
 */
class ClientHabitController extends Controller
{
    /** Mis hábitos (asignados + adoptados + personales), con logs de los últimos N días (7 por defecto). */
    public function getMyList(Request $request)
    {
        $client_id = auth('sanctum')->id();
        $days = min((int) $request->input('days', 7), 400);

        $habits = Habit::forClient($client_id)
            ->with(['logs' => function ($q) use ($days) {
                $q->where('date', '>=', now()->subDays($days))->orderBy('date');
            }])
            ->orderByDesc('id')
            ->get();

        $data = $habits->map(function ($h) {
            return [
                'id'             => $h->id,
                'title'          => $h->title,
                'icon'           => $h->icon,
                'target_value'   => $h->target_value,
                'target_unit'    => $h->target_unit,
                'frequency'      => $h->frequency,
                'source_type'    => $h->source_type,
                'current_streak' => $h->current_streak,
                'logs'           => $h->logs,
            ];
        });

        return json_custom_response(['data' => $data]);
    }

    /** Biblioteca global de hábitos comunes (creados por el coach desde el admin) — solo lectura. */
    public function getLibrary()
    {
        $templates = Habit::templates()
            ->orderByDesc('id')
            ->get(['id', 'title', 'icon', 'category', 'target_value', 'target_unit', 'frequency']);

        return json_custom_response(['data' => $templates]);
    }

    /** Adoptar un hábito de la biblioteca global — crea una copia propia trackeable, ligada a la plantilla. */
    public function adopt(Request $request)
    {
        $request->validate(['template_id' => 'required|exists:habits,id']);

        $template = Habit::templates()->find($request->template_id);
        if (!$template) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Habit Template']));
        }

        $client_id = auth('sanctum')->id();

        $already = Habit::forClient($client_id)->where('source_template_id', $template->id)->exists();
        if ($already) {
            return json_message_response('Ya tienes este hábito en tu lista.');
        }

        $habit = Habit::create([
            'coach_id'           => null,
            'client_id'          => $client_id,
            'source_template_id' => $template->id,
            'title'              => $template->title,
            'icon'               => $template->icon,
            'target_value'       => $template->target_value,
            'target_unit'        => $template->target_unit,
            'frequency'          => $template->frequency,
        ]);

        return json_custom_response(['data' => $habit, 'msg' => __('message.save_form', ['form' => 'Habit'])]);
    }

    /** Crear un hábito 100% propio, sin coach ni plantilla de por medio. */
    public function storePersonal(Request $request)
    {
        $request->validate([
            'title'     => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly',
        ]);

        $habit = Habit::create([
            'coach_id'     => null,
            'client_id'    => auth('sanctum')->id(),
            'title'        => $request->title,
            'icon'         => $request->icon,
            'target_value' => $request->target_value,
            'target_unit'  => $request->target_unit,
            'frequency'    => $request->frequency,
        ]);

        return json_custom_response(['data' => $habit, 'msg' => __('message.save_form', ['form' => 'Habit'])]);
    }

    /** Registrar/actualizar mi log de un hábito propio para una fecha (por defecto hoy). */
    public function logHabit(Request $request)
    {
        $request->validate([
            'habit_id'     => 'required|exists:habits,id',
            'value_logged' => 'nullable|numeric|min:0',
            'is_completed' => 'nullable|boolean',
        ]);

        $habit = Habit::where('id', $request->habit_id)->where('client_id', auth('sanctum')->id())->first();
        if (!$habit) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Habit']));
        }

        $date = $request->date ?? now()->toDateString();
        $valueLogged = $request->filled('value_logged') ? (float) $request->value_logged : null;
        $isCompleted = $habit->resolveLogCompletion($valueLogged, $request->has('is_completed') ? $request->boolean('is_completed') : null);

        $log = HabitLog::updateOrCreate(
            ['habit_id' => $habit->id, 'date' => $date],
            [
                'value_logged' => $valueLogged,
                'is_completed' => $isCompleted,
            ]
        );

        return json_custom_response(['data' => $log]);
    }

    /** Quitar un hábito de mi lista (personal, adoptado, o asignado por el coach) — no borra la plantilla origen. */
    public function destroy(Request $request)
    {
        $habit = Habit::where('id', $request->id)->where('client_id', auth('sanctum')->id())->first();
        if (!$habit) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Habit']));
        }

        $habit->delete();

        return json_message_response(__('message.delete_form', ['form' => 'Habit']));
    }
}
