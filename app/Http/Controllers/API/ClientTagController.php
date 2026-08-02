<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClientTag;
use App\Models\User;

class ClientTagController extends Controller
{
    public function getList(Request $request)
    {
        $tags = ClientTag::where('coach_id', auth()->id())->withCount('clients')->orderBy('title')->get();
        return json_custom_response(['data' => $tags]);
    }

    public function store(Request $request)
    {
        $request->validate(['title' => 'required|string|max:100']);

        if ($request->id) {
            $tag = ClientTag::where('id', $request->id)
                ->where('coach_id', auth()->id())
                ->firstOrFail();

            $tag->update([
                'title' => $request->title,
                'color' => $request->color ?? $tag->color,
            ]);

            return json_custom_response(['data' => $tag]);
        }

        $tag = ClientTag::create([
            'coach_id' => auth()->id(),
            'title'    => $request->title,
            'color'    => $request->color ?? '#2e5cff',
        ]);

        return json_custom_response(['data' => $tag]);
    }

    public function destroy(Request $request)
    {
        ClientTag::where('id', $request->id)->delete();
        return json_message_response('Tag eliminado.');
    }

    /** Tags asignados a un cliente concreto (para pintarlos en su perfil). */
    public function getClientTags(Request $request)
    {
        $request->validate(['client_id' => 'required|exists:users,id']);

        $client = User::with('tags')->find($request->client_id);
        return json_custom_response(['data' => $client->tags]);
    }

    public function assignToClient(Request $request)
    {
        $request->validate([
            'client_id'     => 'required|exists:users,id',
            'client_tag_id' => 'required|exists:client_tags,id',
        ]);

        $client = User::find($request->client_id);
        $client->tags()->syncWithoutDetaching([$request->client_tag_id]);

        return json_message_response('Tag asignado.');
    }

    public function removeFromClient(Request $request)
    {
        $request->validate([
            'client_id'     => 'required|exists:users,id',
            'client_tag_id' => 'required|exists:client_tags,id',
        ]);

        $client = User::find($request->client_id);
        $client->tags()->detach($request->client_tag_id);

        return json_message_response('Tag quitado.');
    }

    /**
     * Para el buscador de clientes: filtrar por uno o varios tags.
     * GET ?tag_ids[]=1&tag_ids[]=3
     */
    public function filterClientsByTags(Request $request)
    {
        $request->validate(['tag_ids' => 'required|array']);

        $clients = User::where('user_type', 'user')
            ->whereHas('tags', function ($q) use ($request) {
                $q->whereIn('client_tags.id', $request->tag_ids);
            })
            ->select('id', 'first_name', 'last_name', 'email', 'display_name', 'username', 'phone_number', 'profile_image', 'user_type')
            ->limit(500)
            ->get();

        return json_custom_response(['data' => $clients]);
    }
}
