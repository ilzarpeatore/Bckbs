<?php

namespace App\Jobs;

use App\Models\ExerciseSessionMetric;
use App\Models\WorkoutSessionReview;
use App\Services\SessionProgressionRuleEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Motor de Auto-Regulación de Carga — Fase 2. Decisión de diseño propia
 * (el documento no especifica un trigger literal para
 * ProgressionRuleEngine::evaluateForExercise(), solo dice que su gate
 * paid-tier se comprueba "en ProgressionRuleEngine::evaluateForExercise()
 * /endpoint de sugerencias" — ver plan §Notas de ejecución transversales).
 *
 * Se despacha desde ClientCalendarController::finishSession(), justo
 * después de ProcessSessionInterpretation::dispatch($review) y bajo el
 * MISMO Gate::forUser($user)->allows('paid-tier') ya comprobado ahí (no se
 * repite el gate en el punto de entrada, aunque el motor también lo
 * vuelve a comprobar como red de seguridad, documento §0.3). Con
 * QUEUE_CONNECTION=sync (igual que ProcessSessionInterpretation) ambos
 * jobs corren en línea, en orden: cuando este job arranca,
 * exercise_session_metrics de esa sesión ya existe.
 *
 * Idempotente: SessionProgressionRuleEngine::evaluateForExercise() escribe
 * vía updateOrCreate sobre (workout_session_review_id, exercise_id,
 * client_id), así que reintentar este job no duplica filas en
 * next_session_targets/shadow_evaluations.
 */
class EvaluateSessionProgressionRules implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    private int $workoutSessionReviewId;

    public function __construct(WorkoutSessionReview $review)
    {
        $this->workoutSessionReviewId = $review->id;
    }

    public function handle(SessionProgressionRuleEngine $engine): void
    {
        $review = WorkoutSessionReview::find($this->workoutSessionReviewId);
        if (!$review) {
            return;
        }

        $exerciseIds = ExerciseSessionMetric::where('workout_session_review_id', $review->id)
            ->pluck('exercise_id')
            ->unique();

        foreach ($exerciseIds as $exerciseId) {
            $engine->evaluateForExercise((int) $review->user_id, (int) $exerciseId, (int) $review->id);
        }
    }
}
