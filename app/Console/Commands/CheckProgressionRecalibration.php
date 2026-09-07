<?php

namespace App\Console\Commands;

use App\Enums\ActionTaken;
use App\Models\OverrideLog;
use App\Models\SessionProgressionRule;
use App\Models\User;
use App\Notifications\CommonNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Motor de Auto-Regulación de Carga — Fase 2 (documento §2.3): "Job de
 * calibración automática (puede ser semanal, no crítico): revisar
 * override_logs agrupados por rule_id+client_id; si hay 4+ ediciones
 * consecutivas en la misma dirección con desviación similar (>2% de
 * diferencia consistente), generar notificación al coach sugiriendo
 * ajustar el parámetro base de la regla."
 *
 * "Consecutivas" se interpreta de forma estricta: las últimas 4 filas de
 * override_logs para ese rule_id+client_id (sin importar el tipo de
 * override_log que hubiera antes) deben ser TODAS action_taken=edited —
 * si alguna de las últimas 4 fue aceptada o rechazada tal cual, la
 * secuencia de ediciones se considera rota y no cuenta.
 *
 * Extensiones sobre el diseño original (docs/Motor_Autorregulacion_Analisis.md,
 * Plan de Optimización, Ronda 5, ítems 8/17/18/19):
 *   - Ítem 8: resuelto el N+1 de una query por par (rule_id, client_id) —
 *     ahora se trae todo el histórico relevante en una sola query y se
 *     agrupa en PHP.
 *   - Ítem 17: el aviso al coach ahora incluye la desviación media de las 4
 *     ediciones (no solo la dirección), como sugerencia concreta de ajuste.
 *   - Ítem 18: además de dirección consistente, se exige consistencia de
 *     MAGNITUD (rango acotado respecto a la media) y una ventana temporal
 *     máxima entre la 1ª y la 4ª edición.
 *   - Ítem 19: agregación adicional a nivel de regla — si 3+ clientes
 *     distintos de la misma regla muestran el patrón en la misma dirección
 *     en esta misma ejecución, se envía un aviso adicional de revisión
 *     global de la regla (no sustituye a los avisos individuales).
 */
class CheckProgressionRecalibration extends Command
{
    protected $signature = 'progression:check-recalibration';

    protected $description = 'Detecta 4+ ediciones consecutivas del coach en la misma dirección sobre una regla y sugiere recalibrarla';

    private const MIN_CONSECUTIVE_EDITS = 4;
    private const MIN_DEVIATION_RATIO = 0.02; // >2%

    /**
     * Ítem 18 — consistencia de magnitud: +3%,+15%,+4%,+20% son todas "up" y
     * todas >2%, pero no describen una desviación sistemática fiable (el
     * contexto del cliente varía demasiado entre ediciones). Se exige que el
     * RANGO (max-min) de los 4 ratios no supere este porcentaje de |su
     * media| — un rango dentro del 50% de la media indica que las 4
     * ediciones son razonablemente parecidas en magnitud, no solo en
     * dirección. Umbral elegido por criterio propio (no viene del
     * "documento" de diseño original), documentado aquí.
     */
    private const MAX_MAGNITUDE_RANGE_RATIO = 0.5;

    /**
     * Ítem 18 — ventana temporal máxima entre la edición más antigua y la
     * más reciente de las 4 consideradas. 4 ediciones dispersas en varios
     * meses pueden reflejar que el contexto del cliente cambió entre medias
     * (fase de entrenamiento distinta), no que la regla esté mal calibrada.
     * 60 días cubre holgadamente varias corridas de este comando (semanal)
     * sin ser tan laxo como para aceptar ediciones de hace medio año.
     * Umbral elegido por criterio propio, documentado aquí.
     */
    private const MAX_EDIT_WINDOW_DAYS = 60;

    /**
     * Ítem 19 — nº mínimo de clientes DISTINTOS de una misma regla que deben
     * mostrar el patrón de recalibración en la misma dirección, en esta
     * misma ejecución, para considerar que el problema es de la regla en sí
     * y no solo de un cliente concreto.
     */
    private const MIN_CLIENTS_FOR_RULE_LEVEL_ALERT = 3;

