<?php

namespace App\Services;

use App\Enums\ExceptionCategory;
use App\Enums\ExceptionSeverity;
use App\Enums\RetentionNudgeStage;
use App\Enums\RiskBand;
use App\Http\Controllers\API\ClientCalendarController;
use App\Models\AchievementEvent;
use App\Models\ClientRetentionNudge;
use App\Models\CoachScoreWeightConfig;
use App\Models\PainReport;
use App\Models\ProgramClientAssignment;
use App\Models\RetentionRiskScore;
use App\Models\User;
use App\Models\WorkoutSessionReview;
use App\Notifications\CommonNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Score de Riesgo de Abandono (docs/Score_Riesgo_Abandono_Implementacion.md).
 * Job diario, mismo patrón que ReadinessCalculationService: gate paid-tier
 * comprobado aquí (no hay request, corre en background), idempotente vía
 * unique(client_id, date) + updateOrCreate.
 *
 * Reconciliación hecha antes de escribir esto (§0 del documento):
 * - User.last_active_at EXISTE en el esquema (migración 2026_07_19_090002)
 *   pero no se escribe en NINGÚN sitio del código — columna muerta hoy, no
 *   se usa aquí. Documentado como mejora futura real (un middleware que la
 *   actualice en cada request autenticado), no construida en esta ronda.
 * - ReadinessCalculationService guarda sus pesos en config/readiness.php
 *   (estático, sin tabla, sin granularidad por coach) -- no había ningún
 *   patrón de tabla reutilizable. Se creó `coach_score_weight_configs`
 *   (genérica, `score_type` string) tal como proponía el documento como
 *   plan B -- Readiness NO se migra a esto, fuera de alcance.
 * - hito_compliance (Fase 3) reutiliza EXACTAMENTE
 *   ClientCalendarController::computeAdherence(), ampliado con un parámetro
 *   $asOf opcional (backward-compatible) para poder pedir la ventana
 *   "anterior" (4 semanas antes de las últimas 4), no reimplementado.
 * - Push al cliente: no existe ningún canal de push server-side aparte de
 *   CommonNotification (la app solo tiene recordatorios LOCALES vía
 *   expo-notifications, programados en el dispositivo, no un servicio de
 *   servidor) -- se reutiliza CommonNotification, mismo patrón que el resto
 *   del Motor. Su transporte real (OneSignal) sigue sin credenciales
 *   configuradas, limitación ya documentada y ajena a esta feature.
 */
class RetentionRiskCalculationService
{
    // §2.1 -- override no configurable.
    private const INACTIVITY_OVERRIDE_DAYS = 20;
    private const INACTIVITY_LINEAR_START = 7;

    // §2.2 -- compliance.
    private const COMPLIANCE_WINDOW_DAYS = 28;
    private const COMPLIANCE_COLD_START_WEEKS = 8;
    private const COMPLIANCE_DECLINE_CAP = 40.0;

    // §2.3 -- ausencia de logro.
    private const ACHIEVEMENT_GRACE_DAYS = 14;
    private const ACHIEVEMENT_CAP_DAYS = 45;

    // §2.4 -- dolor.
    private const PAIN_WINDOW_DAYS = 30;
    private const PAIN_MAX_INTENSITY = 5;
    private const PAIN_SCORE_CAP = 3.0;

    // §3 -- pesos por defecto (redistribuidos si falta un componente).
    private const DEFAULT_WEIGHTS = [
        'inactividad' => 0.40,
        'compliance'  => 0.25,
        'logro'       => 0.20,
        'dolor'       => 0.15,
    ];

    // §8.2 -- textos de los 3 escalones, tal cual el documento (sin lenguaje de urgencia/culpa).
    private const NUDGE_MESSAGES = [
        'dia_7'  => '¿Todo bien? Tu próxima sesión te está esperando cuando quieras retomarla.',
        'dia_14' => 'Si esta temporada está siendo complicada, puedes ajustar tu semana directamente desde la app — no hace falta que sea todo o nada.',
        'dia_20' => 'Tu entrenador también lo sabe y está para ayudarte a retomarlo como mejor te encaje.',
    ];

