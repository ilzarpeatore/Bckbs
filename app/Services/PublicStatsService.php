<?php

namespace App\Services;

use App\Models\ClientExerciseLog;
use App\Models\PersonalRecord;
use App\Models\WorkoutSessionReview;
use Carbon\Carbon;

/**
 * Resumen AGREGADO de entrenamientos de un usuario, el único dato que se enseña
 * a otros usuarios en su perfil (y solo si activó show_public_stats).
 *
 * Nunca devuelve cargas, pesos, ejercicios concretos, salud ni fechas de sesiones:
 * solo cuatro números. Una sesión "cuenta" únicamente si el cliente registró al
 * menos una serie (o, en un workout suelto sin asignación de calendario, si su
 * reseña tiene volumen): finalizar sin apuntar nada no infla las estadísticas
 * (caso Ayoub, 2026-09-25, ver EmptySessionAlertService).
 */
class PublicStatsService
{
    public function forUser(int $userId, ?Carbon $now = null): array
    {
        $now = $now ?? now();
        $since = $now->copy()->subDays(30);

        $reviews = WorkoutSessionReview::where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->get(['id', 'program_day_assignment_id', 'workout_template_id', 'volume_kg', 'duration_seconds', 'completed_at']);

        $withLogs = ClientExerciseLog::where('client_id', $userId)
            ->whereIn('program_day_assignment_id', $reviews->pluck('program_day_assignment_id')->filter()->unique()->values())
            ->whereRaw(EmptySessionAlertService::nonEmptyLoggedSetsSql())
            ->distinct()
            ->pluck('program_day_assignment_id')
            ->flip();

        $valid = $reviews->filter(fn ($r) => $r->program_day_assignment_id
            ? $withLogs->has($r->program_day_assignment_id)
            : (float) $r->volume_kg > 0);

        $lastMonth = $valid->filter(fn ($r) => $r->completed_at >= $since);
        $durations = $lastMonth->pluck('duration_seconds')->filter(fn ($s) => (int) $s > 0);

        return [
            'workouts_last_30_days' => $lastMonth->count(),
            'records_count'         => PersonalRecord::where('user_id', $userId)->count(),
            'avg_duration_minutes'  => $durations->isEmpty() ? null : (int) round($durations->avg() / 60),
            'week_streak'           => $this->weekStreak($valid->pluck('completed_at'), $now),
        ];
    }

    /**
     * Semanas ISO consecutivas con al menos un entreno, contando hacia atrás desde
     * la semana actual (o la anterior, si esta todavía no tiene ninguno: la racha
     * no se rompe hasta que pasa una semana entera sin entrenar).
     */
    private function weekStreak($dates, Carbon $now): int
    {
        $weeks = $dates->map(fn ($d) => $d->copy()->startOfWeek()->toDateString())->unique()->flip();

        $cursor = $now->copy()->startOfWeek();
        if (!$weeks->has($cursor->toDateString())) {
            $cursor->subWeek();
        }

        $streak = 0;
        while ($weeks->has($cursor->toDateString())) {
            $streak++;
            $cursor->subWeek();
        }

        return $streak;
    }
}
