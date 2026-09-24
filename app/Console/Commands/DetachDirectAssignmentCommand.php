<?php

namespace App\Console\Commands;

use App\Models\ProgramClientAssignment;
use App\Models\TrainingProgram;
use App\Services\TemplateIsolationGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Convierte una asignación DIRECTA (legacy) de un programa de la biblioteca a
 * un único cliente en lo que debe ser: el programa pasa a ser la COPIA de ese
 * cliente, y la biblioteca recupera una copia limpia e independiente.
 *
 * Es un "intercambio de papeles" para no tener que remapear historial:
 *   - El training_program original (con sus program_day_assignments, plantillas,
 *     overrides, logs y reseñas del cliente) se queda como programa del cliente
 *     (client_id, source='client_import') -> nada de su historial se mueve.
 *   - Se crea un NUEVO programa de biblioteca (cloneWithStructure) con plantillas
 *     propias, que conserva source/source_id del import (para que reimportar el
 *     mismo Excel siga detectándose como ya importado).
 *   - Las copias que ya se hicieron desde el original (source_id = id) pasan a
 *     apuntar a la biblioteca nueva.
 *
 *   php artisan programs:detach-direct-assignment 48 --dry-run
 *   php artisan programs:detach-direct-assignment 48
 *
 * Se niega si el programa está asignado a más de un cliente (habría que decidir a
 * mano a cuál pertenece) o si ya es de un cliente.
 */
class DetachDirectAssignmentCommand extends Command
{
    protected $signature = 'programs:detach-direct-assignment
        {program_id : Programa de biblioteca asignado directamente}
        {--dry-run : Solo muestra qué haría}';

    protected $description = 'Convierte una asignación directa (legacy) de un programa de biblioteca en la copia propia del cliente, sin mover su historial';

    public function handle(): int
    {
        $program = TrainingProgram::find((int) $this->argument('program_id'));
        if ($program === null) {
            $this->error('Programa no encontrado.');

            return self::FAILURE;
        }

        if (TemplateIsolationGuard::ownerKey($program) !== TemplateIsolationGuard::LIBRARY) {
            $this->error("El programa #{$program->id} ya pertenece a un cliente; no hay nada que separar.");

            return self::FAILURE;
        }

        $clientIds = ProgramClientAssignment::where('training_program_id', $program->id)->pluck('client_id')->unique()->values();
        if ($clientIds->isEmpty()) {
            $this->info("El programa #{$program->id} no está asignado a ningún cliente: nada que hacer.");

            return self::SUCCESS;
        }
        if ($clientIds->count() > 1) {
            $this->error("El programa #{$program->id} está asignado a {$clientIds->count()} clientes ({$clientIds->implode(', ')}): hay que decidir a mano a cuál pertenece.");

            return self::FAILURE;
        }

        $clientId = (int) $clientIds->first();
        $copiesFromIt = TrainingProgram::where('source', 'client_import')->where('source_id', (string) $program->id)->where('id', '!=', $program->id)->get();
        $days = $program->dayAssignments()->count();

        $this->line("Programa #{$program->id} \"{$program->title}\" (source={$program->source}) asignado directamente al cliente #{$clientId} ({$days} días).");
        $this->line("  1) Se crea una copia NUEVA de biblioteca con plantillas propias (conserva source/source_id del import).");
        $this->line("  2) El programa #{$program->id} pasa a ser la copia del cliente #{$clientId} (su historial no se mueve).");
        $this->line("  3) {$copiesFromIt->count()} copia(s) previas de este programa pasan a apuntar a la biblioteca nueva.");

        if ($this->option('dry-run')) {
            $this->info('Dry-run: no se ha modificado nada.');

            return self::SUCCESS;
        }

        $result = DB::transaction(function () use ($program, $clientId) {
            $library = $program->cloneWithStructure([
                'client_id' => null,
                'source'    => $program->source,
                'source_id' => $program->source_id,
            ]);

            $program->update([
                'client_id' => $clientId,
                'source'    => 'client_import',
                'source_id' => (string) $library->id,
            ]);

            $moved = TrainingProgram::where('source', 'client_import')
                ->where('source_id', (string) $program->id)
                ->where('id', '!=', $program->id)
                ->update(['source_id' => (string) $library->id]);

            return [$library, $moved];
        });

        [$library, $moved] = $result;
        $this->info("Hecho: biblioteca nueva #{$library->id}; programa #{$program->id} es ahora la copia del cliente #{$clientId}; {$moved} copia(s) reapuntadas.");

        return self::SUCCESS;
    }
}