    /** Público: usado por RetentionRiskController para mostrar los valores por defecto en el panel admin (claves w1-w4, no los nombres internos de componente). */
    public static function defaultWeights(): array
    {
        return [
            'w1' => self::DEFAULT_WEIGHTS['inactividad'],
            'w2' => self::DEFAULT_WEIGHTS['compliance'],
            'w3' => self::DEFAULT_WEIGHTS['logro'],
            'w4' => self::DEFAULT_WEIGHTS['dolor'],
        ];
    }

    /** Público: mismo motivo que defaultWeights() -- el panel admin necesita mostrar el texto por defecto como placeholder cuando el coach no ha personalizado nada. */
    public static function defaultNudgeMessages(): array
    {
        return self::NUDGE_MESSAGES;
    }

    public function calculateForAllPaidClients(?Carbon $date = null): int
    {
        $date = ($date ?? now())->copy()->startOfDay();
        $count = 0;

        User::where('user_type', 'user')
            ->where('status', 'active')
            ->whereNotNull('coach_id')
            ->chunkById(100, function ($clients) use ($date, &$count) {
                foreach ($clients as $client) {
                    if (!Gate::forUser($client)->allows('paid-tier')) {
                        continue;
                    }
                    $this->calculateForClient($client, $date);
                    $count++;
                }
            });

        return $count;
    }

    public function calculateForClient(User $client, ?Carbon $date = null): RetentionRiskScore
    {
        $date = ($date ?? now())->copy()->startOfDay();

        [$diasInactividad, $inactivityReferenceDate] = $this->inactivityInfo($client->id, $date);
        [$complianceActual, $complianceAnterior] = $this->complianceWindows($client, $date);
        $diasDesdeLogro = $this->diasDesdeUltimoLogro($client->id, $date);
        $dolorScore = $this->dolorScore($client->id, $date);

        $normalized = [
            'inactividad' => $this->normInactividad($diasInactividad),
            'compliance'  => $this->normCompliance($complianceActual, $complianceAnterior),
            'logro'       => $this->normLogro($diasDesdeLogro, $client->id, $date),
            'dolor'       => $this->normDolor($dolorScore),
        ];

        $weights = $this->resolveWeights($client->coach_id);
        $combined = $this->combine($normalized, $weights);
        $band = $this->mapBand($diasInactividad, $combined);

        $score = RetentionRiskScore::updateOrCreate(
            ['client_id' => $client->id, 'date' => $date->toDateString()],
            [
                'dias_inactividad'         => $diasInactividad,
                'compliance_actual'        => $complianceActual,
                'compliance_anterior'      => $complianceAnterior,
                'dias_desde_ultimo_logro'  => $diasDesdeLogro,
                'dolor_score'              => $dolorScore,
                'combined_score'           => $combined,
                'band'                     => $band->value,
                'calculated_at'            => now(),
            ]
        );

        $this->syncExceptionItem($client, $score, $band, $normalized);
        $this->evaluateNudge($client, $diasInactividad, $inactivityReferenceDate, $date);

        return $score;
    }

    // ═══ Componentes ═════════════════════════════════════════════════════

