<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Resource;

class ResourceController extends Controller
{
    /**
     * Lo que un cliente ve: sus recursos compartidos + los suyos asignados.
     * Usa el scope visibleTo() definido en el Model.
     */
    public function getList(Request $request)
    {
        $user = auth('sanctum')->user();

        $resource = Resource::visibleTo($user->id);

        $resource->when($request->type, function ($q) use ($request) {
            return $q->where('type', $request->type);
        });

        $resource->when($request->scope, function ($q) use ($request) {
            return $q->where('scope', $request->scope);
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
        // SEGURIDAD (barrido sistematico 2026-09-01, CRIT): sin scope, cualquier
        // usuario autenticado podia leer CUALQUIER resource por id, incluidos los
        // no compartidos/no asignados a el. Mismo criterio que getList(): visible
        // si es del propio coach, o si visibleTo() lo permite (shared/asignado).
        $user = auth('sanctum')->user();

        $resource = Resource::where('id', $request->id)
            ->where(function ($q) use ($user) {
                $q->visibleTo($user->id)->orWhere('coach_id', $user->id);
            })
            ->first();

        if ($resource == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Resource']));
        }

        return json_custom_response(['data' => $resource]);
    }

    /**
     * Uso secundario - la via real de creacion es el panel Admin
     * (Admin\ResourceController, que ademas gestiona la asignacion a
     * clientes via resource_assignments, ver Resource::assignedClients()).
     * Se deja disponible por si un coach crea contenido desde la propia
     * app cliente en el futuro.
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
            'category'      => $request->category,
        ]);

        return json_message_response(__('message.save_form', ['form' => 'Resource']));
    }

    public function update(Request $request)
    {
        // SEGURIDAD (barrido sistematico 2026-09-01, CRIT): sin scope, cualquier
        // usuario autenticado podia editar el resource de OTRO coach. store()
        // ya establece coach_id = auth()->id() al crear -- se aplica el mismo
        // criterio de propiedad aqui.
        $resource = Resource::where('id', $request->id)
            ->where('coach_id', auth('sanctum')->id())
            ->first();

        if ($resource == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Resource']));
        }

        $resource->update($request->only(['title', 'type', 'content', 'external_url', 'scope', 'category']));

        return json_message_response(__('message.save_form', ['form' => 'Resource']));
    }

    public function destroy(Request $request)
    {
        // SEGURIDAD (barrido sistematico 2026-09-01, CRIT): mismo problema que
        // update() -- sin scope, cualquier usuario autenticado podia borrar el
        // resource de OTRO coach.
        $resource = Resource::where('id', $request->id)
            ->where('coach_id', auth('sanctum')->id())
            ->first();

        if ($resource == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Resource']));
        }

        $resource->delete();

        return json_message_response(__('message.delete_form', ['form' => 'Resource']));
    }
}
