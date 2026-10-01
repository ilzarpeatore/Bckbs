<?php

namespace App\Console\Commands;

use App\Models\WebPageview;
use Illuminate\Console\Command;

/** Borra visitas de la analítica propia más antiguas que N meses (por defecto 25). */
class PruneWebAnalytics extends Command
{
    protected $signature = 'analytics:prune {--months=25}';

    protected $description = 'Elimina visitas antiguas de la analítica propia de la web';

    public function handle(): int
    {
        $months = max(1, (int) $this->option('months'));
        $deleted = WebPageview::where('created_at', '<', now()->subMonths($months))->delete();
        $this->info("Visitas eliminadas: {$deleted}");

        return self::SUCCESS;
    }
}
