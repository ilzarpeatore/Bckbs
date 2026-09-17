<?php

namespace App\Console\Commands;

use App\Models\ProgramClientAssignment;
use App\Models\TrainingProgram;
use App\Services\ProgramCloningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fase 6 de docs/PLAN_CLONADO_PROGRAMAS.md — backfill de las asignaciones
 * que ya existían ANTES de activar `services.program_cloning.enabled`
 * (Fase 2). Esas filas de `program_client_assignments` siguen apuntando
 * directo al `training_program_id` de biblioteca (nunca se clonaron, es
 * el bug raíz del plan) -- este comando las migra a su propio clon
 * exclusivo, una por una, reutilizando `ProgramCloningService::clone()`
 * (misma pieza que ya usa `ProgramAssignmentService` para asignaciones
 * nuevas con el flag activo).
 *
 * IMPORTANTE (ver instrucciones de la tarea): este comando NO se ha
 * ejecutado nunca contra producción desde ningún entorno de agente --
 * solo existe el código y su cobertura de tests contra sqlite de test.
 * Dry-run es el modo por defecto a propósito, precisamente para que la
 * primera ejecución real contra producción sea una decisión explícita del
 * humano operando el VPS, no un efecto colateral de correr el comando.
 *
 *   php artisan programs:backfill-clones            # dry-run, no escribe nada
 *   php artisan programs:backfill-clones --apply     # clona y re-apunta de verdad
 */
class BackfillProgramClonesCommand extends Command
{
    protected $signature = 'programs:backfill-clones
        {--apply : Ejecuta el backfill de verdad (clona y actualiza filas). Sin este flag, solo reporta (dry-run).}';

    protected $description = 'Backfill: clona la plantilla de biblioteca para cada ProgramClientAssignment activo que aún no tenga su propia copia de cliente (Fase 6 de docs/PLAN_CLONADO_PROGRAMAS.md)';

    public function __construct(private readonly ProgramCloningService $cloningService = new ProgramCloningService())
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        // Todas las asignaciones activas, con su TrainingProgram cargado --
        // el criterio real ("¿sigue apuntando a biblioteca?") se evalúa por
        // fila dentro del bucle (is_client_copy), no en el WHERE, para que
        // el mismo query sirva tanto al dry-run como al modo real y el
        // conteo de "saltadas por ya tener clon" sea exacto en ambos casos
        // (incluida la segunda ejecución de --apply, que es el propio test
        // de idempotencia).
        $assignments = ProgramClientAssignment::where('activo', true)
            ->with('trainingProgram')
            ->orderBy('id')
            ->get()
            ->filter(fn (ProgramClientAssignment $assignment) => $assignment->trainingProgram !== null);

        return $apply
            ? $this->runApply($assignments)
            : $this->runDryRun($assignments);
    }

    private function runDryRun($assignments): int
    {
        $pending = $assignments->reject(fn (ProgramClientAssignment $a) => $a->trainingProgram->is_client_copy);

        if ($pending->isEmpty()) {
            $this->info('Dry-run: no hay asignaciones activas pendientes de clonar (todas ya apuntan a un clon propio, o no hay ninguna activa).');

            return self::SUCCESS;
        }

        // Agrupado por training_program_id de biblioteca -- es lo que deja
        // ver de un vistazo cuántos clientes distintos comparten hoy la
        // misma plantilla (el escenario exacto que produce el bug).
        $rows = [];
        foreach ($pending->groupBy('training_program_id') as $libraryProgramId => $group) {
            /** @var TrainingProgram $library */
            $library = $group->first()->trainingProgram;
            $rows[] = [
                $libraryProgramId,
                $library->title,
                $group->count(),
            ];
        }

        $this->line('Dry-run (sin cambios en BD) -- asignaciones activas que aún apuntan a la plantilla de biblioteca compartida:');
        $this->table(['training_program_id (biblioteca)', 'título', 'asignaciones de cliente pendientes'], $rows);
        $this->info("Total: {$pending->count()} asignación(es) se convertirían en clones si se ejecuta con --apply.");

        return self::SUCCESS;
    }

    private function runApply($assignments): int
    {
        $cloned = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($assignments as $assignment) {
            /** @var TrainingProgram $libraryProgram */
            $libraryProgram = $assignment->trainingProgram;

            if ($libraryProgram->is_client_copy) {
                // Ya migrada en una ejecución anterior (idempotencia) --
                // no se toca.
                $skipped++;
                continue;
            }

            try {
                // Transacción POR ASIGNACIÓN (no una transacción global
                // para todo el backfill): si el catálogo de producción es
                // grande y una fila falla (p.ej. datos inconsistentes en
                // una plantilla concreta), las demás asignaciones deben
                // poder seguir procesándose -- un rollback total tiraría
                // a la basura horas de clonado ya hecho y correcto por una
                // sola fila problemática. `clone()` ya abre su propia
                // transacción interna; anidarla aquí solo añade el
                // `training_program_id` de la asignación al mismo commit
                // atómico (todo-o-nada para ESTA fila).
                DB::transaction(function () use ($assignment, $libraryProgram) {
                    $clientCopy = $this->cloningService->clone($libraryProgram, $assignment->client_id);
                    $assignment->update(['training_program_id' => $clientCopy->id]);
                });

                $this->line("Asignación #{$assignment->id} (cliente #{$assignment->client_id}): clonado programa de biblioteca #{$libraryProgram->id} -> nuevo clon creado.");
                $cloned++;
            } catch (\Throwable $e) {
                $this->error("Asignación #{$assignment->id} (cliente #{$assignment->client_id}): FALLÓ el clonado -- {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Resumen: {$cloned} clonada(s), {$skipped} saltada(s) (ya tenían clon), {$failed} fallida(s).");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