    /**
     * @return array{0: ?int, 1: ?Carbon} [días de inactividad, fecha de
     * referencia usada -- reutilizada tal cual como episode_reference_date
     * del nudge, documento §8.3].
     */
    private function inactivityInfo(int $clientId, Carbon $date): array
    {
        // NOTA Carbon 3: diffInDays() ya no es absoluto por defecto (cambio
        // de comportamiento respecto a Carbon 2) -- abs() explícito en todo
        // el archivo para no depender del orden receptor/argumento.
        $last = WorkoutSessionReview::where('user_id', $clientId)->max('completed_at');
        if ($last) {
            $ref = Carbon::parse($last)->startOfDay();
            return [abs($date->diffInDays($ref)), $ref];
        }

        // Nunca completó ninguna sesión -- mismo fallback que ausencia_logro
        // (§2.3): usar el inicio de su primera asignación de programa.
        $firstAssignment = ProgramClientAssignment::where('client_id', $clientId)->min('start_date');
        if ($firstAssignment) {
            $ref = Carbon::parse($firstAssignment)->startOfDay();
            return [abs($date->diffInDays($ref)), $ref];
        }

        return [null, null];
    }

    /** Público: reutilizado por RetentionRiskController para calcular el componente dominante a partir de una fila ya persistida. */
    public function normInactividad(?int $dias): ?float
    {
        if ($dias === null) {
            return null;
        }
        if ($dias < self::INACTIVITY_LINEAR_START) {
            return 0.0;
        }
        if ($dias >= self::INACTIVITY_OVERRIDE_DAYS) {
            return 1.0;
        }

        return round((($dias - self::INACTIVITY_LINEAR_START) / (19 - self::INACTIVITY_LINEAR_START)) * 0.7, 4);
    }

    /** @return array{0: ?float, 1: ?float} [compliance_actual, compliance_anterior], ambos en % (0-100). */
    private function complianceWindows(User $client, Carbon $date): array
    {
        $earliestStart = ProgramClientAssignment::where('client_id', $client->id)
            ->where('activo', true)
            ->min('start_date');

        if (!$earliestStart) {
            return [null, null]; // sin programa asignado -> componente no aplica.
        }

        $weeksSinceStart = abs(Carbon::parse($earliestStart)->diffInDays($date)) / 7;
        if ($weeksSinceStart < self::COMPLIANCE_COLD_START_WEEKS) {
            return [null, null]; // cold start explícito (documento §2.2).
        }

        $actual = ClientCalendarController::computeAdherence($client->id, self::COMPLIANCE_WINDOW_DAYS, $date);
        $anterior = ClientCalendarController::computeAdherence(
            $client->id,
            self::COMPLIANCE_WINDOW_DAYS,
            $date->copy()->subDays(self::COMPLIANCE_WINDOW_DAYS)
        );

        $actualRatio = ($actual['mode'] === 'program' && $actual['ratio'] !== null) ? round($actual['ratio'] * 100, 2) : null;
        $anteriorRatio = ($anterior['mode'] === 'program' && $anterior['ratio'] !== null) ? round($anterior['ratio'] * 100, 2) : null;

        return [$actualRatio, $anteriorRatio];
    }

    public function normCompliance(?float $actual, ?float $anterior): ?float
    {
        if ($actual === null || $anterior === null) {
            return null;
        }

        $declive = $anterior - $actual;
        if ($declive <= 0) {
            return 0.0;
        }
        if ($declive > self::COMPLIANCE_DECLINE_CAP) {
            return 1.0;
        }

        return round($declive / self::COMPLIANCE_DECLINE_CAP, 4);
    }

    private function diasDesdeUltimoLogro(int $clientId, Carbon $date): ?int
    {
        $last = AchievementEvent::where('client_id', $clientId)->max('created_at');
        if ($last) {
            return abs($date->diffInDays(Carbon::parse($last)->startOfDay()));
        }

        $firstAssignment = ProgramClientAssignment::where('client_id', $clientId)->min('start_date');
        if ($firstAssignment) {
            return abs($date->diffInDays(Carbon::parse($firstAssignment)->startOfDay()));
        }

        return null;
    }

