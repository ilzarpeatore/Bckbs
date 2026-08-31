<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use App\Notifications\CommonNotification;

/**
 * Admin/coach: gestión de hábitos — asignación directa a un cliente,
 * biblioteca global de plantillas (reutilizables por todos los clientes),
 * y vista de progreso real de un cliente. Todas las rutas de este
 * controlador viven bajo admin/ + middleware admin.api (ver routes/api.php).
 * El acceso de cliente (mi lista, adoptar de biblioteca, crear personal,
 * loguear, borrar) vive en ClientHabitController — separado a propósito
 * para no tocar ni arriesgar este flujo admin, ya probado.
 */
class HabitController extends Controller
{
    /**
     * ?templates=1           -> biblioteca global (plantillas, client_id null).
     * ?client_id=&days=      -> hábitos asignados a ese cliente (por defecto, últimos 7 días de logs).
     */
    public function getList(Request $request)
    {
        if ($request->boolean('templates')) {
            $templates = Habit::templates()
                ->withCount(['adoptions as adopted_count'])
                ->orderByDesc('id')
                ->get(['id', 'title', 'icon', 'category', 'target_value', 'target_unit', 'frequency', 'created_at']);

            return json_custom_response(['data' => $templates]);
        }

        $user = auth('sanctum')->user();
        $client_id = $request->filled('client_id') ? $request->client_id : $user->id;
        $days = min((int) $request->input('days', 7), 400);

        $habit = Habit::forClient($client_id)->with(['logs' => function ($q) use ($days) {
            $q->where('date', '>=', now()->subDays($days))->orderBy('date');
        }])->orderByDesc('id')->get();

        // "Weekly Progress %" y streak se calculan aquí, no se guardan.
        $data = $habit->map(function ($h) {
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

    /**
     * Registrar/actualizar el log de un hábito para una fecha (por defecto hoy).
     * Un único registro por hábito+fecha (constraint unique en la migración).
     * Sin comprobación de propiedad porque solo la llama un admin de confianza
     * (ver ClientHabitController::logHabit para la versión de cliente, que sí valida).
     */
    public function logHabit(Request $request)
    {
        $request->validate([
            'habit_id'     => 'required|exists:habits,id',
            'value_logged' => 'nullable|numeric|min:0',
            'is_completed' => 'nullable|boolean',
        ]);

        $habit = Habit::find($request->habit_id);
        $date = $request->date ?? now()->toDateString();
        $valueLogged = $request->filled('value_logged') ? (float) $request->value_logged : null;
        $isCompleted = $habit->resolveLogCompletion($valueLogged, $request->has('is_completed') ? $request->boolean('is_completed') : null);

        $log = HabitLog::updateOrCreate(
            ['habit_id' => $request->habit_id, 'date' => $date],
            [
                'value_logged' => $valueLogged,
                'is_completed' => $isCompleted,
            ]
        );

        return json_custom_response(['data' => $log]);
    }

    /** Alta de hábito asignado directo a un cliente — desde el panel Admin. */
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

        $client = User::find($request->client_id);
        if ($client) {
            $client->notify(new CommonNotification('new_habit', [
                'id'      => $habit->id,
                'type'    => 'new_habit',
                'subject' => 'Nuevo hábito asignado',
                'message' => "Tu coach te ha asignado un nuevo hábito: \"{$habit->title}\".",
            ]));
        }

        return json_message_response(__('message.save_form', ['form' => 'Habit']));
    }

    /**
     * NUEVO: la vista admin ya llamaba a /admin/habit-update desde antes de esta
     * ronda, pero el método no existía (404 silencioso — "Editar hábito" nunca
     * funcionó). Añadido con la misma validación que store().
     */
    public function update(Request $request)
    {
        $request->validate([
            'id'        => 'required|exists:habits,id',
            'title'     => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly',
        ]);

        $habit = Habit::whereNotNull('client_id')->find($request->id);
        if (!$habit) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Habit']));
        }

        $habit->update($request->only(['title', 'icon', 'target_value', 'target_unit', 'frequency']));

        return json_custom_response(['data' => $habit, 'msg' => __('message.save_form', ['form' => 'Habit'])]);
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

    /** Alta de una plantilla de biblioteca global (client_id null) — visible/adoptable por todos los clientes. */
    public function storeTemplate(Request $request)
    {
        $request->validate([
            'title'     => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly',
        ]);

        $habit = Habit::create([
            'coach_id'     => auth('sanctum')->id(),
            'client_id'    => null,
            'title'        => $request->title,
            'icon'         => $request->icon,
            'category'     => $request->category,
            'target_value' => $request->target_value,
            'target_unit'  => $request->target_unit,
            'frequency'    => $request->frequency,
        ]);

        return json_custom_response(['data' => $habit, 'msg' => __('message.save_form', ['form' => 'Habit Template'])]);
    }

    public function updateTemplate(Request $request)
    {
        $request->validate([
            'id'        => 'required|exists:habits,id',
            'title'     => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly',
        ]);

        $habit = Habit::templates()->find($request->id);
        if (!$habit) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Habit Template']));
        }

        $habit->update($request->only(['title', 'icon', 'category', 'target_value', 'target_unit', 'frequency']));

        return json_custom_response(['data' => $habit, 'msg' => __('message.save_form', ['form' => 'Habit Template'])]);
    }

    /**
     * Borra solo si es realmente una plantilla (client_id null) — evita que este
     * endpoint se use por error para borrar un hábito ya asignado a un cliente.
     * Los hábitos ya adoptados de esta plantilla no se tocan (source_template_id
     * pasa a null por el onDelete('set null') de la migración) — conservan su historial.
     */
    public function destroyTemplate(Request $request)
    {
        $habit = Habit::templates()->find($request->id);
        if (!$habit) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Habit Template']));
        }
        $habit->delete();

        return json_message_response(__('message.delete_form', ['form' => 'Habit Template']));
    }

    /**
     * Progreso real de un cliente concreto — para que el coach vea cómo se
     * adapta a cada hábito (asignado directo, adoptado de biblioteca, o
     * 100% personal): racha, % de cumplimiento, e historial completo de logs.
     */
    public function getClientProgress(Request $request)
    {
        $request->validate(['client_id' => 'required|exists:users,id']);
        $days = min((int) $request->input('days', 371), 400);

        $habits = Habit::forClient($request->client_id)
            ->with(['logs' => function ($q) use ($days) {
                $q->where('date', '>=', now()->subDays($days))->orderBy('date');
            }, 'sourceTemplate:id,title'])
            ->orderByDesc('id')
            ->get();

        $data = $habits->map(function ($h) {
            $logs = $h->logs;

            return [
                'id'                    => $h->id,
                'title'                 => $h->title,
                'icon'                  => $h->icon,
                'target_value'          => $h->target_value,
                'target_unit'           => $h->target_unit,
                'frequency'             => $h->frequency,
                'source_type'           => $h->source_type,
                'source_template_title' => $h->sourceTemplate?->title,
                'current_streak'        => $h->current_streak,
                'completion_count'      => $logs->where('is_completed', true)->count(),
                'days_tracked'          => $logs->count(),
                'created_at'            => $h->created_at,
                'logs'                  => $logs->map(fn ($l) => [
                    'date'         => $l->date->format('Y-m-d'),
                    'is_completed' => $l->is_completed,
                    'value_logged' => $l->value_logged,
                ]),
            ];
        });

        return json_custom_response(['data' => $data]);
    }
}
