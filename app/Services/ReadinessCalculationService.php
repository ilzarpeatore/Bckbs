<?php

namespace App\Services;

use App\Enums\ExceptionCategory;
use App\Enums\ExceptionSeverity;
use App\Models\DailyReadinessCheck;
use App\Models\ExerciseSessionMetric;
use App\Models\HealthDataPoint;
use App\Models\ReadinessScore;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Motor de Auto-Regulación de Carga — Fase 4 (readiness score, documento
 * §4.1). Job diario (comando readiness:calculate, ver Kernel::schedule),
 * SOLO para clientes con Gate::forUser($client)->allows('paid-tier') —
 * gate resuelto para el cliente concreto del job, no para el usuario
 * autenticado (esto corre en background, no hay request).
 *
 * "readiness_questionnaire_responses" del documento original NO se crea
 * (decisión ya confirmada) — el componente subjetivo se lee directamente
 * de daily_readiness_checks (energy_level/stress_level/sleep_quality/
 * soreness_level), que cubre el mismo propósito con nombres de columna
 * reales distintos.
 *
 * NORMALIZACIÓN (decisión propia, el documento no especifica la escala de
 * combinación): cada fuente se normaliza a una escala 0-100 ("cuánto de
 * bien está el cliente" en esa fuente) antes de combinarse con los pesos
 * configurables (config/readiness.php). z-scores se centran en 50 (z=0,
 * neutro) con ±20 puntos por desviación estándar; subjetivo_score (1-5) se
 * reescala linealmente a 0-100; ACWR usa la banda "óptima" 0.8-1.3 de la
 * literatura de ciencias del deporte (100 puntos dentro de esa banda,
 * penalización más agresiva por encima de 1.3 que por debajo de 0.8, ya
 * que ACWR alto es la señal de riesgo de lesión real).
 */
class ReadinessCalculationService
{
    private const WINDOW_DAYS = 14;
    private const MIN_HISTORY_DAYS = 7;
    private const ACUTE_DAYS = 7;
    private const CHRONIC_DAYS = 28;

    // Umbral de "zona baja" para resolución de conflicto (documento §4.1
    // punto 7): z <= -1.0 desviación estándar se considera zona baja para
    // una fuente objetiva (hrv/sueño); subjetivo_score <= 2/5 tal cual
    // especifica el documento literalmente.
    private const OBJETIVA_BAJA_Z = -1.0;
    private const SUBJETIVA_BAJA_UMBRAL = 2.0;

    /**
     * Corre el cálculo para todos los clientes paid-tier. Devuelve el
     * número de readiness_scores calculados (idempotente: reprocesar el
     * mismo día actualiza en vez de duplicar, por el unique(client_id,date)).
     */
    public function calculateForAllPaidClients(?Carbon $date = null): int
    {
        $date = $date ?? now()->startOfDay();
        $count = 0;

        User::where('user_type', 'user')
            ->where('status', 'active')
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

    /**
     * Calcula (o recalcula) el readiness_score de un cliente concreto para
     * una fecha. No comprueba el gate paid-tier aquí (regla del plan: el
     * gate ya se comprobó en el punto de entrada — calculateForAllPaidClients
     * o quien llame directamente en tinker/tests debe asumir la
     * responsabilidad, igual que el resto de servicios de Fase 2+).
     */
    public function calculateForClient(User $client, ?Carbon $date = null): ReadinessScore
    {
        $date = ($date ?? now())->copy()->startOfDay();

        $metricPoints = $this->loadMetricPoints($client->id, $date);
        $hrvZ = $this->zScoreForMetric($metricPoints, 'hrv', $date);
        $suenoZ = $this->zScoreForMetric($metricPoints, 'sleep_hours', $date);
        $restingHrZ = $this->zScoreForMetric($metricPoints, 'resting_hr', $date);
        $subjetivo = $this->subjetivoScore($client->id, $date);
        $acwr = $this->acwr($client->id, $date);

        $normalized = [
            'hrv'        => $this->normalizeZ($hrvZ),
            'sueno'      => $this->normalizeZ($suenoZ),
            // AÑADIDO 2026-08-12: FC en reposo -- dirección invertida frente
            // a hrv/sueno (z alto = FC elevada vs. tu línea base = MALO, no
            // bueno), se le pasa el z negado a normalizeZ() para que la
            // escala 0-100 siga significando "más alto = mejor" en todo el
            // pipeline sin tener que tocar normalizeZ()/combine().
            'resting_hr' => $restingHrZ !== null ? $this->normalizeZ(-$restingHrZ) : null,
            'subjetivo'  => $this->normalizeSubjetivo($subjetivo),
            'acwr'       => $this->normalizeAcwr($acwr),
        ];

        $combined = $this->combine($normalized);
        $band = $this->mapBand($combined, $hrvZ, $suenoZ, $restingHrZ, $subjetivo);

        $score = ReadinessScore::updateOrCreate(
            ['client_id' => $client->id, 'date' => $date->toDateString()],
            [
                'hrv_z_score'     => $hrvZ,
                'sueno_z_score'   => $suenoZ,
                'subjetivo_score' => $subjetivo,
                'acwr'            => $acwr,
                'combined_score'  => $combined,
                'band'            => $band,
                'calculated_at'   => now(),
            ]
        );

        $this->syncReadinessExceptionItem($client, $date, $band, $score->id);

        return $score;
    }

    /**
     * Panel de Excepciones del Coach (documento §3.4). No genera un ítem
     * por cada día individual `bajo` (ruido diario) -- solo cuando el día
     * ANTERIOR también fue `bajo` (2+ consecutivos), y solo si no hay ya un
     * ítem `pendiente` abierto para esta categoría+cliente (evita crear uno
     * nuevo cada día mientras el cliente sigue bajo, algo que el criterio
     * de aceptación del documento no pide). Si el band de hoy ya NO es
     * `bajo`, resuelve automáticamente cualquier ítem abierto -- el coach
     * no tiene que descartarlo a mano cuando ya se resolvió solo.
     */
    private function syncReadinessExceptionItem(User $client, Carbon $date, string $band, int $scoreId): void
    {
        if (!$client->coach_id) {
            return;
        }

        $feed = new CoachExceptionFeedService();

        if ($band !== 'bajo') {
            $feed->autoResolveByClientCategory($client->id, ExceptionCategory::READINESS_BAJO);
            return;
        }

        if ($feed->hasPendingForClientCategory($client->id, ExceptionCategory::READINESS_BAJO)) {
            return; // ya hay un ítem abierto, no duplicar mientras se mantenga bajo.
        }

        $yesterday = ReadinessScore::where('client_id', $client->id)
            ->where('date', $date->copy()->subDay()->toDateString())
            ->first();

        if (!$yesterday || $yesterday->band !== 'bajo') {
            return; // primer día bajo -- todavía no son 2 consecutivos.
        }

        $feed->createOrSkip(
            coachId: (int) $client->coach_id,
            clientId: $client->id,
            category: ExceptionCategory::READINESS_BAJO,
            severity: ExceptionSeverity::MEDIA,
            sourceType: ReadinessScore::class,
            sourceId: $scoreId,
            title: 'Readiness bajo sostenido',
            description: "Readiness en banda 'bajo' desde hace al menos 2 días ({$date->copy()->subDay()->toDateString()} y {$date->toDateString()})."
        );
    }

    /**
     * Trae en UNA sola query los puntos de las 3 métricas que
     * calculateForClient necesita (hrv, sleep_hours, resting_hr) dentro de
     * la ventana de WINDOW_DAYS, agrupados por metric_type. Sustituye las 3
     * queries casi idénticas que zScoreForMetric() hacía antes (una por
     * métrica, mismo rango de fechas) por una sola con whereIn — la media y
     * desviación de cada métrica se siguen calculando por separado en PHP a
     * partir de esta única colección, sin cambiar la fórmula.
     */
    private function loadMetricPoints(int $clientId, Carbon $date): Collection
    {
        $windowStart = $date->copy()->subDays(self::WINDOW_DAYS - 1);

        return HealthDataPoint::where('client_id', $clientId)
            ->whereIn('metric_type', ['hrv', 'sleep_hours', 'resting_hr'])
            ->whereBetween('recorded_date', [$windowStart->toDateString(), $date->toDateString()])
            ->get(['metric_type', 'value', 'recorded_date'])
            ->groupBy('metric_type');
    }

    /**
     * Media móvil + desviación estándar de los últimos 14 días (ventana que
     * incluye la fecha de cálculo) para una métrica de health_data_points.
     * Cold start (documento §4.1 punto 2): si hay <7 días distintos con
     * dato en la ventana, se excluye la métrica (null), sin rellenar con
     * valor neutro. Si no hay lectura para la propia fecha de cálculo
     * dentro de esa ventana, tampoco hay nada que puntuar hoy -> null
     * (aunque haya histórico suficiente).
     *
     * $pointsByMetric viene de loadMetricPoints() -- ya trae las 3 métricas
     * de los últimos WINDOW_DAYS días en una sola consulta; aquí solo se
     * filtra la porción de $metricType y se calcula el z-score igual que
     * antes.
     */
    private function zScoreForMetric(Collection $pointsByMetric, string $metricType, Carbon $date): ?float
    {
        $points = $pointsByMetric->get($metricType, collect());

        $distinctDays = $points->pluck('recorded_date')->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)->unique()->count();

        if ($distinctDays < self::MIN_HISTORY_DAYS) {
            return null; // cold start, excluida del cálculo combinado
        }

        $today = $points->first(function ($p) use ($date) {
            $d = $p->recorded_date instanceof Carbon ? $p->recorded_date->toDateString() : (string) $p->recorded_date;
            return $d === $date->toDateString();
        });

        if (!$today) {
            return null; // no hay lectura de hoy que puntuar
        }

        $values = $points->pluck('value')->map(fn ($v) => (float) $v);
        $mean = $values->avg();
        $variance = $values->map(fn ($v) => ($v - $mean) ** 2)->avg();
        $stdDev = sqrt($variance);

        if ($stdDev == 0.0) {
            return 0.0; // sin variación histórica, hoy es exactamente la media
        }

        return round(((float) $today->value - $mean) / $stdDev, 4);
    }

    /**
     * subjetivo_score (1-5, mayor = mejor) desde daily_readiness_checks del
     * día. Mapeo (decisión propia, ver comentario de clase):
     * - energy_level (1-5, mayor=mejor) tal cual.
     * - stress_level (1-5, mayor=peor) invertido: 6 - stress_level.
     * - sleep_quality (1-5, mayor=mejor) tal cual.
     * - soreness_level (1-10, mayor=peor) reescalado a 1-5 e invertido.
     * Null si el cliente no rellenó el cuestionario ese día (no se rellena
     * con valor neutro, igual criterio que el resto de fuentes).
     */
    private function subjetivoScore(int $clientId, Carbon $date): ?float
    {
        $check = DailyReadinessCheck::where('user_id', $clientId)
            ->whereDate('date', $date->toDateString())
            ->first();

        if (!$check) {
            return null;
        }

        $sorenessOn5 = (((10 - $check->soreness_level) / 9) * 4) + 1;
        $stressInverted = 6 - $check->stress_level;

        $score = collect([
            $check->energy_level,
            $stressInverted,
            $check->sleep_quality,
            $sorenessOn5,
        ])->avg();

        return round((float) $score, 4);
    }

    /**
     * ACWR = carga aguda (suma carga_efectiva últimos 7 días) / carga
     * crónica (media móvil semanal de las últimas 4 semanas = suma de 28
     * días / 4). Reutiliza exercise_session_metrics (Fase 1) vía
     * workout_session_review.completed_at para fechar cada métrica -
     * exercise_session_metrics no tiene columna de fecha propia (es un
     * output derivado, con created_at = momento de cómputo, no de la
     * sesión).
     *
     * Antes: 2 queries con el mismo JOIN, una por ventana (aguda 7d,
     * crónica 28d). Como ACUTE_DAYS < CHRONIC_DAYS, la ventana aguda
     * siempre está contenida en la crónica -- una sola pasada por
     * [chronicStart, end] con una suma condicional (CASE WHEN) por fecha
     * basta para obtener los dos acumulados sin cambiar el resultado.
     */
    private function acwr(int $clientId, Carbon $date): ?float
    {
        $acuteStart = $date->copy()->subDays(self::ACUTE_DAYS - 1)->startOfDay();
        $chronicStart = $date->copy()->subDays(self::CHRONIC_DAYS - 1)->startOfDay();
        $end = $date->copy()->endOfDay();

        $sums = ExerciseSessionMetric::where('exercise_session_metrics.client_id', $clientId)
            ->join('workout_session_reviews', 'workout_session_reviews.id', '=', 'exercise_session_metrics.workout_session_review_id')
            ->whereBetween('workout_session_reviews.completed_at', [$chronicStart, $end])
            ->selectRaw(
                'SUM(CASE WHEN workout_session_reviews.completed_at >= ? THEN exercise_session_metrics.carga_efectiva ELSE 0 END) as acute_sum,'
                .' SUM(exercise_session_metrics.carga_efectiva) as chronic_sum',
                [$acuteStart]
            )
            ->first();

        $acute = (float) ($sums->acute_sum ?? 0);
        $chronicSum = (float) ($sums->chronic_sum ?? 0);

        $chronic = $chronicSum / (self::CHRONIC_DAYS / 7);

        if ($chronic <= 0.0) {
            return null; // sin histórico de carga crónica suficiente, ACWR no tiene sentido
        }

        return round($acute / $chronic, 4);
    }

    private function normalizeZ(?float $z): ?float
    {
        if ($z === null) {
            return null;
        }

        return max(0.0, min(100.0, 50.0 + ($z * 20.0)));
    }

    private function normalizeSubjetivo(?float $s): ?float
    {
        if ($s === null) {
            return null;
        }

        return max(0.0, min(100.0, (($s - 1.0) / 4.0) * 100.0));
    }

    private function normalizeAcwr(?float $a): ?float
    {
        if ($a === null) {
            return null;
        }

        if ($a >= 0.8 && $a <= 1.3) {
            return 100.0;
        }

        if ($a < 0.8) {
            return max(0.0, 100.0 - ((0.8 - $a) * 100.0));
        }

        return max(0.0, 100.0 - (($a - 1.3) * 150.0));
    }

    /**
     * Media ponderada de las fuentes disponibles, redistribuyendo los
     * pesos de las que faltan proporcionalmente entre las presentes
     * (documento §4.1 punto 6). Null si no hay ninguna fuente disponible.
     */
    private function combine(array $normalized): ?float
    {
        $weights = config('readiness.weights');
        $available = array_filter($normalized, fn ($v) => $v !== null);

        if (empty($available)) {
            return null;
        }

        $availableWeightSum = array_sum(array_intersect_key($weights, $available));
        if ($availableWeightSum <= 0.0) {
            // fuentes disponibles no tienen peso configurado (config
            // corrupta) -> reparto uniforme como último recurso.
            return round(array_sum($available) / count($available), 4);
        }

        $combined = 0.0;
        foreach ($available as $key => $value) {
            $redistributedWeight = $weights[$key] / $availableWeightSum;
            $combined += $value * $redistributedWeight;
        }

        return round($combined, 4);
    }

    /**
     * Mapea a banda final. Resolución de conflicto (documento §4.1 punto
     * 7): si CUALQUIERA de las dos fuentes principales (objetiva: hrv o
     * sueño con z <= -1.0; o subjetiva: subjetivo_score <= 2/5) está en
     * zona baja, la banda final es "bajo" aunque el promedio ponderado no
     * lo refleje (evita que un buen HRV oculte que el cliente se siente
     * mal, o viceversa).
     */
    private function mapBand(?float $combined, ?float $hrvZ, ?float $suenoZ, ?float $restingHrZ, ?float $subjetivo): string
    {
        if ($combined === null) {
            return 'dato_insuficiente';
        }

        // restingHrZ NO se invierte aquí (a diferencia de normalizeZ() más
        // arriba) -- el criterio es directo sobre el z original: FC en
        // reposo elevada (z >= +1.0 sobre la línea base) es la señal mala,
        // mismo umbral en magnitud que hrv/sueno pero en sentido contrario.
        $objetivaBaja = ($hrvZ !== null && $hrvZ <= self::OBJETIVA_BAJA_Z)
            || ($suenoZ !== null && $suenoZ <= self::OBJETIVA_BAJA_Z)
            || ($restingHrZ !== null && $restingHrZ >= abs(self::OBJETIVA_BAJA_Z));
        $subjetivaBaja = $subjetivo !== null && $subjetivo <= self::SUBJETIVA_BAJA_UMBRAL;

        if ($objetivaBaja || $subjetivaBaja) {
            return 'bajo';
        }

        if ($combined >= 70.0) {
            return 'optimo';
        }

        if ($combined >= 45.0) {
            return 'reducido';
        }

        return 'bajo';
    }
}
