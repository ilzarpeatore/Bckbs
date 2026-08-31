<?php

namespace App\Console\Commands;

use App\Services\ReadinessCalculationService;
use Illuminate\Console\Command;

/**
 * Motor de Auto-Regulación de Carga — Fase 4 (readiness score, documento
 * §4.1). Corre diario a las 6:00 (ver Kernel::schedule), antes de que
 * empiecen las sesiones del día. Idempotente: reprocesar el mismo día
 * actualiza el readiness_score existente en vez de duplicar (unique
 * client_id+date), por si llegan datos de Health con delay y hace falta
 * recalcular sin bloquear (documento §4.1, "Sincronización con Health APIs").
 */
class CalculateReadinessScores extends Command
{
    protected $signature = 'readiness:calculate {--date= : Fecha a calcular (Y-m-d), por defecto hoy}';

    protected $description = 'Calcula el readiness_score diario de todos los clientes paid-tier';

    public function handle(ReadinessCalculationService $service): int
    {
        $date = $this->option('date') ? \Carbon\Carbon::parse($this->option('date')) : now();

        $count = $service->calculateForAllPaidClients($date);

        $this->info("Readiness calculado para {$count} cliente(s) paid-tier ({$date->toDateString()}).");

        return self::SUCCESS;
    }
}
