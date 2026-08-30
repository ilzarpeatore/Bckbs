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
        $query = Resource::with(['coach' => fn($q) => $q->select('id', 'first_name', 'last_name')]);

        if ($request->filled('client_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('scope', 'shared')
                  ->orWhere('client_id', $request->client_id);
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

        $resource = Resource::with(['coach' => fn($q) => $q->select('id', 'first_name', 'last_name')])
            ->findOrFail($validated['id']);

        return $this->sendResponse($resource, 'Resource retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:article,video,link,doc',
            'scope' => 'required|in:shared,personal',
            'client_id' => 'required_if:scope,personal|nullable|exists:users,id',
            'content' => 'nullable|string',
            'external_url' => 'nullable|string|max:2048',
            'category' => 'nullable|string|in:entrenamiento,nutricion,habitos_mindset',
            // Portada: o bien una URL directa, o bien un archivo -- ver abajo.
            'image_url' => 'nullable|string|max:2048',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:5120',
        ]);

        $validated['coach_id'] = auth('sanctum')->id();
        if ($validated['scope'] === 'shared') {
            $validated['client_id'] = null;
        }

        if ($request->hasFile('image')) {
            $validated['image_url'] = $this->storeImage($request);
        }
        unset($validated['image']);

        $resource = Resource::create($validated);

        return $this->sendResponse($resource, 'Resource created successfully');
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|exists:resources,id',
            'title' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|in:article,video,link,doc',
            'scope' => 'sometimes|in:shared,personal',
            'client_id' => 'nullable|exists:users,id',
            'content' => 'nullable|string',
            'external_url' => 'nullable|string|max:2048',
            'category' => 'nullable|string|in:entrenamiento,nutricion,habitos_mindset',
            'image_url' => 'nullable|string|max:2048',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:5120',
        ]);

        $resource = Resource::findOrFail($validated['id']);
        $data = collect($validated)->except(['id', 'image'])->filter()->toArray();

        if (isset($data['scope']) && $data['scope'] === 'shared') {
            $data['client_id'] = null;
        }

        if ($request->hasFile('image')) {
            $data['image_url'] = $this->storeImage($request);
        }

        $resource->update($data);

        return $this->sendResponse($resource, 'Resource updated successfully');
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
