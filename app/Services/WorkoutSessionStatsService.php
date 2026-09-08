<?php

namespace App\Services;

use App\Models\ClientExerciseLog;
use App\Models\Exercise;
use App\Models\User;
use Carbon\Carbon;

/**
 * Estadisticas reales de cierre de sesion (Workout Session -> Feedback ->
 * Summary/"Enhorabuena"). Antes de esto: calorias mostraba "0" fijo en la
 * app (ningun calculo existia en todo el proyecto para entrenamiento, a
 * diferencia de nutricion) y la pantalla de "Enhorabuena" no comparaba
 * nada contra la sesion anterior.
 */
class WorkoutSessionStatsService
{
    /**
     * MET (Metabolic Equivalent of Task) para entrenamiento de fuerza
     * moderado-vigoroso - valor estandar del Compendium of Physical
     * Activities para "resistance training, general". No hay forma de
     * calcularlo con precision sin un monitor de frecuencia cardiaca, asi
     * que se usa esta aproximacion (misma que usan la mayoria de apps de
     * fitness para entrenamiento de fuerza sin wearable conectado).
     */
    private const RESISTANCE_TRAINING_MET = 5.0;
    private const FALLBACK_WEIGHT_KG = 70.0;

    // Ítem 37 (Plan de Optimización, Ronda 12, docs/Motor_Autorregulacion_Analisis.md):
    // mismo tamaño de ventana que SessionInterpretationService::TREND_WINDOW
    // (Fase 1) -- comparar contra la media de las últimas N sesiones
    // válidas en vez de solo la inmediatamente anterior, para no premiar
    // "recuperarse de un mal día puntual" como si fuera progreso real.
    private const TREND_WINDOW = 3;

    public static function computeCalories(User $user, int $durationSeconds): float
    {
        $weightKg = optional($user->userProfile)->weight_in_kg;
        $weightKg = is_numeric($weightKg) && $weightKg > 0 ? (float) $weightKg : self::FALLBACK_WEIGHT_KG;

        $hours = max($durationSeconds, 0) / 3600;

        return round(self::RESISTANCE_TRAINING_MET * $weightKg * $hours, 1);
    }

    /**
     * Compara el mejor set de hoy contra la MEDIA del mejor set de cada una
     * de las últimas TREND_WINDOW sesiones válidas (antes: solo la sesión
     * inmediatamente anterior), ejercicio por ejercicio (solo los de esta
     * sesion, pasados desde el frontend porque agrupar "que pertenece a
     * esta sesion" de otra forma es ambiguo: un mismo dia podria en teoria
     * tener mas de una sesion). "Mejor set" = mayor carga; a igualdad de
     * carga, mayor reps.
     *
     * Ítem 37 (Plan de Optimización, Ronda 12): comparar solo contra la
     * sesión anterior premiaba "recuperarse de un mal día puntual" como si
     * fuera progresión real sostenida -- la media de la ventana suaviza
     * ese efecto sin perder la comparación sesión-vs-histórico-reciente
     * que ya distingue este logro (`progreso_sesion`) de los PR all-time.
     */
    public static function computeAchievements(int $userId, array $exerciseIds): array
    {
        $exerciseIds = array_values(array_unique(array_filter($exerciseIds)));
        if (empty($exerciseIds)) {
            return self::emptyAchievements();
        }

        $today = now()->toDateString();
        $titles = Exercise::whereIn('id', $exerciseIds)->pluck('title', 'id');

        $weightUp = [];
        $repsUp = [];
        $betterRpe = [];
        // Fase 3 (Motor de Auto-Regulación de Carga, documento §3.2, tarea
        // #20): detalle por ejercicio (id + valores), AÑADIDO de forma
        // aditiva junto a los arrays de títulos ya existentes (sin quitar
        // ni cambiar 'weight_up_exercises'/etc., que otras pantallas ya
        // consumen tal cual) — lo necesita
        // ClientCalendarController::finishSession() para persistir estos
        // logros en achievement_events (antes solo vivían en esta response
        // transitoria).
        $weightUpDetails = [];
        $repsUpDetails = [];
        $betterRpeDetails = [];

        foreach ($exerciseIds as $exerciseId) {
            $todayLog = ClientExerciseLog::where('client_id', $userId)
                ->where('exercise_id', $exerciseId)
                ->whereDate('performed_date', $today)
                ->orderByDesc('created_at')
                ->first();

            $recentLogs = ClientExerciseLog::where('client_id', $userId)
                ->where('exercise_id', $exerciseId)
                ->where('performed_date', '<', $today)
                ->orderByDesc('performed_date')
                ->orderByDesc('created_at')
                ->limit(self::TREND_WINDOW)
                ->get();

            if (!$todayLog || $recentLogs->isEmpty()) {
                continue; // sin sesion anterior de este ejercicio, nada que comparar
            }

            $todayBest = self::bestSet($todayLog->logged_sets ?? []);
            $prevBest = self::averageBestSet($recentLogs);
            if (!$todayBest || !$prevBest) {
                continue;
            }

            $title = $titles->get($exerciseId, 'Ejercicio');

            if ($todayBest['carga'] > $prevBest['carga']) {
                $weightUp[] = $title;
                $weightUpDetails[] = ['exercise_id' => $exerciseId, 'title' => $title, 'value' => $todayBest['carga'], 'previous_best' => $prevBest['carga']];
            } elseif (abs($todayBest['carga'] - $prevBest['carga']) < 0.01 && $todayBest['reps'] > $prevBest['reps']) {
                // Igualdad exacta con una MEDIA (ítem 37) es improbable en
                // la práctica -- comparación con epsilon en vez de `==`
                // para no perder este caso por precisión de coma flotante.
                $repsUp[] = $title;
                $repsUpDetails[] = ['exercise_id' => $exerciseId, 'title' => $title, 'value' => $todayBest['reps'], 'previous_best' => $prevBest['reps']];
            }

            if ($todayBest['rpe'] !== null && $prevBest['rpe'] !== null
                && $todayBest['carga'] >= $prevBest['carga']
                && $todayBest['rpe'] < $prevBest['rpe']
            ) {
                $betterRpe[] = $title;
                $betterRpeDetails[] = ['exercise_id' => $exerciseId, 'title' => $title, 'value' => $todayBest['rpe'], 'previous_best' => $prevBest['rpe']];
            }
        }

        return [
            'weight_up_count'   => count($weightUp),
            'weight_up_exercises' => $weightUp,
            'weight_up_details' => $weightUpDetails,
            'reps_up_count'     => count($repsUp),
            'reps_up_exercises' => $repsUp,
            'reps_up_details' => $repsUpDetails,
            'better_rpe_count'  => count($betterRpe),
            'better_rpe_exercises' => $betterRpe,
            'better_rpe_details' => $betterRpeDetails,
        ];
    }

