<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ResourceController extends Controller
{
    public function getList(Request $request): JsonResponse
    {
        $query = Resource::with([
            'coach' => fn($q) => $q->select('id', 'first_name', 'last_name'),
            'assignedClients:id,first_name,last_name,email',
        ]);

        if ($request->filled('client_id')) {
            $clientId = $request->client_id;
            $query->where(function ($q) use ($clientId) {
                $q->where('scope', 'shared')
                  ->orWhereHas('assignedClients', fn($q2) => $q2->where('users.id', $clientId));
            });
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('scope')) {
            $query->where('scope', $request->scope);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('content', 'LIKE', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 50), 250);
        $resources = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->sendResponse($resources, 'Resources retrieved successfully');
    }

    public function getDetail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|exists:resources,id',
        ]);

        $resource = Resource::with([
            'coach' => fn($q) => $q->select('id', 'first_name', 'last_name'),
            'assignedClients:id,first_name,last_name,email',
        ])->findOrFail($validated['id']);

        return $this->sendResponse($resource, 'Resource retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:article,video,link,doc',
            'scope' => 'required|in:shared,assigned',
            'client_ids' => 'required_if:scope,assigned|array',
            'client_ids.*' => 'exists:users,id',
            'content' => 'nullable|string',
            'external_url' => 'nullable|string|max:2048',
            'category' => 'nullable|string|in:entrenamiento,nutricion,habitos_mindset,onboarding,planes_actuales',
            // Portada: o bien una URL directa, o bien un archivo -- ver abajo.
            'image_url' => 'nullable|string|max:2048',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:5120',
        ]);

        $clientIds = $validated['scope'] === 'assigned' ? ($validated['client_ids'] ?? []) : [];
        unset($validated['client_ids']);
        $validated['coach_id'] = auth('sanctum')->id();

        if ($request->hasFile('image')) {
            $validated['image_url'] = $this->storeImage($request);
        }
        unset($validated['image']);

        $resource = Resource::create($validated);
        if ($clientIds) {
            $resource->assignedClients()->sync($clientIds);
        }

        return $this->sendResponse($resource->load('assignedClients:id,first_name,last_name,email'), 'Resource created successfully');
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|exists:resources,id',
            'title' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|in:article,video,link,doc',
            'scope' => 'sometimes|in:shared,assigned',
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'exists:users,id',
            'content' => 'nullable|string',
            'external_url' => 'nullable|string|max:2048',
            'category' => 'nullable|string|in:entrenamiento,nutricion,habitos_mindset,onboarding,planes_actuales',
            'image_url' => 'nullable|string|max:2048',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:5120',
        ]);

        $resource = Resource::findOrFail($validated['id']);
        $clientIdsProvided = array_key_exists('client_ids', $validated);
        $clientIds = $validated['client_ids'] ?? [];
        $data = collect($validated)->except(['id', 'image', 'client_ids'])->filter()->toArray();

        if ($request->hasFile('image')) {
            $data['image_url'] = $this->storeImage($request);
        }

        $resource->update($data);

        if ($resource->scope === 'shared') {
            $resource->assignedClients()->sync([]);
        } elseif ($clientIdsProvided) {
            $resource->assignedClients()->sync($clientIds);
        }

        return $this->sendResponse($resource->load('assignedClients:id,first_name,last_name,email'), 'Resource updated successfully');
    }

    /**
     * Sube el archivo `image` al disco `public` (storage/app/public/resources,
     * enlazado en public/storage vía `php artisan storage:link`) y devuelve
     * su URL pública.
     */
    protected function storeImage(Request $request): string
    {
        $file = $request->file('image');
        $filename = uniqid('resource_') . '.' . $file->getClientOriginalExtension();

        Storage::disk('public')->put('resources/' . $filename, file_get_contents($file->getRealPath()));

        return Storage::disk('public')->url('resources/' . $filename);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|exists:resources,id',
        ]);

        Resource::findOrFail($validated['id'])->delete();

        return $this->sendResponse(null, 'Resource deleted successfully');
    }
}
