<?php

namespace App\Jobs;

use App\Models\WorkoutSessionReview;
use App\Services\SessionInterpretationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Motor de Auto-Regulación de Carga — Fase 1. Se despacha desde
 * ClientCalendarController::finishSession() SOLO si Gate::allows('paid-tier')
 * para el cliente (el gate se comprueba una única vez, en ese punto de
 * entrada — este job asume que ya pasó).
 *
 * QUEUE_CONNECTION=sync en este proyecto -> corre inline, no hace falta
 * infraestructura de cola adicional, pero se implementa como job real para
 * que el día que se cambie a una cola de verdad (ej. database/redis) no
 * haga falta tocar nada más.
 *
 * Idempotente: SessionInterpretationService::processReview() usa
 * updateOrCreate sobre (workout_session_review_id, exercise_id), así que
 * reintentar este job no duplica filas en exercise_session_metrics.
 */
class ProcessSessionInterpretation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    private int $workoutSessionReviewId;

    public function __construct(WorkoutSessionReview $review)
    {
        $this->workoutSessionReviewId = $review->id;
    }

    public function handle(SessionInterpretationService $service): void
    {
        $review = WorkoutSessionReview::find($this->workoutSessionReviewId);
        if (!$review) {
            return;
        }

        $service->processReview($review);
    }
}
