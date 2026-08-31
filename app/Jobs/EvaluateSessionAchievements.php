<?php

namespace App\Jobs;

use App\Enums\AchievementEventType;
use App\Http\Controllers\API\ClientCalendarController;
use App\Models\AchievementEvent;
use App\Models\WorkoutSessionReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Motor de Auto-Regulación de Carga — Fase 3 (documento §3.2, tarea #20).
 * Racha de sesiones (hitos 5/10/20/50) e hito_compliance. Se despacha
 * desde ClientCalendarController::finishSession() bajo el mismo gate
 * paid-tier ya comprobado ahí (no se repite), justo después de los jobs
 * de Fase 1/2 (mismo criterio QUEUE_CONNECTION=sync -> en línea, en orden).
 *
 * Racha de sesiones: REUTILIZA ClientCalendarController::computeAdherence()
 * en vez de reimplementar el cruce calendario-vs-sesiones-reales — es
 * exactamente la misma necesidad que el propio documento describe ("no
 * existe estado explícito de sesión saltada, hay que cruzar
 * program_day_assignments esperadas contra workout_session_reviews
 * reales"), que esa función ya resuelve en producción para la pantalla de
 * adherencia. Mismo patrón algorítmico que Habit::getCurrentStreakAttribute()
 * (racha = contador de elementos consecutivos completos hasta hoy, sin
 * persistir el contador en ninguna columna), no su código literal.
 *
 * hito_compliance: umbral propio (documento no define uno exacto) — ver
 * constantes COMPLIANCE_* y resumen final de la tarea.
 *
 * Idempotente: antes de escribir un hito de racha comprueba si ya existe
 * una fila achievement_events para ese client_id+type+value exacto, y
 * antes de escribir hito_compliance comprueba que no haya uno ya dentro de
 * la misma ventana de 4 semanas — reintentar este job no duplica logros.
 */
class EvaluateSessionAchievements implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    // Hitos de racha de sesiones (documento §3.2).
    private const STREAK_MILESTONES = [5, 10, 20, 50];

    // Decisión de diseño propia (el documento no define un umbral exacto
    // para hito_compliance): 80%+ de sesiones programadas completadas en
    // las últimas 4 semanas (28 días), exigiendo al menos 8 sesiones
    // programadas en esa ventana para evitar falsos positivos con muy
    // pocos datos (ej. un cliente con 1 sesión programada y 1 completada
    // no debería disparar un "hito" de compliance). Documentado también en
    // config/achievements.php.
    private const COMPLIANCE_WINDOW_DAYS = 28;
    private const COMPLIANCE_THRESHOLD = 0.80;
    private const COMPLIANCE_MIN_SCHEDULED = 8;

    private int $workoutSessionReviewId;

    public function __construct(WorkoutSessionReview $review)
    {
        $this->workoutSessionReviewId = $review->id;
    }

    public function handle(): void
    {
        $review = WorkoutSessionReview::find($this->workoutSessionReviewId);
        if (!$review) {
            return;
        }

        $clientId = (int) $review->user_id;

        $this->evaluateStreak($clientId);
        $this->evaluateCompliance($clientId);
    }

    private function evaluateStreak(int $clientId): void
    {
        // Ventana amplia (400 días) para no cortar artificialmente una
        // racha larga — computeAdherence() no tiene el tope de 90 días que
        // sí aplica getMyAdherence() a nivel de request HTTP.
        $adherence = ClientCalendarController::computeAdherence($clientId, 400);
        $streak = (int) ($adherence['currentStreak'] ?? 0);

        foreach (self::STREAK_MILESTONES as $milestone) {
            if ($streak < $milestone) {
                continue;
            }

            $alreadyRecorded = AchievementEvent::where('client_id', $clientId)
                ->where('type', AchievementEventType::RACHA_SESIONES->value)
                ->where('value', $milestone)
                ->exists();

            if ($alreadyRecorded) {
                continue;
            }

            AchievementEvent::create([
                'client_id'                 => $clientId,
                'type'                       => AchievementEventType::RACHA_SESIONES->value,
                'value'                      => $milestone,
                'previous_best'              => null,
                'significancia_verificada'   => true,
            ]);
        }
    }

    private function evaluateCompliance(int $clientId): void
    {
        $adherence = ClientCalendarController::computeAdherence($clientId, self::COMPLIANCE_WINDOW_DAYS);

        if (($adherence['mode'] ?? null) !== 'program') {
            return; // hito_compliance solo tiene sentido con un ratio real (cliente con programa asignado).
        }
        if (($adherence['scheduledCount'] ?? 0) < self::COMPLIANCE_MIN_SCHEDULED) {
            return;
        }
        if (($adherence['ratio'] ?? 0) < self::COMPLIANCE_THRESHOLD) {
            return;
        }

        $recentlyRecorded = AchievementEvent::where('client_id', $clientId)
            ->where('type', AchievementEventType::HITO_COMPLIANCE->value)
            ->where('created_at', '>=', now()->subDays(self::COMPLIANCE_WINDOW_DAYS))
            ->exists();

        if ($recentlyRecorded) {
            return; // un hito_compliance por ventana de 4 semanas, no uno por sesión mientras se mantenga sobre el umbral.
        }

        AchievementEvent::create([
            'client_id'                 => $clientId,
            'type'                       => AchievementEventType::HITO_COMPLIANCE->value,
            'value'                      => round($adherence['ratio'] * 100, 2),
            'previous_best'              => null,
            'significancia_verificada'   => true,
        ]);
    }
}