    public function handle(): int
    {
        // Ítem 8: antes se hacía `OverrideLog::select('rule_id','client_id')
        // ->distinct()->get()` y luego, DENTRO del loop, una query adicional
        // por cada par distinto (N+1 clásico). Ahora se trae en una sola
        // query todo el histórico relevante (ya ordenado desc por
        // created_at) y se agrupa en PHP — Collection::groupBy() conserva el
        // orden original dentro de cada grupo, así que ->take(4) sobre cada
        // grupo equivale exactamente a orderByDesc('created_at')->limit(4)
        // de antes. No se acota por fecha para garantizar un resultado
        // funcional idéntico al actual (una tabla que crezca mucho es
        // candidata a un recorte por fecha en el futuro, pero cambiaría qué
        // ediciones se consideran "las últimas 4").
        $logs = OverrideLog::whereNotNull('rule_id')
            ->orderByDesc('created_at')
            ->get(['id', 'rule_id', 'client_id', 'action_taken', 'suggested_value', 'applied_value', 'created_at']);

        $groups = $logs->groupBy(fn (OverrideLog $log) => $log->rule_id.'|'.$log->client_id);

        $notified = 0;
        // Ítem 19: rule_id => direction => [client_id, ...] detectados en
        // esta misma ejecución del comando.
        $ruleLevelHits = [];

        foreach ($groups as $groupedLogs) {
            $ruleId = $groupedLogs->first()->rule_id;
            $clientId = $groupedLogs->first()->client_id;

            $lastLogs = $groupedLogs->take(self::MIN_CONSECUTIVE_EDITS);

            if ($lastLogs->count() < self::MIN_CONSECUTIVE_EDITS) {
                continue;
            }

            if ($lastLogs->contains(fn (OverrideLog $log) => $log->action_taken !== ActionTaken::EDITED)) {
                continue; // secuencia rota por un accepted/rejected intercalado.
            }

            $ratios = $lastLogs->map(function (OverrideLog $log) {
                if ((float) $log->suggested_value == 0.0) {
                    return null;
                }

                return ($log->applied_value - $log->suggested_value) / $log->suggested_value;
            });

            if ($ratios->contains(null)) {
                continue; // suggested_value=0 en alguna edición, ratio indefinido.
            }

            $directions = $ratios->map(function (float $ratio) {
                if (abs($ratio) <= self::MIN_DEVIATION_RATIO) {
                    return null; // desviación insuficiente para contar como "edición consistente".
                }

                return $ratio > 0 ? 'up' : 'down';
            });

            if ($directions->contains(null) || $directions->unique()->count() !== 1) {
                continue; // no todas van en la misma dirección con desviación >2%.
            }

            $direction = $directions->first();

            // Ítem 18a: consistencia de magnitud (ver constante MAX_MAGNITUDE_RANGE_RATIO).
            $meanRatio = $ratios->avg();
            $range = $ratios->max() - $ratios->min();
            if (abs($meanRatio) > 0.0 && $range > self::MAX_MAGNITUDE_RANGE_RATIO * abs($meanRatio)) {
                continue; // magnitud errática -- no hay un patrón fiable de "desviado en X%".
            }

            // Ítem 18b: ventana temporal máxima (ver constante MAX_EDIT_WINDOW_DAYS).
            // $lastLogs está ordenado desc: first() = edición más reciente, last() = más antigua de las 4.
            $oldestEditAt = $lastLogs->last()->created_at;
            $newestEditAt = $lastLogs->first()->created_at;
            if ($oldestEditAt->diffInDays($newestEditAt) > self::MAX_EDIT_WINDOW_DAYS) {
                continue; // demasiado dispersas en el tiempo, el contexto del cliente pudo cambiar entre medias.
            }

            // No re-notificar por el mismo lote de 4 ediciones en corridas sucesivas.
            $cacheKey = 'spr_recalib_notified_'.$ruleId.'_'.$clientId.'_'.$lastLogs->first()->id;
            if (Cache::has($cacheKey)) {
                continue;
            }
            Cache::put($cacheKey, true, now()->addDays(30));

            // Ítem 17: media de las 4 desviaciones, para sugerir un ajuste concreto.
            $meanDeviationPct = round(abs($meanRatio) * 100, 1);

            $this->notifyCoach($ruleId, $clientId, $direction, $meanDeviationPct);
            $notified++;

            $ruleLevelHits[$ruleId][$direction][] = $clientId;
        }

        // Ítem 19: si 3+ clientes DISTINTOS de la MISMA regla muestran el
        // patrón en la MISMA dirección en esta ejecución, es más probable
        // que el problema sea el parámetro base de la regla y no solo el
        // ajuste para un cliente concreto. Se envía como aviso ADICIONAL a
        // los individuales (no los sustituye): el coach sigue viendo el
        // detalle por cliente en el panel de excepciones, y además recibe
        // una señal de que conviene revisar la regla globalmente.
        $ruleLevelNotified = $this->notifyRuleLevelPatterns($ruleLevelHits);

        $this->info("Recalibración evaluada: {$notified} aviso(s) individual(es), {$ruleLevelNotified} aviso(s) a nivel de regla.");

        return self::SUCCESS;
    }

