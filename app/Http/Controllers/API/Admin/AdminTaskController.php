<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminTaskController extends Controller
{
    public function getList(Request $request)
    {
        $query = AdminTask::with([
            'client:id,first_name,last_name,email',
            'creator:id,first_name,last_name',
        ]);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', config('constant.PER_PAGE_LIMIT', 20));
        $tasks = $query->orderByRaw("FIELD(status, 'in_progress', 'pending', 'done')")
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return json_custom_response([
            'pagination' => json_pagination_response($tasks),
            'data'       => $tasks,
        ]);
    }

    public function getDetail(Request $request)
    {
        $request->validate(['id' => 'required|exists:admin_tasks,id']);

        $task = AdminTask::with(['client:id,first_name,last_name,email', 'creator:id,first_name,last_name'])
            ->findOrFail($request->id);

        return json_custom_response(['data' => $task]);
    }

    /**
     * Crea una tarea de gestion (a mano, por el admin). `type` se fuerza a
     * `management` aqui -- las de `dev` solo se crean via sync().
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'nullable|in:entrenamiento,nutricion,revisiones,otro',
            'priority'    => 'nullable|in:alta,media,baja',
            'due_date'    => 'nullable|date',
            'client_id'   => 'nullable|exists:users,id',
        ]);

        $task = AdminTask::create([
            'type'        => 'management',
            'title'       => $request->title,
            'description' => $request->description,
            'category'    => $request->category,
            'priority'    => $request->priority,
            'due_date'    => $request->due_date,
            'client_id'   => $request->client_id,
            'created_by'  => auth()->id(),
            'status'      => 'pending',
        ]);

        return json_custom_response(['data' => $task, 'message' => 'Tarea creada.']);
    }

    public function update(Request $request)
    {
        $request->validate([
            'id'          => 'required|exists:admin_tasks,id',
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'sometimes|in:pending,in_progress,done',
            'category'    => 'nullable|in:entrenamiento,nutricion,revisiones,otro',
            'priority'    => 'nullable|in:alta,media,baja',
            'due_date'    => 'nullable|date',
            'client_id'   => 'nullable|exists:users,id',
        ]);

        $task = AdminTask::findOrFail($request->id);

        $data = $request->only(['title', 'description', 'category', 'priority', 'due_date', 'client_id']);

        if ($request->filled('status')) {
            $data['status'] = $request->status;
            $data['completed_at'] = $request->status === 'done' ? now() : null;
        }

        $task->update($data);

        return json_custom_response(['data' => $task, 'message' => 'Tarea actualizada.']);
    }

    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|exists:admin_tasks,id']);

        AdminTask::findOrFail($request->id)->delete();

        return json_custom_response(['message' => 'Tarea eliminada.']);
    }

    /**
     * Sincroniza las tareas `dev` desde docs/ROADMAP.md de `bsa`. Llamado
     * por Claude Code (no por la UI) con un token dedicado (ability
     * `tasks:sync`, ver AuthServiceProvider/token creado a mano).
     *
     * Contrato: el payload es SIEMPRE la lista completa de items pendientes
     * de ese `source_repo` en este momento -- upsert por (type=dev,
     * source_repo, source_key), y cualquier tarea dev de ese repo que ya
     * estuviera en BD pero no venga en este payload se marca `done`
     * automaticamente (ya no esta pendiente en el roadmap).
     */
    public function sync(Request $request)
    {
        if (!$request->user()->tokenCan('tasks:sync')) {
            abort(403, 'Token sin permiso para sincronizar tareas.');
        }

        $request->validate([
            'source_repo'            => 'required|string|max:100',
            'items'                  => 'present|array',
            'items.*.source_key'     => 'required|string|max:100',
            'items.*.title'          => 'required|string|max:255',
            'items.*.description'    => 'nullable|string',
            'items.*.status'         => 'required|in:pending,in_progress,done',
            'items.*.source_url'     => 'nullable|string|max:500',
        ]);

        $sourceRepo = $request->source_repo;
        $items = $request->items;
        $seenKeys = [];

        $result = DB::transaction(function () use ($sourceRepo, $items, &$seenKeys) {
            $upserted = [];

            foreach ($items as $item) {
                $seenKeys[] = $item['source_key'];

                $task = AdminTask::updateOrCreate(
                    [
                        'type'        => 'dev',
                        'source_repo' => $sourceRepo,
                        'source_key'  => $item['source_key'],
                    ],
                    [
                        'title'        => $item['title'],
                        'description'  => $item['description'] ?? null,
                        'status'       => $item['status'],
                        'source_url'   => $item['source_url'] ?? null,
                        'completed_at' => $item['status'] === 'done' ? now() : null,
                    ]
                );

                $upserted[] = $task->id;
            }

            $closedCount = AdminTask::where('type', 'dev')
                ->where('source_repo', $sourceRepo)
                ->whereNotIn('source_key', $seenKeys ?: [''])
                ->where('status', '!=', 'done')
                ->update(['status' => 'done', 'completed_at' => now()]);

            return ['upserted' => count($upserted), 'auto_closed' => $closedCount];
        });

        return json_custom_response([
            'message' => 'Sincronizacion completada.',
            'data'    => $result,
        ]);
    }
}
