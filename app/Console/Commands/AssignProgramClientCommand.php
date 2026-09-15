<?php

namespace App\Console\Commands;

use App\Models\ProgramClientAssignment;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Notifications\CommonNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Asigna un training_program (plantilla) a un cliente concreto, para que le
 * aparezca en su calendario -- el paso que faltaba tras programs:import,
 * hasta ahora resuelto "a mano" con un script puntual (ver
 * docs/AGENTE_IMPORTADOR.md, sección 7, punto 3).
 *
 * Reutiliza exactamente la misma lógica que ya usa el panel
 * (TrainingProgramController::assignClient(), ruta HTTP
 * POST training-program-assign-client) -- misma semántica de renovación,
 * mismo cálculo de fecha_fin, misma notificación al cliente -- pero
 * accesible por SSH sin necesitar un token de coach vía Sanctum, que es
 * como opera hoy el agente importador.
 *
 *   php artisan programs:assign-client 48 cliente@ejemplo.com
 *   php artisan programs:assign-client 48 cliente@ejemplo.com --start-date=2026-09-22
 *   php artisan programs:assign-client 48 cliente@ejemplo.com --json
 */
class AssignProgramClientCommand extends Command
{
    protected $signature = 'programs:assign-client
        {training_program_id : ID del training_program a asignar}
        {client_email : Email del cliente (debe pertenecer al coach de --coach-id)}
        {--start-date= : Fecha de inicio, YYYY-MM-DD (por defecto: hoy)}
        {--coach-id=1 : Coach dueño del programa y del cliente}
        {--json : Salida JSON estructurada por stdout en vez de texto para humano}';

    protected $description = 'Asigna un programa de entrenamiento ya importado a un cliente concreto';

    public function handle(): int
    {
        $jsonOutput = (bool) $this->option('json');
        $coachId = (int) $this->option('coach-id');
        $programId = (int) $this->argument('training_program_id');
        $email = (string) $this->argument('client_email');

        $program = TrainingProgram::where('coach_id', $coachId)->find($programId);
        if ($program === null) {
            return $this->reportFailure($jsonOutput, "Training program #{$programId} no encontrado para coach_id={$coachId}.");
        }

        $client = User::where('email', $email)->where('coach_id', $coachId)->first();
        if ($client === null) {
            return $this->reportFailure($jsonOutput, "Cliente con email {$email} no encontrado, o no pertenece a coach_id={$coachId}.");
        }

        $startDateOpt = $this->option('start-date');
        try {
            $startDate = $startDateOpt !== null ? Carbon::parse($startDateOpt) : Carbon::today();
        } catch (\Throwable $e) {
            return $this->reportFailure($jsonOutput, "Fecha de inicio inválida: {$startDateOpt}");
        }

        $fechaFin = ProgramClientAssignment::computeFechaFin($startDate, $program->num_weeks);

        $existing = ProgramClientAssignment::where('training_program_id', $programId)
            ->where('client_id', $client->id)
            ->first();

        // Misma semántica que TrainingProgramController::assignClient(): si
        // ya existía, es una renovación (nuevo ciclo del mesociclo) -- no
        // una fila duplicada -- así que se actualiza la misma, reabriendo
        // cerrado_at si estaba cerrada.
        if ($existing !== null) {
            $existing->update([
                'start_date' => $startDate->toDateString(),
                'fecha_fin'  => $fechaFin->toDateString(),
                'activo'     => true,
                'cerrado_at' => null,
            ]);
            $assignment = $existing;
            $renewed = true;
        } else {
            $assignment = ProgramClientAssignment::create([
                'training_program_id' => $programId,
                'client_id'           => $client->id,
                'start_date'          => $startDate->toDateString(),
                'fecha_fin'           => $fechaFin->toDateString(),
                'activo'              => true,
            ]);
            $renewed = false;
        }

        $client->notify(new CommonNotification('new_training_program', [
            'id'      => $program->id,
            'type'    => 'new_training_program',
            'subject' => 'Nuevo programa de entrenamiento',
            'message' => "Tu coach te ha asignado el programa \"{$program->title}\".",
        ]));

        if ($jsonOutput) {
            $this->line(json_encode([
                'ok'                    => true,
                'renewed'               => $renewed,
                'assignment_id'         => $assignment->id,
                'training_program_id'   => $programId,
                'client_id'             => $client->id,
                'client_email'          => $client->email,
                'start_date'            => $assignment->start_date->toDateString(),
                'fecha_fin'             => $assignment->fecha_fin->toDateString(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info(($renewed ? 'Asignación renovada' : 'Cliente asignado') . ": programa #{$programId} → {$client->email} ({$assignment->start_date->toDateString()} – {$assignment->fecha_fin->toDateString()})");

        return self::SUCCESS;
    }

    private function reportFailure(bool $jsonOutput, string $message): int
    {
        if ($jsonOutput) {
            $this->line(json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }
}
