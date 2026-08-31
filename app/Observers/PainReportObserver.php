<?php

namespace App\Observers;

use App\Enums\ExceptionCategory;
use App\Enums\ExceptionSeverity;
use App\Models\PainReport;
use App\Models\User;
use App\Notifications\CommonNotification;
use App\Services\CoachExceptionFeedService;

/**
 * Notificación inmediata al coach (documento §1.3): al insertar un
 * pain_report con intensidad >= 4 o tipo != molestia_leve, avisar al coach
 * ya, sin esperar al job de interpretación (que puede tardar si la cola no
 * es síncrona) ni al patrón de detección recurrente (que corre aparte,
 * ver comando check:pain-patterns).
 *
 * Mismo patrón exacto que ClientExerciseLogObserver::notifyNewRecord():
 * resolver el destinatario y notify(new CommonNotification(...)), sin
 * gate de tier (el bloqueo por dolor nunca se gatea, documento §1.3 /
 * plan aprobado Fase 1).
 *
 * AÑADIDO — Panel de Excepciones del Coach: mismo punto, crea también el
 * ítem persistente (category=dolor, severity=alta SIEMPRE) — sin gate de
 * tier, deliberadamente, ver docblock de CoachExceptionFeedService.
 */
class PainReportObserver
{
    public function created(PainReport $report): void
    {
        if (!$report->blocksProgression()) {
            return;
        }

        $client = User::find($report->client_id);
        if (!$client || !$client->coach_id) {
            return;
        }

        $coach = User::find($client->coach_id);
        if (!$coach) {
            return;
        }

        $exerciseTitle = optional($report->exercise)->title ?? 'un ejercicio';

        $coach->notify(new CommonNotification('pain_alert', [
            'id'      => $report->id,
            'type'    => 'pain_alert',
            'subject' => 'Alerta de dolor de un cliente',
            'message' => "{$client->display_name} reportó dolor ({$report->tipo}, intensidad {$report->intensidad}/5) en \"{$exerciseTitle}\".",
        ]));

        (new CoachExceptionFeedService())->createOrSkip(
            coachId: $coach->id,
            clientId: $client->id,
            category: ExceptionCategory::DOLOR,
            severity: ExceptionSeverity::ALTA,
            sourceType: PainReport::class,
            sourceId: $report->id,
            title: "Dolor reportado en \"{$exerciseTitle}\"",
            description: "{$report->tipo}, intensidad {$report->intensidad}/5, {$report->localizacion} ({$report->momento})."
        );
    }
}
