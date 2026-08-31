<?php

namespace App\Console\Commands;

use App\Services\MesocycleClosureService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Motor de Auto-Regulación de Carga — cierre automático de mesociclo.
 * Corre diario (ver Kernel::schedule), mismo patrón que check:pain-patterns
 * / readiness:calculate. No crítico en tiempo real -- un mesociclo que
 * terminó ayer se cierra hoy sin problema.
 */
class CloseMesocycles extends Command
{
    protected $signature = 'check:mesocycle-closures {--date= : Fecha de referencia (Y-m-d), por defecto hoy}';

    protected $description = 'Cierra asignaciones de mesociclo cuya fecha_fin ya pasó y genera el achievement_event mesociclo_cerrado (solo paid-tier)';

    public function handle(MesocycleClosureService $service): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : now();

        $count = $service->closeEligibleAssignments($date);

        $this->info("Mesociclos cerrados: {$count} asignación(es) ({$date->toDateString()}).");

        return self::SUCCESS;
    }
}
