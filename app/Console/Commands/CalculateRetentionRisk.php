<?php

namespace App\Console\Commands;

use App\Services\RetentionRiskCalculationService;
use Illuminate\Console\Command;

/**
 * Score de Riesgo de Abandono (docs/Score_Riesgo_Abandono_Implementacion.md
 * §6) — sustituye a check:client-inactivity, que queda borrado (ver
 * ExceptionCategory::INACTIVIDAD, conservado solo para deserializar
 * historial). Mismo slot de programación (07:00, después de readiness y
 * mesociclos) que ocupaba el comando anterior.
 */
class CalculateRetentionRisk extends Command
{
    protected $signature = 'retention-risk:calculate';

    protected $description = 'Calcula el Score de Riesgo de Abandono diario para todos los clientes paid-tier con coach asignado';

    public function handle(RetentionRiskCalculationService $service): int
    {
        $count = $service->calculateForAllPaidClients();

        $this->info("Riesgo de abandono calculado: {$count} cliente(s).");

        return self::SUCCESS;
    }
}
