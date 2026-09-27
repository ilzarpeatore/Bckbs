<?php

namespace App\Services;

use App\Enums\ExceptionCategory;
use App\Enums\ExceptionSeverity;
use App\Models\CoachExceptionItem;
use App\Models\Task;
use App\Models\User;
use App\Notifications\CommonNotification;

/**
 * Avisa al coach cuando se crea una tarea de alta prioridad (docs/TAREAS_PENDIENTES.md
 * de AgenticdesignBS, ítem 2.21): hasta ahora una tarea creada por el Agente de
 * Soporte / Customer Success o el Agente de Onboarding vía POST task-store se
 * quedaba en el panel hasta que el coach la abría por su cuenta -- sin ningún
 * aviso activo, a diferencia de otras excepciones del Motor (mismo patrón que
 * EmptySessionAlertService: correo + item en el Panel de Excepciones + push).
 *
 * Alcance decidido por el usuario (2026-09-27): se avisa en TODA tarea
 * priority=high con client_id, sin distinguir origen (agente vs. panel admin)
 * ni categoría de la tarea -- hoy no hay ningún campo que distinga "esto lo
 * creó el agente" de "esto lo creó el coach a mano" desde el panel, y el
 * volumen real (una única cuenta de coach) no justifica construir esa
 * distinción todavía.
 */
class TaskEscalationAlertService
{
    public function evaluate(Task $task): ?CoachExceptionItem
    {
        if ($task->priority !== 'high' || $task->client_id === null) {
            return null;
        }

        $client = User::find($task->client_id);
        $coach = $client ? $this->resolveCoach($client) : null;
        if (!$client || !$coach) {
            return null;
        }

        $item = (new CoachExceptionFeedService())->createOrSkip(
            coachId: $coach->id,
            clientId: $client->id,
            category: ExceptionCategory::TAREA_ESCALADA_AGENTE,
            severity: ExceptionSeverity::ALTA,
            sourceType: Task::class,
            sourceId: $task->id,
            title: $task->title,
            description: $task->description
        );

        if ($item) {
            $name = $client->display_name ?: trim("{$client->first_name} {$client->last_name}");

            StaffAlertService::send(
                "Tarea urgente — {$name}",
                $task->title
                    . ($task->description ? "\n\n{$task->description}" : '')
                    . "\n\nFicha: " . StaffAlertService::adminUrl("/users/{$client->id}")
            );
            $coach->notify(new CommonNotification('task_escalated', [
                'id'      => $item->id,
                'type'    => 'task_escalated',
                'subject' => 'Tarea urgente',
                'message' => $task->title,
            ]));
        }

        return $item;
    }

    /** Mismo criterio de resolución que EmptySessionAlertService::resolveCoach(). */
    private function resolveCoach(User $client): ?User
    {
        if ($client->coach_id && ($coach = User::find($client->coach_id))) {
            return $coach;
        }

        return User::where('user_type', 'coach')->where('status', 'active')->orderBy('id')->first()
            ?? User::where('user_type', 'admin')->where('status', 'active')->orderBy('id')->first();
    }
}
