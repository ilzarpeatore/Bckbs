<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientNote;
use App\Models\User;
use Illuminate\Http\Request;

class ClientNoteController extends Controller
{
    public function getList(Request $request)
    {
        $request->validate(['client_id' => 'required|exists:users,id']);

        $notes = ClientNote::where('client_id', $request->client_id)
            ->with('author:id,first_name,last_name')
            ->orderBy('created_at', 'desc')
            ->get();

        return json_custom_response(['data' => $notes]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:users,id',
            'content'   => 'required|string',
        ]);

        $note = ClientNote::create([
            'client_id' => $request->client_id,
            'author_id' => auth('sanctum')->id(),
            'content'   => $request->content,
        ]);

        $note->load('author:id,first_name,last_name');

        return json_custom_response(['data' => $note, 'message' => 'Note created.'], 201);
    }

    public function update(Request $request)
    {
        $request->validate([
            'id'      => 'required|exists:client_notes,id',
            'content' => 'required|string',
        ]);

        $note = ClientNote::findOrFail($request->id);
        $note->update(['content' => $request->content]);

        $note->load('author:id,first_name,last_name');

        return json_custom_response(['data' => $note, 'message' => 'Note updated.']);
    }

    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|exists:client_notes,id']);

        ClientNote::findOrFail($request->id)->delete();

        return json_message_response('Note deleted.');
    }
}
