<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\ProgressPhotoService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProgressPhotoController extends Controller
{
    // URL firmada, pose y fecha: ver ProgressPhotoService (compartido con la
    // app, MyProgressPhotoController).
    public function getList(Request $request)
    {
        $request->validate(['client_id' => 'required|exists:users,id']);

        $user = User::findOrFail($request->client_id);

        $photos = $user->getMedia('progress_photos')
            ->sortByDesc('created_at')
            ->values()
            ->map(fn ($media) => ProgressPhotoService::present($media));

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
            'pose'      => 'nullable|in:'.implode(',', ProgressPhotoService::POSES),
            'taken_at'  => 'nullable|date',
        ]);

        $user = User::findOrFail($request->client_id);

        $media = $user->addMediaFromRequest('photo')
            ->usingName($request->get('name', 'Progress Photo'))
            ->withCustomProperties([
                'pose'     => ProgressPhotoService::normalizePose($request->input('pose')),
                'taken_at' => ProgressPhotoService::normalizeTakenAt($request->input('taken_at')),
                'source'   => 'admin',
            ])
            ->toMediaCollection('progress_photos');

        return json_custom_response([
            'data' => ProgressPhotoService::present($media),
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
