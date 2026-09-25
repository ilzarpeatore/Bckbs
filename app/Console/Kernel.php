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
        // 2026-09-15: todas las horas de aquí abajo se anclan con
        // ->timezone('Europe/Madrid') en vez de dejarlas como hora UTC fija.
        // Antes, cada `dailyAt('HH:MM')` era una hora UTC literal -- se leía
        // como "las X de la mañana en España" solo mientras estuviera en
        // vigor el horario de verano (UTC+2); en horario de invierno
        // (UTC+1) se habría desfasado una hora respecto a la hora española
        // real, y vuelta a desfasarse al revés en el cambio siguiente. Los
        // valores de abajo son el equivalente exacto en hora de Madrid de
        // lo que ya corría hoy (verano) -- no cambia nada ahora mismo, solo
        // evita el desfase futuro. `progression:apply-fallbacks` corre
        // ->hourly() y no lleva ->timezone() porque no tiene una hora de
        // pared a la que anclarse.
        $schedule->command('check:subscription')->dailyAt('02:00')->timezone('Europe/Madrid');
        $schedule->command('check:subscription-expiring')->dailyAt('02:00')->timezone('Europe/Madrid');
        $schedule->command('check:habit-streaks')->dailyAt('22:00')->timezone('Europe/Madrid');
        // Motor de Auto-Regulación de Carga (Fase 1) — detección de patrón
        // recurrente de dolor, no crítico en tiempo real (la alerta
        // inmediata ya la dispara PainReportObserver en el momento del
        // reporte).
        $schedule->command('check:pain-patterns')->dailyAt('02:00')->timezone('Europe/Madrid');
        // Motor de Auto-Regulación de Carga (Fase 2) — documento §2.3:
        // aplica fallback_behavior a sugerencias pendientes cuya sesión ya
        // está a menos de 24h y el coach no respondió. Cada hora es
        // suficiente granularidad para una ventana de 24h.
        $schedule->command('progression:apply-fallbacks')->hourly();
        // Motor de Auto-Regulación de Carga (Fase 2) — documento §2.3:
        // job de calibración automática, "puede ser semanal, no crítico".
        $schedule->command('progression:check-recalibration')->weeklyOn(0, '02:00')->timezone('Europe/Madrid');
        // Motor de Auto-Regulación de Carga (Fase 4) — readiness score
        // diario, antes de que empiecen las sesiones del día (documento
        // §4.1). Solo clientes paid-tier (gate comprobado dentro del
        // propio servicio, por cliente).
        $schedule->command('readiness:calculate')->dailyAt('08:00')->timezone('Europe/Madrid');
        // Motor de Auto-Regulación de Carga — cierre automático de
        // mesociclo (achievement_events tipo mesociclo_cerrado). No
        // crítico en tiempo real, corre después de readiness para que un
        // mesociclo cerrado hoy ya tenga el readiness_band del día
        // disponible si algún día hace falta cruzarlo.
        $schedule->command('check:mesocycle-closures')->dailyAt('08:30')->timezone('Europe/Madrid');
        // Score de Riesgo de Abandono (docs/Score_Riesgo_Abandono_Implementacion.md)
        // — sustituye a check:client-inactivity (mismo slot horario). No
        // crítico en tiempo real, corre después de readiness/mesociclos.
        $schedule->command('retention-risk:calculate')->dailyAt('09:00')->timezone('Europe/Madrid');
        // Backup de BD (docs/TAREAS.md: "sigue sin automatizarse" hasta
        // ahora) - el comando en si comprueba AppSetting->backup_enabled y
        // ->backup_frequency, el toggle real vive en /app-settings.
        $schedule->command('backup:run')->dailyAt('05:00')->timezone('Europe/Madrid');
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
        // Aislamiento entre clientes (solo lectura): ningún programa ni plantilla
        // compartidos entre clientes ni con la biblioteca. Si detecta una
        // violación avisa a los admin con una notificación (sin escribir
        // ficheros: el scheduler corre como root y dejaría logs con dueño
        // root que PHP-FPM no puede escribir).
        $schedule->command('programs:audit-isolation')
            ->dailyAt('05:30')
            ->timezone('Europe/Madrid')
            ->onFailure(function () {
                \App\Services\StaffAlertService::send(
                    'Aislamiento entre clientes: violación detectada',
                    'La auditoría diaria (programs:audit-isolation) ha detectado un programa o una plantilla compartidos entre clientes. Ejecuta el comando por SSH para ver el detalle.'
                );
                foreach (\App\Models\User::where('user_type', 'admin')->get() as $admin) {
                    $admin->notify(new \App\Notifications\CommonNotification('isolation_violation', [
                        'id'      => 0,
                        'type'    => 'isolation_violation',
                        'subject' => 'Aislamiento entre clientes',
                        'message' => 'La auditoría diaria ha detectado un programa o una plantilla compartidos entre clientes. Ejecuta programs:audit-isolation por SSH para ver el detalle.',
                    ]));
                }
            });
        $time = SettingData ('QUOTE', 'QUOTE_TIME') ?? '05:00';
        $timezone = SettingData ('string', 'timezone') ?? config('app.timezone');
        
        if ( $timezone != 'UTC' ) {
            $time = Carbon::createFromFormat('H:i', $time, $timezone)->setTimezone('UTC')->format('H:i');
        }

        $schedule->command('send:quotes')->daily()->at($time);

        // Integración FatSecret (2026-09-19, ver docs/FATSECRET_INTEGRATION.md
        // sección 6) -- un alimento genérico casi nunca cambia de verdad,
        // mensual sobra de margen para cumplir su límite de 24h sin refrescar.
        $schedule->command('fatsecret:refresh-ingredients')->monthly();
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
