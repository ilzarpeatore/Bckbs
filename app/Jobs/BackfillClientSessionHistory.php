<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\SessionInterpretationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Motor de Auto-Regulación de Carga (2026-08-12) — hallazgo real: un
 * cliente que pasa de free a paid-tier (personal o por Package) arranca el
 * motor desde cero, aunque tenga años de sesiones reales completadas.
 * `exercise_session_metrics` (de la que se alimenta TODO el motor — ACWR,
 * tendencias, calibración) solo se rellena hacia adelante desde
 * finishSession(), nunca retroactivamente. Este job reprocesa el historial
 * ya existente en el momento exacto en que el cliente se activa, para que
 * ACWR/tendencias/calibración arranquen con contexto real en vez de vacío.
 *
 * Alcance deliberado — SOLO exercise_session_metrics (Fase 1, capa de
 * interpretación), NO se re-evalúan reglas de progresión ni logros:
 * - Reglas (Fase 2): "next_session_target" solo tiene sentido para la
 *   PRÓXIMA sesión real — generarlos en masa sobre sesiones ya pasadas
 *   inundaría el Panel de Excepciones del coach con sugerencias obsoletas
 *   sobre entrenamientos que ya ocurrieron.
 * - Logros (Fase 3): los PRs reales ya existen en `personal_records` sin
 *   gate de tier (se calculan para todos los clientes desde siempre);
 *   racha/hito_compliance leen `workout_session_reviews` directamente, no
 *   `exercise_session_metrics` — no necesitan backfill, se recalculan bien
 *   solos en la próxima sesión real.
 *
 * Capado a los últimos 90 días (de sobra para las ventanas reales que usa
 * el motor: ACWR 28 días, z-scores 14 días, calibración) — evita una
 * operación síncrona sin límite en una cuenta con muchísimo histórico.
 */
class BackfillClientSessionHistory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    private int $clientId;

    public function __construct(User $client)
    {
        $this->clientId = $client->id;
    }

    public function handle(SessionInterpretationService $service): void
    {
        $client = User::find($this->clientId);
        if (!$client) {
            return;
        }

        $service->backfillHistoryForClient($client);
    }
}
