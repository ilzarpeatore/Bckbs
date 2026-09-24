<?php

namespace App\Console\Commands;

use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
use App\Models\TrainingProgram;
use App\Services\TemplateIsolationGuard;
use Illuminate\Console\Command;

/**
 * Auditoría de aislamiento entre clientes (solo lectura): comprueba que cada
 * cliente tiene su programa y sus plantillas de sesión independientes.
 *
 *   php artisan programs:audit-isolation
 *
 * Termina con código 1 si encuentra una VIOLACIÓN (datos compartidos entre
 * clientes o entre un cliente y la biblioteca, o un programa de biblioteca
 * asignado directamente a un cliente), 0 si no.
 */
class AuditProgramIsolationCommand extends Command
{
    protected $signature = 'programs:audit-isolation';

    protected $description = 'Audita (solo lectura) que ningún cliente comparta programa ni plantillas con otro cliente o con la biblioteca';

    public function handle(): int
    {
        $violations = 0;

        // 1) Un mismo programa asignado a más de un cliente.
        $sharedPrograms = ProgramClientAssignment::selectRaw('training_program_id, COUNT(DISTINCT client_id) as clientes')
            ->groupBy('training_program_id')
            ->havingRaw('COUNT(DISTINCT client_id) > 1')
            ->get();
        foreach ($sharedPrograms as $row) {
            $violations++;
            $this->error("VIOLACIÓN: el programa {$row->training_program_id} está asignado a {$row->clientes} clientes distintos.");
        }

        // 2) Plantillas usadas por programas de más de un propietario (cliente A/B o biblioteca+cliente).
        $templateIds = ProgramDayAssignment::whereNotNull('workout_template_id')
            ->selectRaw('workout_template_id')
            ->groupBy('workout_template_id')
            ->havingRaw('COUNT(DISTINCT training_program_id) > 1')
            ->pluck('workout_template_id');
        foreach ($templateIds as $templateId) {
            $owners = TemplateIsolationGuard::ownersOfTemplate((int) $templateId);
            if (count($owners) > 1) {
                $violations++;
                $this->error("VIOLACIÓN: la plantilla {$templateId} la usan a la vez: ".implode(', ', $owners).'.');
            }
        }

        // 3) Programas de biblioteca asignados directamente (sin copia propia): el cliente comparte fila con la biblioteca.
        $directs = ProgramClientAssignment::where('activo', true)->get()
            ->groupBy('training_program_id')
            ->filter(function ($rows, $programId) {
                $program = TrainingProgram::find($programId);
                return $program !== null && TemplateIsolationGuard::ownerKey($program) === TemplateIsolationGuard::LIBRARY;
            });
        foreach ($directs as $programId => $rows) {
            $ids = $rows->pluck('client_id')->unique()->implode(', ');
            $violations++;
            $this->error("VIOLACIÓN: el programa de biblioteca {$programId} está asignado directamente (sin copia propia) al/los cliente(s) {$ids}; se corrige con programs:detach-direct-assignment {$programId}.");
        }

        if ($violations === 0) {
            $this->info('Aislamiento OK: ningún programa ni plantilla compartidos entre clientes ni con la biblioteca ('
                .ProgramClientAssignment::count().' asignaciones, '.ProgramDayAssignment::count().' días revisados).');

            return self::SUCCESS;
        }

        $this->error("{$violations} violación(es) de aislamiento.");

        return self::FAILURE;
    }
}
