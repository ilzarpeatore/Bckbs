<?php

namespace App\Console\Commands;

use App\Enums\ActionTaken;
use App\Enums\FallbackBehavior;
use App\Enums\TargetStatus;
use App\Models\NextSessionTarget;
use App\Models\OverrideLog;
use App\Models\User;
use App\Notifications\CommonNotification;
use App\Services\SessionProgressionRuleEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Motor de Auto-Regulación de Carga — Fase 2 (documento §2.3): "Si no hay
 * respuesta del coach antes de que la sesión correspondiente se vuelva
 * 'próxima' (ventana ej. 24h antes) -> aplicar fallback_behavior de la
 * regla."
 *
 * Corre cada hora (ver Kernel::schedule) — suficiente granularidad para
 * una ventana de 24h sin sobrecargar. Solo actúa sobre next_session_targets
 * status=pendiente cuya PRÓXIMA sesión programada con ese ejercicio cae
 * dentro de las próximas 24h.
 */
class ApplyProgressionFallbacks extends Command
{
    protected $signature = 'progression:apply-fallbacks';

    protected $description = 'Aplica el fallback_behavior de la regla a sugerencias pendientes cuya sesión ya está a menos de 24h y el coach no respondió';

    private const WINDOW_HOURS = 24;

    public function handle(SessionProgressionRuleEngine $engine): int
    {
        $targets = NextSessionTarget::where('status', TargetStatus::PENDIENTE->value)
            ->with('rule')
            ->get();

        $applied = 0;
        $rejected = 0;
        $escalated = 0;

        foreach ($targets as $target) {
            $rule = $target->rule;
            if (!$rule) {
                continue; // No debería ocurrir (pendiente siempre viene de una regla), salvaguarda.
            }

            $assignment = $engine->resolveNextAssignmentForExercise((int) $target->client_id, (int) $target->exercise_id);
            if (!$assignment || !$assignment->scheduled_date) {
                continue; // Sin próxima sesión programada todavía, nada que forzar.
            }

            // Comparación directa de fechas (en vez de diffInHours con signo,
            // ambiguo de leer) — la sesión "se vuelve próxima" cuando su
            // fecha cae dentro de las próximas WINDOW_HOURS horas.
            // resolveNextAssignmentForExercise() ya filtra scheduled_date >=
            // hoy, así que nunca llega aquí una sesión ya pasada de días
            // anteriores.
            $windowEnd = now()->addHours(self::WINDOW_HOURS);
            if ($assignment->scheduled_date->startOfDay()->greaterThan($windowEnd)) {
                continue; // Todavía fuera de la ventana de 24h.
            }

            switch ($rule->fallback_behavior) {
                case FallbackBehavior::APLICAR_IGUAL:
                    $engine->applyToNextScheduledSession((int) $target->client_id, (int) $target->exercise_id, $target->proposed_weight, $target->proposed_reps);
                    $target->status = TargetStatus::APLICADO;
                    $target->resolved_at = now();
                    $target->save();
                    $this->log($target, ActionTaken::ACCEPTED, 'fallback: aplicar_igual (sin respuesta del coach en ventana de 24h)');
                    $applied++;
                    break;

                case FallbackBehavior::MANTENER_SIN_CAMBIO:
                    $target->status = TargetStatus::RECHAZADO;
                    $target->resolved_at = now();
                    $target->save();
                    $this->log($target, ActionTaken::REJECTED, 'fallback: mantener_sin_cambio (sin respuesta del coach en ventana de 24h)');
                    $rejected++;
                    break;

                case FallbackBehavior::ESCALAR_A_NOTIFICACION_URGENTE:
                    // No resuelve el target (sigue pendiente, el coach
                    // todavía puede decidir) -> solo escala la alerta, una
                    // única vez por target (evita spam en corridas
                    // sucesivas dentro de la misma ventana de 24h).
                    $cacheKey = "spr_fallback_escalated_{$target->id}";
                    if (!Cache::has($cacheKey)) {
                        Cache::put($cacheKey, true, now()->addHours(self::WINDOW_HOURS + 6));
                        $this->notifyUrgent($target, $rule, $assignment);
                        $escalated++;
                    }
                    break;
            }
        }

        $this->info("Fallbacks aplicados: {$applied} aplicados, {$rejected} rechazados, {$escalated} escalados.");

        return self::SUCCESS;
    }

    private function log(NextSessionTarget $target, ActionTaken $action, string $motivo): void
    {
        OverrideLog::create([
            'next_session_target_id' => $target->id,
            'rule_id'                 => $target->rule_id,
            'client_id'               => $target->client_id,
            'exercise_id'             => $target->exercise_id,
            'suggested_value'         => $target->proposed_weight ?? $target->proposed_reps ?? 0,
            'applied_value'           => $target->proposed_weight ?? $target->proposed_reps ?? 0,
            'action_taken'            => $action->value,
            'motivo'                  => $motivo,
        ]);
    }

    private function notifyUrgent(NextSessionTarget $target, $rule, $assignment): void
    {
        $coach = User::find($rule->coach_id);
        if (!$coach) {
            return;
        }

        $client = User::find($target->client_id);
        $exerciseTitle = optional($target->exercise)->title ?? 'un ejercicio';

        $coach->notify(new CommonNotification('progression_fallback_urgent', [
            'id'      => $target->id,
            'type'    => 'progression_fallback_urgent',
            'subject' => 'Sugerencia de carga sin resolver',
            'message' => 'Hay una sugerencia de carga pendiente para '
                .optional($client)->display_name." en \"{$exerciseTitle}\" y la sesión es en menos de 24h. Revísala antes de que empiece.",
        ]));
    }
}