    private function normLogro(?int $dias, int $clientId, Carbon $date): ?float
    {
        // Cold start (documento §2.3): <14 días desde la primera asignación
        // -> no penalizar, es normal no tener logros todavía.
        $firstAssignment = ProgramClientAssignment::where('client_id', $clientId)->min('start_date');
        if ($firstAssignment && abs($date->diffInDays(Carbon::parse($firstAssignment)->startOfDay())) < self::ACHIEVEMENT_GRACE_DAYS) {
            return null;
        }

        return $this->normLogroValue($dias);
    }

    /** Público: parte pura de normLogro(), sin el chequeo de cold start (ya reflejado en el null persistido). */
    public function normLogroValue(?int $dias): ?float
    {
        if ($dias === null) {
            return null;
        }
        if ($dias < self::ACHIEVEMENT_GRACE_DAYS) {
            return 0.0;
        }
        if ($dias > self::ACHIEVEMENT_CAP_DAYS) {
            return 1.0;
        }

        return round(($dias - self::ACHIEVEMENT_GRACE_DAYS) / (self::ACHIEVEMENT_CAP_DAYS - self::ACHIEVEMENT_GRACE_DAYS), 4);
    }

    private function dolorScore(int $clientId, Carbon $date): float
    {
        $sum = (float) PainReport::where('client_id', $clientId)
            ->where('created_at', '>=', $date->copy()->subDays(self::PAIN_WINDOW_DAYS))
            ->sum('intensidad');

        return round($sum / self::PAIN_MAX_INTENSITY, 2);
    }

    public function normDolor(float $dolorScore): float
    {
        if ($dolorScore <= 0) {
            return 0.0;
        }
        if ($dolorScore > self::PAIN_SCORE_CAP) {
            return 1.0;
        }

        return round($dolorScore / self::PAIN_SCORE_CAP, 4);
    }

    // ═══ Combinación y banda ═════════════════════════════════════════════

    private function resolveWeights(?int $coachId): array
    {
        if (!$coachId) {
            return self::DEFAULT_WEIGHTS;
        }

        $config = CoachScoreWeightConfig::where('coach_id', $coachId)->where('score_type', 'retention_risk')->first();
        if (!$config) {
            return self::DEFAULT_WEIGHTS;
        }

        return [
            'inactividad' => $config->w1 ?? self::DEFAULT_WEIGHTS['inactividad'],
            'compliance'  => $config->w2 ?? self::DEFAULT_WEIGHTS['compliance'],
            'logro'       => $config->w3 ?? self::DEFAULT_WEIGHTS['logro'],
            'dolor'       => $config->w4 ?? self::DEFAULT_WEIGHTS['dolor'],
        ];
    }

    /** Redistribución proporcional si falta un componente -- mismo criterio que ReadinessCalculationService::combine(). */
    private function combine(array $normalized, array $weights): ?float
    {
        $available = array_filter($normalized, fn ($v) => $v !== null);
        if (empty($available)) {
            return null;
        }

        $availableWeightSum = array_sum(array_intersect_key($weights, $available));
        if ($availableWeightSum <= 0.0) {
            return round(array_sum($available) / count($available), 4);
        }

        $combined = 0.0;
        foreach ($available as $key => $value) {
            $combined += $value * ($weights[$key] / $availableWeightSum);
        }

        return round($combined, 4);
    }

    private function mapBand(?int $diasInactividad, ?float $combined): RiskBand
    {
        // §2.1/§4: override directo, gana sobre el cálculo ponderado -- mismo
        // patrón que el bloqueo por dolor en el motor de progresión.
        if ($diasInactividad !== null && $diasInactividad >= self::INACTIVITY_OVERRIDE_DAYS) {
            return RiskBand::ALTO;
        }

        if ($combined === null) {
            return RiskBand::DATO_INSUFICIENTE;
        }
        if ($combined >= 0.6) {
            return RiskBand::ALTO;
        }
        if ($combined >= 0.3) {
            return RiskBand::MEDIO;
        }

        return RiskBand::BAJO;
    }

