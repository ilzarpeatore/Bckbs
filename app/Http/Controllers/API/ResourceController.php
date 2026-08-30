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
     * Crear un recurso desde el panel Admin. `scope=personal` exige `client_id`;
     * `scope=shared` lo ignora (es visible para todo el roster del coach).
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'type'  => 'required|string',
            'scope' => 'required|in:shared,personal',
            'client_id' => 'required_if:scope,personal|exists:users,id',
        ]);

        $resource = Resource::create([
            'coach_id'      => auth('sanctum')->id(),
            'client_id'     => $request->scope == 'personal' ? $request->client_id : null,
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

        $resource->update($request->only(['title', 'type', 'content', 'external_url', 'scope', 'client_id']));

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
