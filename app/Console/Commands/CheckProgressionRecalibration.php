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
 */
class CheckProgressionRecalibration extends Command
{
    protected $signature = 'progression:check-recalibration';

    protected $description = 'Detecta 4+ ediciones consecutivas del coach en la misma dirección sobre una regla y sugiere recalibrarla';

    private const MIN_CONSECUTIVE_EDITS = 4;
    private const MIN_DEVIATION_RATIO = 0.02; // >2%

    public function handle(): int
    {
        $groups = OverrideLog::select('rule_id', 'client_id')
            ->whereNotNull('rule_id')
            ->distinct()
            ->get();

        $notified = 0;

        foreach ($groups as $group) {
            $lastLogs = OverrideLog::where('rule_id', $group->rule_id)
                ->where('client_id', $group->client_id)
                ->orderByDesc('created_at')
                ->limit(self::MIN_CONSECUTIVE_EDITS)
                ->get();

            if ($lastLogs->count() < self::MIN_CONSECUTIVE_EDITS) {
                continue;
            }

            if ($lastLogs->contains(fn (OverrideLog $log) => $log->action_taken !== ActionTaken::EDITED)) {
                continue; // secuencia rota por un accepted/rejected intercalado.
            }

            $directions = $lastLogs->map(function (OverrideLog $log) {
                if ((float) $log->suggested_value == 0.0) {
                    return null;
                }
                $ratio = ($log->applied_value - $log->suggested_value) / $log->suggested_value;
                if (abs($ratio) <= self::MIN_DEVIATION_RATIO) {
                    return null; // desviación insuficiente para contar como "edición consistente".
                }

                return $ratio > 0 ? 'up' : 'down';
            });

            if ($directions->contains(null) || $directions->unique()->count() !== 1) {
                continue; // no todas van en la misma dirección con desviación >2%.
            }

            // No re-notificar por el mismo lote de 4 ediciones en corridas sucesivas.
            $cacheKey = 'spr_recalib_notified_'.$group->rule_id.'_'.$group->client_id.'_'.$lastLogs->first()->id;
            if (Cache::has($cacheKey)) {
                continue;
            }
            Cache::put($cacheKey, true, now()->addDays(30));

            $this->notifyCoach($group->rule_id, $group->client_id, $directions->first());
            $notified++;
        }

        $this->info("Recalibración evaluada: {$notified} aviso(s) generado(s).");

        return self::SUCCESS;
    }

    private function notifyCoach(int $ruleId, int $clientId, string $direction): void
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

        $coach->notify(new CommonNotification('progression_recalibration', [
            'id'      => $rule->id,
            'type'    => 'progression_recalibration',
            'subject' => 'Sugerencia de recalibrar una regla',
            'message' => "Has editado {$directionLabel} las últimas 4 sugerencias de la regla \"{$rule->name}\" para "
                .optional($client)->display_name.'. Puede que el parámetro base de la regla ya no encaje — considera ajustarlo.',
        ]));
    }
}
