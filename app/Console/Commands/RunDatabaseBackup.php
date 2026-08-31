<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Reemplaza el backup manual de BD (repetido a mano varias veces desde el
 * incidente de migrate:fresh del 2026-08-05, nunca automatizado). Se
 * programa siempre (Kernel.php, diaria a las 03:00) pero se auto-desactiva
 * leyendo AppSetting->backup_enabled - el toggle real vive en
 * /app-settings del admin, no en el schedule.
 */
class RunDatabaseBackup extends Command
{
    protected $signature = 'backup:run {--force : Ignorar backup_enabled y backup_frequency, correr siempre}';
    protected $description = 'Vuelca la base de datos a storage/app/backups (gzip) y purga backups mas viejos que la retencion configurada';

    public function handle(): int
    {
        $settings = AppSetting::first();

        if (!$this->option('force')) {
            if (!$settings || !$settings->backup_enabled) {
                $this->info('Backup desactivado en app-settings, no se hace nada.');
                return 0;
            }
            if ($settings->backup_frequency === 'weekly' && !now()->isSunday()) {
                $this->info('Frecuencia semanal, hoy no toca (solo domingos).');
                return 0;
            }
        }

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        $filename = 'backup_' . now()->format('Y-m-d_His') . '.sql.gz';
        $path = $dir . '/' . $filename;

        $db = config('database.connections.mysql');

        // mysqldump | gzip, via shell real (Process::fromShellCommandline)
        // porque necesitamos el pipe - Process::run() normal no encadena
        // dos binarios. La contrasena va por MYSQL_PWD (variable de
        // entorno del subproceso), nunca como argumento en texto plano
        // (evita que aparezca en `ps aux` mientras corre).
        $cmd = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --single-transaction --quick %s | gzip > %s',
            escapeshellarg($db['host']),
            escapeshellarg((string) $db['port']),
            escapeshellarg($db['username']),
            escapeshellarg($db['database']),
            escapeshellarg($path)
        );

        $process = Process::fromShellCommandline($cmd);
        $process->setTimeout(600);
        $process->setEnv(['MYSQL_PWD' => $db['password']]);
        $process->run();

        if (!$process->isSuccessful() || !File::exists($path) || File::size($path) === 0) {
            if ($settings) {
                $settings->backup_last_run_at = now();
                $settings->backup_last_status = 'failed';
                $settings->save();
            }
            $this->error('Backup fallido: ' . $process->getErrorOutput());
            return 1;
        }

        $sizeKb = (int) round(File::size($path) / 1024);

        if ($settings) {
            $settings->backup_last_run_at = now();
            $settings->backup_last_status = 'success';
            $settings->backup_last_size_kb = $sizeKb;
            $settings->save();

            $this->pruneOldBackups($dir, (int) $settings->backup_retention_days);
        }

        $this->info("Backup OK: {$filename} ({$sizeKb} KB)");
        return 0;
    }

    private function pruneOldBackups(string $dir, int $retentionDays): void
    {
        if ($retentionDays <= 0) {
            return;
        }
        $cutoff = now()->subDays($retentionDays)->timestamp;

        foreach (File::files($dir) as $file) {
            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
            }
        }
    }
}
