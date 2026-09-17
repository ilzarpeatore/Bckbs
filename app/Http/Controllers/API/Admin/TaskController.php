<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function getList(Request $request)
    {
        $query = Task::with(['author:id,first_name,last_name', 'client:id,first_name,last_name,email']);

        // Sin filtro explicito, "management" es la vista por defecto -- las
        // `dev` (sincronizadas por Claude Code) solo aparecen si se piden.
        $query->where('type', $request->get('type', 'management'));

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
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

        $perPage = $request->get('per_page', 50);
        $tasks = $query->orderBy('due_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $response = [
            'pagination' => json_pagination_response($tasks),
            'data'       => $tasks,
        ];

        return json_custom_response($response);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_id'   => 'nullable|exists:users,id',
            'due_date'    => 'nullable|date',
            'priority'    => 'sometimes|in:low,medium,high',
            'status'      => 'sometimes|in:pending,in_progress,completed',
            'category'    => 'nullable|in:entrenamiento,nutricion,revisiones,otro',
        ]);

        $task = Task::create([
            'type'        => 'management',
            'author_id'   => auth('sanctum')->id(),
            'client_id'   => $request->client_id,
            'title'       => $request->title,
            'description' => $request->description,
            'due_date'    => $request->due_date,
            'priority'    => $request->get('priority', 'medium'),
            'status'      => $request->get('status', 'pending'),
            'category'    => $request->category,
        ]);

        $task->load(['author:id,first_name,last_name', 'client:id,first_name,last_name,email']);

        return json_custom_response(['data' => $task, 'message' => 'Task created.'], 201);
    }

    public function update(Request $request)
    {
        $request->validate([
            'id'          => 'required|exists:tasks,id',
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'client_id'   => 'nullable|exists:users,id',
            'due_date'    => 'nullable|date',
            'priority'    => 'sometimes|in:low,medium,high',
            'status'      => 'sometimes|in:pending,in_progress,completed',
            'category'    => 'nullable|in:entrenamiento,nutricion,revisiones,otro',
        ]);

        $task = Task::findOrFail($request->id);

        $data = $request->only(['title', 'description', 'client_id', 'due_date', 'priority', 'status', 'category']);

        if ($request->filled('status')) {
            $data['completed_at'] = $request->status === 'completed' ? now() : null;
        }

        $task->update($data);

        $task->load(['author:id,first_name,last_name', 'client:id,first_name,last_name,email']);

        return json_custom_response(['data' => $task, 'message' => 'Task updated.']);
    }

    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|exists:tasks,id']);

        Task::findOrFail($request->id)->delete();

        return json_message_response('Task deleted.');
    }

    /**
     * Sincroniza las tareas `dev` desde docs/ROADMAP.md de `bsa`. Llamado
     * por Claude Code (no por la UI del panel) con un token dedicado
     * (ability `tasks:sync`).
     *
     * Contrato: el payload es SIEMPRE la lista completa de items
     * pendientes de ese `source_repo` en este momento -- upsert por
     * (type=dev, source_repo, source_key), y cualquier tarea dev de ese
     * repo que ya estuviera en BD pero no venga en este payload se marca
     * `completed` automaticamente (ya no esta pendiente en el roadmap).
     */
    public function sync(Request $request)
    {
        if (!$request->user()->tokenCan('tasks:sync')) {
            abort(403, 'Token sin permiso para sincronizar tareas.');
        }

        $request->validate([
            'source_repo'         => 'required|string|max:100',
            'items'                => 'present|array',
            'items.*.source_key'   => 'required|string|max:100',
            'items.*.title'        => 'required|string|max:255',
            'items.*.description'  => 'nullable|string',
            'items.*.status'       => 'required|in:pending,in_progress,completed',
            'items.*.source_url'   => 'nullable|string|max:500',
        ]);

        $sourceRepo = $request->source_repo;
        $items = $request->items;
        $authorId = $request->user()->id;
        $seenKeys = [];

        $result = DB::transaction(function () use ($sourceRepo, $items, $authorId, &$seenKeys) {
            $upserted = [];

            foreach ($items as $item) {
                $seenKeys[] = $item['source_key'];

                $task = Task::updateOrCreate(
                    [
                        'type'        => 'dev',
                        'source_repo' => $sourceRepo,
                        'source_key'  => $item['source_key'],
                    ],
                    [
                        'author_id'    => $authorId,
                        'title'        => $item['title'],
                        'description'  => $item['description'] ?? null,
                        'status'       => $item['status'],
                        'source_url'   => $item['source_url'] ?? null,
                        'completed_at' => $item['status'] === 'completed' ? now() : null,
                    ]
                );

                $upserted[] = $task->id;
            }

            $closedCount = Task::where('type', 'dev')
                ->where('source_repo', $sourceRepo)
                ->whereNotIn('source_key', $seenKeys ?: [''])
                ->where('status', '!=', 'completed')
                ->update(['status' => 'completed', 'completed_at' => now()]);

            return ['upserted' => count($upserted), 'auto_closed' => $closedCount];
        });

        return json_custom_response([
            'message' => 'Sincronizacion completada.',
            'data'    => $result,
        ]);
    }
}
