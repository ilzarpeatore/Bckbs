<?php

namespace App\Services;

use App\Enums\ExceptionCategory;
use App\Enums\ExceptionSeverity;
use App\Models\ClientExerciseLog;
use App\Models\CoachExceptionItem;
use App\Models\User;
use App\Models\WorkoutSessionReview;
use App\Notifications\CommonNotification;

/**
 * Avisa al coach cuando un cliente FINALIZA una sesión de su programa sin
 * haber registrado ninguna serie (caso Ayoub, 2026-09-25: 4 sesiones de
 * ~80 min con volumen 0 y cero filas en client_exercise_logs; el panel las
 * pintaba en verde como "hechas" y no había forma de enterarse el mismo día).
 *
 * Deja un ítem en el Panel de Excepciones (category=sesion_sin_registro,
 * idempotente por reseña) y una notificación push. Sin gate de tier, igual que
 * `dolor`: no es una función de pago, es que el coach no se quede a ciegas.
 * Las sesiones que el propio cliente se creó (ClientCustomWorkoutController) se
 * ignoran: no hay plan del coach que comprobar.
 */
class EmptySessionAlertService
{
    public function evaluate(WorkoutSessionReview $review): ?CoachExceptionItem
    {
        // Solo sesiones de un programa asignado: los logs de un workout suelto
        // no cuelgan de program_day_assignment_id y no se pueden comprobar así.
        if (!$review->program_day_assignment_id || $review->isClientCustomSession()) {
            return null;
        }

        if ($this->hasLoggedSets((int) $review->user_id, (int) $review->program_day_assignment_id)) {
            return null;
        }

        $client = User::find($review->user_id);
        $coach = $client ? $this->resolveCoach($client) : null;
        if (!$client || !$coach) {
            return null;
        }

        $title = optional(optional($review->programDayAssignment)->workoutTemplate)->title ?? 'un entrenamiento';
        $minutes = $review->duration_seconds ? (int) round($review->duration_seconds / 60) : null;
        $name = $client->display_name ?: trim("{$client->first_name} {$client->last_name}");

        $item = (new CoachExceptionFeedService())->createOrSkip(
            coachId: $coach->id,
            clientId: $client->id,
            category: ExceptionCategory::SESION_SIN_REGISTRO,
            severity: ExceptionSeverity::MEDIA,
            sourceType: WorkoutSessionReview::class,
            sourceId: $review->id,
            title: "Finalizó \"{$title}\" sin registrar ninguna serie",
            description: 'No hay cargas ni repeticiones guardadas de esta sesión'
                . ($minutes ? " (duración {$minutes} min)" : '')
                . '. El calendario la muestra como completada.'
        );

        if ($item) {
            StaffAlertService::send(
                "Sesión sin registrar — {$name}",
                "{$name} finalizó \"{$title}\" sin registrar ninguna serie"
                    . ($minutes ? " (duración {$minutes} min)" : '') . ".\n\n"
                    . 'Ficha: ' . StaffAlertService::adminUrl("/users/{$client->id}/entrenamiento")
            );
            $coach->notify(new CommonNotification('session_without_logs', [
                'id'      => $item->id,
                'type'    => 'session_without_logs',
                'subject' => 'Sesión sin registrar',
                'message' => "{$name} finalizó \"{$title}\" sin registrar ninguna serie.",
            ]));
        }

        return $item;
    }

    public function hasLoggedSets(int $clientId, int $programDayAssignmentId): bool
    {
        return ClientExerciseLog::where('client_id', $clientId)
            ->where('program_day_assignment_id', $programDayAssignmentId)
            ->whereRaw(self::nonEmptyLoggedSetsSql())
            ->exists();
    }

    /**
     * Condición SQL "este log tiene al menos una serie". JSON_LENGTH en MySQL (producción);
     * json_array_length en sqlite (los tests de PHPUnit), que no tiene JSON_LENGTH.
     */
    public static function nonEmptyLoggedSetsSql(): string
    {
        return \DB::connection()->getDriverName() === 'sqlite'
            ? 'json_array_length(logged_sets) > 0'
            : 'JSON_LENGTH(logged_sets) > 0';
    }

    /**
     * Destinatario del aviso: el coach del cliente si lo tiene; hoy ningún
     * cliente real tiene coach_id, así que si no, un usuario coach activo y,
     * en último término, el admin principal (el ítem aparece igualmente en la
     * campana, el dashboard y la ficha del cliente, que listan sin filtrar por coach).
     */
    public function resolveCoach(User $client): ?User
    {
        if ($client->coach_id && ($coach = User::find($client->coach_id))) {
            return $coach;
        }

        return User::where('user_type', 'coach')->where('status', 'active')->orderBy('id')->first()
            ?? User::where('user_type', 'admin')->where('status', 'active')->orderBy('id')->first();
    }
}
