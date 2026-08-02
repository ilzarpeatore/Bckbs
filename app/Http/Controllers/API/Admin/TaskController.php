<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function getList(Request $request)
    {
        $query = Task::with(['author:id,first_name,last_name', 'client:id,first_name,last_name,email']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
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
        ]);

        $task = Task::create([
            'author_id'   => auth('sanctum')->id(),
            'client_id'   => $request->client_id,
            'title'       => $request->title,
            'description' => $request->description,
            'due_date'    => $request->due_date,
            'priority'    => $request->get('priority', 'medium'),
            'status'      => $request->get('status', 'pending'),
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
        ]);

        $task = Task::findOrFail($request->id);

        $data = $request->only(['title', 'description', 'client_id', 'due_date', 'priority', 'status']);
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
}
