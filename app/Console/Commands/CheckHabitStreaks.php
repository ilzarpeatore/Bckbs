<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Habit;
use App\Notifications\CommonNotification;

/**
 * Corre una vez al día por la tarde (ver Kernel::schedule) — avisa a un
 * cliente si tiene una racha real (2+ días) en un hábito y todavía no lo
 * ha marcado hoy, para que no la pierda. No se dispara con streak=0/1
 * (ruido innecesario para un hábito recién empezado).
 */
class CheckHabitStreaks extends Command
{
    protected $signature = 'check:habit-streaks';

    protected $description = 'Avisa a los clientes con una racha de hábito en riesgo de perderse hoy';

    public function handle()
    {
        $today = now()->toDateString();

        Habit::whereNotNull('client_id')
            ->with(['client', 'logs' => function ($q) use ($today) {
                $q->where('date', $today);
            }])
            ->chunk(100, function ($habits) use ($today) {
                foreach ($habits as $habit) {
                    if (!$habit->client) {
                        continue;
                    }

                    $streak = $habit->current_streak;
                    if ($streak < 2) {
                        continue;
                    }

                    $todayLog = $habit->logs->first();
                    if ($todayLog && $todayLog->is_completed) {
                        continue; // ya cumplido hoy, racha a salvo
                    }

                    $habit->client->notify(new CommonNotification('habit_streak_risk', [
                        'id'      => $habit->id,
                        'type'    => 'habit_streak_risk',
                        'subject' => 'No pierdas tu racha',
                        'message' => "Llevas {$streak} días seguidos con \"{$habit->title}\" — complétalo hoy para no perder la racha.",
                    ]));
                }
            });
    }
}
