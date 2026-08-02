<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Exercise;
use App\Models\BodyPart;
use App\Models\ClientExerciseFeedback;
use App\Models\ClientExerciseLog;
use App\Models\PersonalRecord;
use App\Models\WorkoutTemplateExercise;
use App\Traits\HasYoutubeThumbnail;

class ExerciseInfoController extends Controller
{
    use HasYoutubeThumbnail;

    /**
     * Contrato para la pantalla de Detalle de Ejercicio (4 pestañas).
     * Reutiliza los mismos datos que ya sirve `exercise-detail` (sin
     * tocar ese endpoint ni su Resource, que otras pantallas siguen
     * usando), pero con la forma exacta que pide el frontend nuevo.
     */
    public function getDetail(Request $request)
    {
        $request->validate(['id' => 'required|exists:exercises,id']);

        $exercise = Exercise::with(['level', 'equipment'])->find($request->id);

        $video_url = $exercise->video_url;
        if ($exercise->video_type == 'upload_video') {
            $video_url = getMediaFileExit($exercise, 'exercise_video') ? getSingleMedia($exercise, 'exercise_video', null) : null;
        }

        $media_url = $video_url ?: getSingleMedia($exercise, 'exercise_image', null);
        $media_type = $video_url ? 'video' : ($media_url ? 'image' : null);

        // No hay ningún reproductor de vídeo integrado en la app todavía
        // (ni expo-av ni similar instalado) — la cabecera de esta pantalla
        // siempre pinta una imagen, igual que el resto del proyecto usa
        // la miniatura de YouTube en vez de reproducir el vídeo inline.
        $thumbnail_url = $video_url
            ? ($this->youtubeThumbnail($video_url) ?? getSingleMedia($exercise, 'exercise_image', null))
            : $media_url;

        $bodyparts = collect();
        if (!empty($exercise->bodypart_ids)) {
            $ordered = collect($exercise->bodypart_ids);
            $found = BodyPart::whereIn('id', $ordered)->get()->keyBy('id');
            $bodyparts = $ordered
                ->map(fn ($id) => $found->get($id))
                ->filter()
                ->map(fn ($bp) => [
                    'name'     => $bp->title,
                    'icon_url' => getSingleMedia($bp, 'bodypart_image', null),
                ])
                ->values();
        }

        $instructionLines = $exercise->instruction
            ? collect(preg_split('/\r\n|\r|\n/', $exercise->instruction))->map(fn ($l) => trim($l))->filter()->values()
            : collect();
        $tipLines = $exercise->tips
            ? collect(preg_split('/\r\n|\r|\n/', $exercise->tips))->map(fn ($l) => trim($l))->filter()->values()
            : collect();

        $equipment = $exercise->equipment ? [
            'name'      => $exercise->equipment->title,
            'image_url' => getSingleMedia($exercise->equipment, 'equipment_image', null),
        ] : null;

        $client_id = auth('sanctum')->id();
        $feedback = ClientExerciseFeedback::where('client_id', $client_id)
            ->where('exercise_id', $exercise->id)
            ->value('feedback');

        return json_custom_response([
            'data' => [
                'id'         => $exercise->id,
                'title'      => $exercise->title,
                'media_url'  => $media_url,
                'media_type' => $media_type,
                'thumbnail_url' => $thumbnail_url,
                // No existe ninguna señal real de popularidad todavía
                // (sin contador de uso/favoritos agregado) — false siempre,
                // no se inventa un número.
                'is_popular' => false,
                'muscle' => [
                    'primary'   => $bodyparts->first(),
                    'secondary' => $bodyparts->slice(1)->values(),
                ],
                'instructions' => [
                    'steps' => $instructionLines,
                    'tips'  => $tipLines,
                ],
                'equipment'      => $equipment,
                'user_feedback'  => $feedback,
            ],
        ]);
    }

    /**
     * Pestaña ANÁLISIS: historial de sesiones agregado desde
     * client_exercise_logs (sistema v2). No crea ninguna tabla nueva de
     * logs, es solo lectura agregada, tal como pide la spec.
     */
    public function getAnalysis(Request $request)
    {
        $request->validate(['exercise_id' => 'required|exists:exercises,id']);

        $client_id = auth('sanctum')->id();

        $logs = ClientExerciseLog::with('workoutTemplateExercise.block.workoutTemplate')
            ->where('client_id', $client_id)
            ->where('exercise_id', $request->exercise_id)
            ->whereNotNull('performed_date')
            ->orderByDesc('performed_date')
            ->orderByDesc('created_at')
            ->get()
            ->unique('performed_date'); // el log más reciente de cada día = estado final de esa sesión

        $sessions = $logs->map(function ($log) use ($client_id, $request) {
            $wte = $log->workoutTemplateExercise;
            $workoutTitle = optional(optional($wte)->block)->workoutTemplate->title ?? null;

            $prs = PersonalRecord::where('user_id', $client_id)
                ->where('exercise_id', $request->exercise_id)
                ->whereDate('achieved_at', $log->performed_date)
                ->pluck('record_type')
                ->unique()
                ->values();

            return [
                'date'             => $log->performed_date->toDateString(),
                'workout_title'    => $workoutTitle,
                'sets'             => $log->logged_sets ?? [],
                'prs_this_session' => $prs,
            ];
        })->values();

        return json_custom_response([
            'data' => [
                'exercise_id'    => (int) $request->exercise_id,
                'total_sessions' => $sessions->count(),
                'sessions'       => $sessions,
            ],
        ]);
    }
}
