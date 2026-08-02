<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientLimitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientLimitationController extends Controller
{
    public function getList(Request $request): JsonResponse
    {
        $query = ClientLimitation::query();

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $limitations = $query->orderByDesc('created_at')->get();

        return $this->sendResponse($limitations, 'Client limitations retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:users,id',
            'type' => 'nullable|string|in:injury,limitation,medical_condition,allergy',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:active,resolved',
            'date_reported' => 'nullable|date',
            'date_resolved' => 'nullable|date',
        ]);

        $limitation = ClientLimitation::create($validated);

        return $this->sendResponse($limitation, 'Client limitation created successfully');
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|exists:client_limitations,id',
            'type' => 'nullable|string|in:injury,limitation,medical_condition,allergy',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:active,resolved',
            'date_reported' => 'nullable|date',
            'date_resolved' => 'nullable|date',
        ]);

        $limitation = ClientLimitation::findOrFail($validated['id']);
        $limitation->update(collect($validated)->except('id')->filter()->toArray());

        return $this->sendResponse($limitation, 'Client limitation updated successfully');
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|exists:client_limitations,id',
        ]);

        ClientLimitation::findOrFail($validated['id'])->delete();

        return $this->sendResponse(null, 'Client limitation deleted successfully');
    }
}
