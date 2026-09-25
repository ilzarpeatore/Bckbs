<?php

namespace App\Console\Commands;

use App\Enums\ExceptionCategory;
use App\Enums\ExceptionSeverity;
use App\Models\User;
use App\Models\WorkoutSessionReview;
use App\Services\CoachExceptionFeedService;
use App\Services\EmptySessionAlertService;
use App\Services\StaffAlertService;
use Illuminate\Console\Command;

/**
 * Auditoría diaria de clientes que finalizan sesiones SIN registrar series
 * (caso Ayoub, 2026-09-25). Complementa el aviso puntual de
 * EmptySessionAlertService (uno por sesión) con un ítem "vivo" por cliente
 * cuando el patrón se repite: 2+ sesiones de programa finalizadas sin ningún
 * log de series en los últimos N días.
 *
 *   php artisan sessions:audit-empty [--days=14] [--min=2] [--dry-run]
 *
 * - Crea/actualiza UN ítem por cliente (categoría patron_sesiones_sin_registro,
 *   severidad alta a partir de 3 sesiones) y manda un correo al equipo solo la
 *   primera vez que aparece (StaffAlertService: no envía si no hay SMTP).
 * - Cuando el cliente deja de cumplir el patrón, resuelve el ítem solo.
 * - Con --dry-run solo lista, no escribe nada.
 * - Ignora sesiones creadas por el propio cliente y workouts sueltos, igual que
 *   el aviso puntual. Termina siempre con código 0: es un aviso, no una violación.
 */
class AuditEmptySessionsCommand extends Command
{
    protected $signature = 'sessions:audit-empty {--days=14 : ventana en días} {--min=2 : sesiones sin series para avisar} {--dry-run : no escribe nada}';

    protected $description = 'Avisa (Panel de Excepciones + correo) de clientes con varias sesiones finalizadas sin registrar ninguna serie';

    public function handle(EmptySessionAlertService $alerts, CoachExceptionFeedService $feed): int
    {
        $days = max(1, (int) $this->option('days'));
        $min = max(1, (int) $this->option('min'));
        $dry = (bool) $this->option('dry-run');

        $reviews = WorkoutSessionReview::whereNotNull('program_day_assignment_id')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subDays($days))
            ->with('programDayAssignment.workoutTemplate')
            ->orderBy('completed_at')
            ->get();

        $emptyByClient = [];
        foreach ($reviews as $review) {
            if ($review->isClientCustomSession()) {
                continue;
            }
            if (!$alerts->hasLoggedSets((int) $review->user_id, (int) $review->program_day_assignment_id)) {
                $emptyByClient[$review->user_id][] = $review;
            }
        }

        $flagged = [];
        foreach ($emptyByClient as $clientId => $empty) {
            if (count($empty) < $min) {
                continue;
            }
            $flagged[] = $clientId;
            $client = User::find($clientId);
            $coach = $client ? $alerts->resolveCoach($client) : null;
            $name = $client ? ($client->display_name ?: trim("{$client->first_name} {$client->last_name}")) : "cliente {$clientId}";
            $n = count($empty);
            $dates = collect($empty)->map(fn ($r) => $r->completed_at->format('d/m'))->implode(', ');

            $this->line("{$name}: {$n} sesión(es) sin series en {$days} días ({$dates})");
            if ($dry || !$client || !$coach) {
                continue;
            }

            $title = "{$n} sesiones finalizadas sin registrar series en {$days} días";
            $item = $feed->upsertForClientCategory(
                coachId: $coach->id,
                clientId: $client->id,
                category: ExceptionCategory::PATRON_SESIONES_SIN_REGISTRO,
                severity: $n >= 3 ? ExceptionSeverity::ALTA : ExceptionSeverity::MEDIA,
                sourceType: null,
                sourceId: null,
                title: $title,
                description: "Sesiones sin ninguna serie guardada: {$dates}. Conviene hablar con el cliente: sin cargas y repeticiones no hay progresión ni estadísticas."
            );

            // Solo la primera vez que aparece (upsert actualiza en sitio los días siguientes).
            if ($item->wasRecentlyCreated) {
                StaffAlertService::send(
                    "Patrón: {$name} no registra series",
                    "{$name} ha finalizado {$n} sesiones sin registrar ninguna serie en los últimos {$days} días ({$dates}).\n\nFicha: "
                        . StaffAlertService::adminUrl("/users/{$client->id}/entrenamiento")
                );
            }
        }

        // Clientes que ya no cumplen el patrón: se resuelve el ítem abierto.
        if (!$dry) {
            $open = \App\Models\CoachExceptionItem::where('category', ExceptionCategory::PATRON_SESIONES_SIN_REGISTRO->value)
                ->where('status', 'pendiente')
                ->pluck('client_id')
                ->unique();
            foreach ($open as $clientId) {
                if (!in_array($clientId, $flagged, false)) {
                    $feed->autoResolveByClientCategory((int) $clientId, ExceptionCategory::PATRON_SESIONES_SIN_REGISTRO);
                    $this->line("Cliente {$clientId}: patrón resuelto.");
                }
            }
        }

        $this->info('Clientes con patrón: ' . count($flagged) . ($dry ? ' (dry-run, sin escribir)' : ''));

        return self::SUCCESS;
    }
}
