<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ProgressPhotoService;
use Illuminate\Http\Request;

/**
 * Fotos de progreso del propio cliente desde la app (2026-10-03). Hasta ahora
 * solo el panel podía subirlas/verlas (Admin\ProgressPhotoController); aquí el
 * cliente solo ve, sube y borra SUS fotos -- el usuario sale siempre del token,
 * nunca de un parámetro.
 */
class MyProgressPhotoController extends Controller
{
    public function index(Request $request)
    {
        $photos = $request->user()->getMedia('progress_photos')
            ->sortByDesc(fn ($m) => ($m->getCustomProperty('taken_at') ?: $m->created_at->toDateString()).sprintf('%012d', $m->id))
            ->values()
            ->map(fn ($m) => ProgressPhotoService::present($m));

        return json_custom_response(['data' => $photos]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'photo'    => 'required|image|max:10240',
            'pose'     => 'nullable|in:'.implode(',', ProgressPhotoService::POSES),
            'taken_at' => 'nullable|date|before_or_equal:tomorrow',
        ]);

        $media = $request->user()->addMediaFromRequest('photo')
            ->usingName('Progress Photo')
            ->withCustomProperties([
                'pose'     => ProgressPhotoService::normalizePose($request->input('pose')),
                'taken_at' => ProgressPhotoService::normalizeTakenAt($request->input('taken_at')),
                'source'   => 'app',
            ])
            ->toMediaCollection('progress_photos');

        return json_custom_response([
            'data'    => ProgressPhotoService::present($media),
            'message' => 'Foto guardada.',
        ], 201);
    }

    public function destroy(Request $request)
    {
        $request->validate(['photo_id' => 'required|integer']);

        $media = $request->user()->getMedia('progress_photos')->firstWhere('id', (int) $request->photo_id);

        if (!$media) {
            return json_message_response('Foto no encontrada.', 404);
        }

        $media->delete();

        return json_message_response('Foto eliminada.');
    }
}