    private function notifyCoach(int $ruleId, int $clientId, string $direction, float $meanDeviationPct): void
    {
        $rule = SessionProgressionRule::find($ruleId);
        if (!$rule) {
            return;
        }

        $coach = User::find($rule->coach_id);
        if (!$coach) {
            return;
        }

        $client = User::find($clientId);
        $directionLabel = $direction === 'up' ? 'al alza' : 'a la baja';
        // Ítem 17: dejar explícito el sentido del ajuste sugerido, no solo la
        // magnitud — "up" implica subir el parámetro base, "down" bajarlo.
        $adjustmentVerb = $direction === 'up' ? 'subir' : 'bajar';

        $coach->notify(new CommonNotification('progression_recalibration', [
            'id'      => $rule->id,
            'type'    => 'progression_recalibration',
            'subject' => 'Sugerencia de recalibrar una regla',
            'message' => "Has editado {$directionLabel} las últimas 4 sugerencias de la regla \"{$rule->name}\" para "
                .optional($client)->display_name
                ." con una desviación media del {$meanDeviationPct}% — considera {$adjustmentVerb} el parámetro base de la regla en esa proporción.",
        ]));
    }

    /**
     * Ítem 19 — agrega los aciertos de recalibración de esta ejecución por
     * (rule_id, direction) y notifica al coach cuando 3+ clientes distintos
     * de la misma regla coinciden en la misma dirección.
     *
     * @param  array<int, array<string, array<int, int>>>  $ruleLevelHits  rule_id => direction => [client_id, ...]
     */
    private function notifyRuleLevelPatterns(array $ruleLevelHits): int
    {
        $notified = 0;

        foreach ($ruleLevelHits as $ruleId => $byDirection) {
            foreach ($byDirection as $direction => $clientIds) {
                $distinctClientIds = array_values(array_unique($clientIds));
                if (count($distinctClientIds) < self::MIN_CLIENTS_FOR_RULE_LEVEL_ALERT) {
                    continue;
                }

                sort($distinctClientIds);

                // Dedup por 30 días sobre el mismo conjunto exacto de
                // clientes+dirección para esa regla (mismo criterio de
                // ventana que el dedup individual) — si semana tras semana
                // siguen siendo los mismos 3+ clientes, no se repite el
                // aviso hasta que cambie el conjunto o pase el mes.
                $cacheKey = 'spr_recalib_rule_level_'.$ruleId.'_'.$direction.'_'.md5(implode(',', $distinctClientIds));
                if (Cache::has($cacheKey)) {
                    continue;
                }
                Cache::put($cacheKey, true, now()->addDays(30));

                $this->notifyCoachRuleLevel((int) $ruleId, (string) $direction, count($distinctClientIds));
                $notified++;
            }
        }

        return $notified;
    }

    private function notifyCoachRuleLevel(int $ruleId, string $direction, int $clientCount): void
    {
        $rule = SessionProgressionRule::find($ruleId);
        if (!$rule) {
            return;
        }

        $coach = User::find($rule->coach_id);
        if (!$coach) {
            return;
        }

        $directionLabel = $direction === 'up' ? 'al alza' : 'a la baja';

        $coach->notify(new CommonNotification('progression_recalibration_rule', [
            'id'      => $rule->id,
            'type'    => 'progression_recalibration_rule',
            'subject' => 'Una regla podría necesitar revisión global',
            'message' => "La regla \"{$rule->name}\" muestra un patrón de recalibración {$directionLabel} en {$clientCount} clientes distintos en esta misma ejecución. Es probable que el parámetro base de la regla necesite una revisión global, no solo ajustes por cliente concreto.",
        ]));
    }
}