    private static function emptyAchievements(): array
    {
        return [
            'weight_up_count' => 0, 'weight_up_exercises' => [], 'weight_up_details' => [],
            'reps_up_count' => 0, 'reps_up_exercises' => [], 'reps_up_details' => [],
            'better_rpe_count' => 0, 'better_rpe_exercises' => [], 'better_rpe_details' => [],
        ];
    }

    /**
     * Ítem 37 (Plan de Optimización, Ronda 12): media del mejor set de cada
     * log de la ventana (los que tengan un mejor set válido) -- reemplaza
     * comparar contra un único log anterior. `rpe` promedia solo los logs
     * que sí reportaron RPE (null si ninguno lo hizo, igual criterio que
     * bestSet() con un set individual).
     *
     * @param \Illuminate\Support\Collection<int, ClientExerciseLog> $logs
     * @return array{carga: float, reps: float, rpe: float|null}|null
     */
    private static function averageBestSet($logs): ?array
    {
        $bests = $logs->map(fn (ClientExerciseLog $log) => self::bestSet($log->logged_sets ?? []))
            ->filter();

        if ($bests->isEmpty()) {
            return null;
        }

        $rpeValues = $bests->pluck('rpe')->filter(fn ($v) => $v !== null);

        return [
            'carga' => round($bests->avg('carga'), 2),
            'reps'  => round($bests->avg('reps'), 2),
            'rpe'   => $rpeValues->isNotEmpty() ? round($rpeValues->avg(), 2) : null,
        ];
    }

    /** @return array{carga: float, reps: float, rpe: float|null}|null */
    private static function bestSet(array $sets): ?array
    {
        $best = null;
        foreach ($sets as $set) {
            $carga = isset($set['carga']) && is_numeric($set['carga']) ? (float) $set['carga'] : 0.0;
            $reps = isset($set['reps']) && is_numeric($set['reps']) ? (float) $set['reps'] : 0.0;
            if ($carga <= 0 && $reps <= 0) {
                continue;
            }
            if ($best === null || $carga > $best['carga'] || ($carga == $best['carga'] && $reps > $best['reps'])) {
                $rpe = isset($set['rpe']) && is_numeric($set['rpe']) ? (float) $set['rpe'] : null;
                $best = ['carga' => $carga, 'reps' => $reps, 'rpe' => $rpe];
            }
        }
        return $best;
    }
}