    /**
     * Público: recalcula los 4 normalizados a partir de una fila ya
     * persistida, sin repetir el chequeo de cold start (ya reflejado en los
     * `null` guardados en su momento) -- usado por RetentionRiskController
     * para el listado de resumen (§9), donde no interesa recalcular todo el
     * score, solo derivar el componente dominante de lo ya guardado.
     */
    public function normalizedFromScore(RetentionRiskScore $score): array
    {
        return [
            'inactividad' => $this->normInactividad($score->dias_inactividad),
            'compliance'  => $this->normCompliance(
                $score->compliance_actual !== null ? (float) $score->compliance_actual : null,
                $score->compliance_anterior !== null ? (float) $score->compliance_anterior : null
            ),
            'logro' => $this->normLogroValue($score->dias_desde_ultimo_logro),
            'dolor' => $score->dolor_score !== null ? $this->normDolor((float) $score->dolor_score) : null,
        ];
    }

    /** Público: componente dominante de una fila ya persistida, o null si no hay ninguno calculable. */
    public function dominantComponent(RetentionRiskScore $score): ?string
    {
        if ($score->dias_inactividad !== null && $score->dias_inactividad >= self::INACTIVITY_OVERRIDE_DAYS) {
            return 'inactividad';
        }

        $available = array_filter($this->normalizedFromScore($score), fn ($v) => $v !== null);
        if (empty($available)) {
            return null;
        }
        arsort($available);

        return array_key_first($available);
    }

    // ═══ Panel de Excepciones ════════════════════════════════════════════

    /**
     * §7: a diferencia del resto de categorías, aquí SÍ interesa actualizar
     * el ítem existente in-place (el riesgo puede subir de medio a alto de
     * un día a otro) -- CoachExceptionFeedService::upsertForClientCategory().
     */
    private function syncExceptionItem(User $client, RetentionRiskScore $score, RiskBand $band, array $normalized): void
    {
        if (!$client->coach_id) {
            return;
        }

        $feed = new CoachExceptionFeedService();

        if ($band === RiskBand::BAJO || $band === RiskBand::DATO_INSUFICIENTE) {
            $feed->autoResolveByClientCategory($client->id, ExceptionCategory::RIESGO_ABANDONO);
            return;
        }

        $severity = $band === RiskBand::ALTO ? ExceptionSeverity::ALTA : ExceptionSeverity::MEDIA;
        [$title, $description] = $this->buildTitle($score, $band, $normalized);

        $feed->upsertForClientCategory(
            coachId: (int) $client->coach_id,
            clientId: $client->id,
            category: ExceptionCategory::RIESGO_ABANDONO,
            severity: $severity,
            sourceType: RetentionRiskScore::class,
            sourceId: $score->id,
            title: $title,
            description: $description
        );
    }

    /**
     * §7: el título debe incluir el componente dominante para que el coach
     * sepa por qué sin abrir el detalle. "Dominante" = mayor contribución
     * normalizada (0-1) entre los componentes que sí se pudieron calcular,
     * no el de mayor peso -- el coach quiere saber qué está fallando, no
     * cómo está configurada la fórmula.
     */
    private function buildTitle(RetentionRiskScore $score, RiskBand $band, array $normalized): array
    {
        $bandLabel = $band === RiskBand::ALTO ? 'Riesgo alto' : 'Riesgo medio';

        if ($score->dias_inactividad !== null && $score->dias_inactividad >= self::INACTIVITY_OVERRIDE_DAYS) {
            return [
                "{$bandLabel} — {$score->dias_inactividad} días sin entrenar",
                'Umbral de 20 días de silencio alcanzado — override directo, independiente del resto de componentes.',
            ];
        }

        $available = array_filter($normalized, fn ($v) => $v !== null);
        if (empty($available)) {
            return ["{$bandLabel} — riesgo compuesto elevado", 'Varias señales combinadas apuntan a riesgo de abandono.'];
        }
        arsort($available);
        $dominant = array_key_first($available);

        return match ($dominant) {
            'compliance' => [
                "{$bandLabel} — compliance bajó " . round($score->compliance_anterior - $score->compliance_actual) . ' puntos',
                "Cumplimiento últimas 4 semanas: {$score->compliance_actual}% (antes {$score->compliance_anterior}%).",
            ],
            'logro' => [
                "{$bandLabel} — {$score->dias_desde_ultimo_logro} días sin ningún logro",
                'Sin PRs, rachas ni hitos de cumplimiento recientes.',
            ],
            'dolor' => [
                "{$bandLabel} — molestias recurrentes reportadas",
                'Frecuencia/intensidad de dolor elevada en los últimos 30 días.',
            ],
            default => [
                "{$bandLabel} — {$score->dias_inactividad} días sin entrenar",
                'Actividad reciente por debajo de lo esperado.',
            ],
        };
    }

