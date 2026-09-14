<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\CheckSubscription;
use App\Console\Commands\CheckSubscriptionExpiring;
use App\Console\Commands\CheckHabitStreaks;
use App\Console\Commands\SendQuotes;
use Carbon\Carbon;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        CheckSubscription::class,
        CheckSubscriptionExpiring::class,
        CheckHabitStreaks::class,
        SendQuotes::class,
        \App\Console\Commands\ImportChatCocinaCommand::class,
        \App\Console\Commands\ImportProgramsCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('check:subscription')->daily();
        $schedule->command('check:subscription-expiring')->daily();
        $schedule->command('check:habit-streaks')->dailyAt('20:00');
        // Motor de Auto-Regulación de Carga (Fase 1) — detección de patrón
        // recurrente de dolor, no crítico en tiempo real (la alerta
        // inmediata ya la dispara PainReportObserver en el momento del
        // reporte).
        $schedule->command('check:pain-patterns')->daily();
        // Motor de Auto-Regulación de Carga (Fase 2) — documento §2.3:
        // aplica fallback_behavior a sugerencias pendientes cuya sesión ya
        // está a menos de 24h y el coach no respondió. Cada hora es
        // suficiente granularidad para una ventana de 24h.
        $schedule->command('progression:apply-fallbacks')->hourly();
        // Motor de Auto-Regulación de Carga (Fase 2) — documento §2.3:
        // job de calibración automática, "puede ser semanal, no crítico".
        $schedule->command('progression:check-recalibration')->weekly();
        // Motor de Auto-Regulación de Carga (Fase 4) — readiness score
        // diario, antes de que empiecen las sesiones del día (documento
        // §4.1). Solo clientes paid-tier (gate comprobado dentro del
        // propio servicio, por cliente).
        $schedule->command('readiness:calculate')->dailyAt('06:00');
        // Motor de Auto-Regulación de Carga — cierre automático de
        // mesociclo (achievement_events tipo mesociclo_cerrado). No
        // crítico en tiempo real, corre después de readiness para que un
        // mesociclo cerrado hoy ya tenga el readiness_band del día
        // disponible si algún día hace falta cruzarlo.
        $schedule->command('check:mesocycle-closures')->dailyAt('06:30');
        // Score de Riesgo de Abandono (docs/Score_Riesgo_Abandono_Implementacion.md)
        // — sustituye a check:client-inactivity (mismo slot horario). No
        // crítico en tiempo real, corre después de readiness/mesociclos.
        $schedule->command('retention-risk:calculate')->dailyAt('07:00');
        // Backup de BD (docs/TAREAS.md: "sigue sin automatizarse" hasta
        // ahora) - el comando en si comprueba AppSetting->backup_enabled y
        // ->backup_frequency, el toggle real vive en /app-settings.
        $schedule->command('backup:run')->dailyAt('03:00');
        // Red de seguridad para exercise_id roto en workout_template_exercises
        // (ver app/Console/Commands/CheckProgramsIntegrityCommand.php y
        // docs/AGENTE_IMPORTADOR.md) -- solo REPORTA, sin --fix: reparar
        // automáticamente sin revisión humana puede sustituir un ejercicio
        // por otro no equivalente (ver commit 92b060f). El servidor corre en
        // UTC; ->timezone() aquí asegura que sean siempre las 4:00 hora
        // española de verdad, con cambio de horario de verano/invierno
        // incluido.
        $schedule->command('programs:check-integrity')
            ->weeklyOn(0, '04:00')
            ->timezone('Europe/Madrid')
            ->appendOutputTo(storage_path('logs/programs-check-integrity.log'));
        $time = SettingData ('QUOTE', 'QUOTE_TIME') ?? '05:00';
        $timezone = SettingData ('string', 'timezone') ?? config('app.timezone');
        
        if ( $timezone != 'UTC' ) {
            $time = Carbon::createFromFormat('H:i', $time, $timezone)->setTimezone('UTC')->format('H:i');
        }

        $schedule->command('send:quotes')->daily()->at($time);
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
