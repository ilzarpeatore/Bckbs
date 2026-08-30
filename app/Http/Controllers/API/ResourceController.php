<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Resource;

class ResourceController extends Controller
{
    /**
     * Lo que un cliente ve: sus recursos compartidos + los suyos personales.
     * Usa el scope visibleTo() definido en el Model.
     */
    public function getList(Request $request)
    {
        $user = auth('sanctum')->user();

        $resource = Resource::visibleTo($user->id);

        $resource->when($request->type, function ($q) use ($request) {
            return $q->where('type', $request->type);
        });

        // AÑADIDO: permite agrupar/filtrar las guías compartidas por
        // categoría (entrenamiento|nutricion|habitos_mindset) desde la app.
        $resource->when($request->category, function ($q) use ($request) {
            return $q->where('category', $request->category);
        });

        $per_page = config('constant.PER_PAGE_LIMIT');
        if ($request->has('per_page') && !empty($request->per_page)) {
            if (is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            }
            if ($request->per_page == -1) {
                $per_page = $resource->count();
            }
        }

        $resource = $resource->orderByDesc('created_at')->paginate($per_page);

        $response = [
            'pagination' => json_pagination_response($resource),
            'data'       => $resource->items(),
        ];

        return json_custom_response($response);
    }

    public function getDetail(Request $request)
    {
        $resource = Resource::find($request->id);

        if ($resource == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Resource']));
        }

        return json_custom_response(['data' => $resource]);
    }

    /**
     * Legacy: la creación/edición real de recursos vive en
     * API\Admin\ResourceController (admin-resource-store/update), que ya
     * soporta scope=assigned con varios clientes vía resource_assignments
     * (ver Resource::assignedClients()). Este endpoint se deja funcional
     * solo para scope=shared -- `client_id` ya no existe en la tabla.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'type'  => 'required|string',
            'scope' => 'required|in:shared,assigned',
        ]);

        $resource = Resource::create([
            'coach_id'      => auth('sanctum')->id(),
            'title'         => $request->title,
            'type'          => $request->type,
            'content'       => $request->content,
            'external_url'  => $request->external_url,
            'scope'         => $request->scope,
        ]);

        return json_message_response(__('message.save_form', ['form' => 'Resource']));
    }

    public function update(Request $request)
    {
        $resource = Resource::find($request->id);

        if ($resource == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Resource']));
        }

        $resource->update($request->only(['title', 'type', 'content', 'external_url', 'scope']));

        return json_message_response(__('message.save_form', ['form' => 'Resource']));
    }

    public function destroy(Request $request)
    {
        $resource = Resource::find($request->id);

        if ($resource == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Resource']));
        }

        $resource->delete();

        return json_message_response(__('message.delete_form', ['form' => 'Resource']));
    }
}
