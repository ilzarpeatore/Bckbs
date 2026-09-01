<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProgressPhotoController extends Controller
{
    // SEGURIDAD (auditoria 2026-09-01, HIGH-1): URL firmada y temporal en
    // vez de $media->getUrl() -- el disco 'private' no tiene URL publica,
    // y aunque la tuviera, un ID secuencial es enumerable. Ver
    // SECURITY_AUDIT_BACKEND.md.
    private function signedPhotoUrl(Media $media): string
    {
        return URL::temporarySignedRoute('progress-photo.signed', now()->addHours(6), ['media' => $media->id]);
    }

    public function getList(Request $request)
    {
        $request->validate(['client_id' => 'required|exists:users,id']);

        $user = User::findOrFail($request->client_id);

        $photos = $user->getMedia('progress_photos')
            ->sortByDesc('created_at')
            ->map(fn ($media) => [
                'id'         => $media->id,
                'url'        => $this->signedPhotoUrl($media),
                'name'       => $media->name,
                'created_at' => $media->created_at,
            ]);

        return json_custom_response(['data' => $photos]);
    }

    public function showSigned(Request $request, $media)
    {
        $mediaModel = Media::where('collection_name', 'progress_photos')->findOrFail($media);

        return response()->file($mediaModel->getPath());
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:users,id',
            'photo'     => 'required|image|max:10240',
        ]);

        $user = User::findOrFail($request->client_id);

        $media = $user->addMediaFromRequest('photo')
            ->usingName($request->get('name', 'Progress Photo'))
            ->toMediaCollection('progress_photos');

        return json_custom_response([
            'data' => [
                'id'         => $media->id,
                'url'        => $this->signedPhotoUrl($media),
                'name'       => $media->name,
                'created_at' => $media->created_at,
            ],
            'message' => 'Photo uploaded.',
        ], 201);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:users,id',
            'photo_id'  => 'required|integer',
        ]);

        $user = User::findOrFail($request->client_id);

        $media = $user->getMedia('progress_photos')->where('id', $request->photo_id)->first();

        if (!$media) {
            return json_message_response('Photo not found.', 404);
        }

        $media->delete();

        return json_message_response('Photo deleted.');
    }
}