    // ═══ Reenganche (§8) ═════════════════════════════════════════════════

    /** Mensaje personalizado del coach si existe (§8.2 ampliado a petición del usuario, editable desde el panel admin), o el texto por defecto. */
    private function nudgeMessage(RetentionNudgeStage $stage, ?CoachScoreWeightConfig $config): string
    {
        $custom = match ($stage) {
            RetentionNudgeStage::DIA_7 => $config?->msg_dia_7,
            RetentionNudgeStage::DIA_14 => $config?->msg_dia_14,
            RetentionNudgeStage::DIA_20 => $config?->msg_dia_20,
        };

        return $custom ?: self::NUDGE_MESSAGES[$stage->value];
    }

    private function evaluateNudge(User $client, ?int $diasInactividad, ?Carbon $episodeReferenceDate, Carbon $date): void
    {
        if ($diasInactividad === null || $diasInactividad < self::INACTIVITY_LINEAR_START || !$episodeReferenceDate) {
            return;
        }

        $stage = match (true) {
            $diasInactividad >= 20 => RetentionNudgeStage::DIA_20,
            $diasInactividad >= 14 => RetentionNudgeStage::DIA_14,
            default => RetentionNudgeStage::DIA_7,
        };

        $config = $client->coach_id
            ? CoachScoreWeightConfig::where('coach_id', $client->coach_id)->where('score_type', 'retention_risk')->first()
            : null;
        if ($config && !$config->auto_reengagement_enabled) {
            return; // §8.5: coach desactivó el canal -- el score y el panel siguen funcionando igual, solo se salta esto.
        }

        // §8.3/§8.4: unique(client_id, stage, episode_reference_date) es lo
        // que hace que un episodio nuevo (fecha de referencia distinta)
        // libere las 3 etapas de nuevo, sin lógica de reset explícita.
        $alreadySent = ClientRetentionNudge::where('client_id', $client->id)
            ->where('stage', $stage->value)
            ->where('episode_reference_date', $episodeReferenceDate->toDateString())
            ->exists();
        if ($alreadySent) {
            return;
        }

        $status = 'fallido';
        try {
            $client->notify(new CommonNotification('retention_nudge_' . $stage->value, [
                'id'      => $client->id,
                'type'    => 'retention_nudge_' . $stage->value,
                'subject' => 'Te echamos de menos',
                'message' => $this->nudgeMessage($stage, $config),
            ]));
            $status = 'enviado';
        } catch (\Throwable $e) {
            // No relanzar -- un fallo de notificación (ej. transporte push
            // sin credenciales, limitación ya documentada) no debe romper
            // el cálculo del score del resto de clientes en el mismo job.
        }

        ClientRetentionNudge::create([
            'client_id'              => $client->id,
            'stage'                  => $stage->value,
            'episode_reference_date' => $episodeReferenceDate->toDateString(),
            'sent_at'                => now(),
            'channel'                => 'push',
            'status'                 => $status,
        ]);
    }
}
