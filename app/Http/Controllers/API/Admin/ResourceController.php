<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Models\User;
use App\Notifications\CommonNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\FuzzySearch;
use Illuminate\Support\Facades\Storage;

class ResourceController extends Controller
{
    public function getList(Request $request): JsonResponse
    {
        $query = Resource::with([
            'coach' => fn ($q) => $q->select('id', 'first_name', 'last_name'),
            'assignedClients' => fn ($q) => $q->select('users.id', 'first_name', 'last_name', 'email'),
        ]);

        // Vista por cliente (pestaña "Recursos" del perfil): compartidos +
        // los que ya tenga asignados este cliente concreto. Sin este
        // filtro (vista global) se devuelven todos.
        if ($request->filled('client_id')) {
            $clientId = $request->client_id;
            $query->where(function ($q) use ($clientId) {
                $q->where('scope', 'shared')
                  ->orWhereHas('assignedClients', fn ($q2) => $q2->where('users.id', $clientId));
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
            FuzzySearch::apply($query, ['title', 'content'], $request->search, ['title']);
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
            'coach' => fn ($q) => $q->select('id', 'first_name', 'last_name'),
            'assignedClients' => fn ($q) => $q->select('users.id', 'first_name', 'last_name', 'email'),
        ])->findOrFail($validated['id']);

        return $this->sendResponse($resource, 'Resource retrieved successfully');
    }

    /**
     * `client_ids` opcional: si scope=assigned, permite asignar de una vez
     * a varios clientes en el mismo paso de creacion (via la tabla puente
     * resource_assignments), en vez de crear el recurso y asignarlo aparte.
     * Portada (`image_url`/`image`): o bien una URL directa, o bien un
     * archivo subido -- ver storeImage().
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:article,video,link,doc',
            'scope' => 'required|in:shared,assigned',
            'category' => 'nullable|string|in:' . implode(',', Resource::CATEGORIES),
            'content' => 'nullable|string',
            'external_url' => 'nullable|string|max:2048',
            'image_url' => 'nullable|string|max:2048',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:5120',
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'integer|exists:users,id',
        ]);

        $resource = Resource::create([
            'coach_id'     => auth('sanctum')->id(),
            'title'        => $validated['title'],
            'type'         => $validated['type'],
            'scope'        => $validated['scope'],
            'category'     => $validated['category'] ?? null,
            'content'      => $validated['content'] ?? null,
            'external_url' => $validated['external_url'] ?? null,
            'image_url'    => $request->hasFile('image') ? $this->storeImage($request) : ($validated['image_url'] ?? null),
        ]);

        if ($validated['scope'] === 'assigned' && !empty($validated['client_ids'])) {
            $resource->assignedClients()->sync($validated['client_ids']);
        }

        return $this->sendResponse($resource->load('assignedClients'), 'Resource created successfully');
    }

    /**
     * CORREGIDO: antes usaba ->filter() para quitar la clave 'id', lo que
     * de paso descartaba cualquier valor falsy real (external_url/content
     * puestos a vacio, etc.) y esos cambios nunca se guardaban. Ahora se
     * usa ->only() sobre los campos validados, sin filtrar.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|exists:resources,id',
            'title' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|in:article,video,link,doc',
            'scope' => 'sometimes|in:shared,assigned',
            'category' => 'nullable|string|in:' . implode(',', Resource::CATEGORIES),
            'content' => 'nullable|string',
            'external_url' => 'nullable|string|max:2048',
            'image_url' => 'nullable|string|max:2048',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:5120',
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'integer|exists:users,id',
        ]);

        $resource = Resource::findOrFail($validated['id']);

        $data = collect($validated)->only(['title', 'type', 'scope', 'category', 'content', 'external_url'])->toArray();
        if ($request->hasFile('image')) {
            $data['image_url'] = $this->storeImage($request);
        } elseif (array_key_exists('image_url', $validated)) {
            $data['image_url'] = $validated['image_url'];
        }
        $resource->update($data);

        if (array_key_exists('client_ids', $validated)) {
            // Reemplaza el conjunto completo de asignacion (sync). Si el
            // recurso pasa a 'shared', se limpia cualquier asignacion
            // individual previa (ya no aplica, es visible para todos).
            $resource->assignedClients()->sync(
                $resource->scope === 'assigned' ? ($validated['client_ids'] ?? []) : []
            );
        }

        return $this->sendResponse($resource->load('assignedClients'), 'Resource updated successfully');
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

    /** Asignar un recurso concreto a un cliente concreto (toggle individual desde su perfil). */
    public function assign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'resource_id' => 'required|exists:resources,id',
            'client_id'   => 'required|exists:users,id',
        ]);

        $resource = Resource::findOrFail($validated['resource_id']);
        $resource->assignedClients()->syncWithoutDetaching([$validated['client_id']]);

        $client = User::find($validated['client_id']);
        if ($client) {
            $client->notify(new CommonNotification('new_resource', [
                'id'      => $resource->id,
                'type'    => 'new_resource',
                'subject' => 'Nuevo recurso compartido',
                'message' => "Tu coach te ha compartido \"{$resource->title}\".",
            ]));
        }

        return $this->sendResponse($resource->load('assignedClients'), 'Resource assigned successfully');
    }

    /** Quitar la asignacion de un recurso a un cliente concreto. */
    public function unassign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'resource_id' => 'required|exists:resources,id',
            'client_id'   => 'required|exists:users,id',
        ]);

        $resource = Resource::findOrFail($validated['resource_id']);
        $resource->assignedClients()->detach($validated['client_id']);

        return $this->sendResponse($resource->load('assignedClients'), 'Resource unassigned successfully');
    }
}
