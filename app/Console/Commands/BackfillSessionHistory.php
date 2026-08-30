<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SessionInterpretationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;

/**
 * Motor de Auto-Regulación de Carga (2026-08-12) — versión manual de
 * App\Jobs\BackfillClientSessionHistory, para cuentas que pasaron a
 * paid-tier ANTES de que existiera el observer automático (ej. corregidas
 * a mano por SQL/tinker en vez de por el flujo real de la app/panel admin).
 */
class BackfillSessionHistory extends Command
{
    protected $signature = 'session-history:backfill {user : ID o email del cliente}';

    protected $description = 'Reprocesa el historial de sesiones ya existente de un cliente paid-tier hacia exercise_session_metrics';

    public function handle(SessionInterpretationService $service): int
    {
        $identifier = $this->argument('user');
        $client = is_numeric($identifier) ? User::find($identifier) : User::where('email', $identifier)->first();

        if (!$client) {
            $this->error("Cliente no encontrado: {$identifier}");
            return self::FAILURE;
        }

        if (!Gate::forUser($client)->allows('paid-tier')) {
            $this->error("{$client->email} no es paid-tier hoy — el backfill no serviría de nada (el motor sigue bloqueado por el gate).");
            return self::FAILURE;
        }

        $count = $service->backfillHistoryForClient($client);

        $this->info("Backfill completo para {$client->email}: {$count} sesión(es) reprocesada(s) hacia exercise_session_metrics.");

        return self::SUCCESS;
    }
}
