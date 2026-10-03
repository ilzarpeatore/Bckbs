<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Fotos de progreso (colección 'progress_photos' del usuario, disco 'private').
 * Compartido entre el panel (Admin\ProgressPhotoController), la app
 * (MyProgressPhotoController) y las preguntas de check-in (FormController).
 *
 * La pose (front/side/back/other) y la fecha en que se hizo la foto viven en
 * custom_properties del media, así que no hace falta migración. Las fotos
 * antiguas sin esos datos salen como pose 'other' y fecha = created_at.
 */
class ProgressPhotoService
{
    public const POSES = ['front', 'side', 'back', 'other'];

    // SEGURIDAD (auditoria 2026-09-01, HIGH-1): URL firmada y temporal en
    // vez de $media->getUrl() -- el disco 'private' no tiene URL publica,
    // y aunque la tuviera, un ID secuencial es enumerable.
    public static function signedUrl(Media $media, int $hours = 6): string
    {
        return URL::temporarySignedRoute('progress-photo.signed', now()->addHours($hours), ['media' => $media->id]);
    }

    public static function present(Media $media): array
    {
        $takenAt = $media->getCustomProperty('taken_at');

        return [
            'id'         => $media->id,
            'url'        => self::signedUrl($media),
            'name'       => $media->name,
            'pose'       => $media->getCustomProperty('pose', 'other'),
            'taken_at'   => $takenAt ?: optional($media->created_at)->toDateString(),
            'created_at' => $media->created_at,
        ];
    }

    public static function normalizePose(?string $pose): string
    {
        return in_array($pose, self::POSES, true) ? $pose : 'other';
    }

    public static function normalizeTakenAt(?string $date): string
    {
        if (!$date) {
            return now()->toDateString();
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable $e) {
            return now()->toDateString();
        }
    }
}
