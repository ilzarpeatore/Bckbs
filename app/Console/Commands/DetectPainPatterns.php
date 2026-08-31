<?php

namespace App\Console\Commands;

use App\Enums\ExceptionCategory;
use App\Enums\ExceptionSeverity;
use App\Models\ClientExerciseFlag;
use App\Models\PainReport;
use App\Models\User;
use App\Notifications\CommonNotification;
use App\Services\CoachExceptionFeedService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Detección de patrón recurrente de dolor (documento §1.3) — job separado,
 * no crítico en tiempo real (a diferencia de PainReportObserver, que ya
 * avisa al coach de forma inmediata en el mismo momento del reporte).
 * Corre diario (ver Kernel::schedule). Mismo exercise_id + localización con
 * pain_report en 2+ sesiones distintas de los últimos 30 días -> marca
 * pain_pattern_flag=true en client_exercise_flags y notifica al coach.
 */
class DetectPainPatterns extends Command
{
    protected $signature = 'check:pain-patterns';

    protected $description = 'Detecta patrones recurrentes de dolor (mismo ejercicio+localización, 2+ sesiones en 30 días) y avisa al coach';

    public function handle(): int
    {
        $since = now()->subDays(30);

        $groups = PainReport::where('created_at', '>=', $since)
            ->select('client_id', 'exercise_id', 'localizacion')
            ->selectRaw('COUNT(DISTINCT DATE(created_at)) as distinct_days')
            ->groupBy('client_id', 'exercise_id', 'localizacion')
            ->having('distinct_days', '>=', 2)
            ->get();

        foreach ($groups as $group) {
            $flag = ClientExerciseFlag::firstOrCreate(
                [
                    'client_id'    => $group->client_id,
                    'exercise_id'  => $group->exercise_id,
                    'localizacion' => $group->localizacion,
                ],
                ['pain_pattern_flag' => false]
            );

            if ($flag->pain_pattern_flag) {
                continue; // ya notificado, no repetir a diario
            }

            $flag->pain_pattern_flag = true;
            $flag->flagged_at = now();
            $flag->save();

            $client = User::find($group->client_id);
            if (!$client || !$client->coach_id) {
                continue;
            }

            $coach = User::find($client->coach_id);
            if (!$coach) {
                continue;
            }

            $exerciseTitle = optional($flag->exercise)->title ?? 'un ejercicio';

            $coach->notify(new CommonNotification('pain_pattern', [
                'id'      => $flag->id,
                'type'    => 'pain_pattern',
                'subject' => 'Patrón de dolor recurrente',
                'message' => "{$client->display_name} ha reportado dolor en \"{$exerciseTitle}\" ({$group->localizacion}) en varias sesiones de los últimos 30 días.",
            ]));

            // Panel de Excepciones del Coach (documento §3.1) — ítem
            // distinto del de dolor puntual (PainReportObserver), no lo
            // sustituye. Origen real de este ítem = la transición del
            // propio flag (ya idempotente por el "continue" de arriba, que
            // solo deja pasar una vez por flag). severity=alta siempre,
            // igual que el dolor puntual, sin gate de tier.
            (new CoachExceptionFeedService())->createOrSkip(
                coachId: $coach->id,
                clientId: $client->id,
                category: ExceptionCategory::DOLOR,
                severity: ExceptionSeverity::ALTA,
                sourceType: ClientExerciseFlag::class,
                sourceId: $flag->id,
                title: "Patrón de dolor recurrente en \"{$exerciseTitle}\"",
                description: "{$group->localizacion}, reportado en varias sesiones de los últimos 30 días."
            );
        }

        $this->info("Patrones de dolor evaluados: {$groups->count()} grupo(s) con 2+ días distintos.");

        return self::SUCCESS;
    }
}
