<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientGoalController extends Controller
{
    public function getList(Request $request): JsonResponse
    {
        $query = ClientGoal::with([]);

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $goals = $query->orderByDesc('created_at')->get();

        return $this->sendResponse($goals, 'Client goals retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string|in:weight_loss,strength,endurance,flexibility,body_comp,custom',
            'target_value' => 'nullable|numeric',
            'current_value' => 'nullable|numeric',
            'unit' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:active,achieved,abandoned',
            'target_date' => 'nullable|date',
        ]);

        $goal = ClientGoal::create($validated);

        return $this->sendResponse($goal, 'Client goal created successfully');
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|exists:client_goals,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string|in:weight_loss,strength,endurance,flexibility,body_comp,custom',
            'target_value' => 'nullable|numeric',
            'current_value' => 'nullable|numeric',
            'unit' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:active,achieved,abandoned',
            'target_date' => 'nullable|date',
        ]);

        $goal = ClientGoal::findOrFail($validated['id']);
        $goal->update(collect($validated)->except('id')->filter()->toArray());

        return $this->sendResponse($goal, 'Client goal updated successfully');
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|exists:client_goals,id',
        ]);

        ClientGoal::findOrFail($validated['id'])->delete();

        return $this->sendResponse(null, 'Client goal deleted successfully');
    }
}
