<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Habit;
use App\Models\MotivationalPhrase;
use App\Models\WorkoutSessionReview;
use Illuminate\Http\Request;

class MotivationalPhraseController extends Controller
{
    /**
     * Frase contextual de la cabecera Home (ver docs/Nueva_Cabecera_Home_Helix.md,
     * seccion 4). Calcula el contexto real del cliente (entrenamientos
     * completados esta semana, racha de habitos mas alta) -- mismo dato que ya
     * calculan otras pantallas (listMyCompletedSessions/Habit::currentStreak),
     * sin duplicar la logica de negocio -- busca las frases activas cuyo rango
     * encaje, elige una al azar entre las que apliquen, sustituye {n} por el
     * dato real. Si ninguna condicion especifica encaja, cae a 'general'.
     */
    public function getPhrase(Request $request)
    {
        $user = auth('sanctum')->user();

        $weekStart = now()->startOfWeek();
        $workoutsThisWeek = WorkoutSessionReview::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $weekStart)
            ->count();

        // Racha mas alta entre los habitos reales del cliente (no plantillas
        // de biblioteca) -- misma formula que Habit::getCurrentStreakAttribute.
        $habitsStreak = Habit::forClient($user->id)->get()
            ->max(fn ($h) => $h->current_streak) ?? 0;

        $candidates = collect();

        $candidates = $candidates->merge(
            MotivationalPhrase::active()->forValue('workouts_this_week', $workoutsThisWeek)->get()
                ->map(fn ($p) => ['phrase' => $p, 'value' => $workoutsThisWeek])
        );
        $candidates = $candidates->merge(
            MotivationalPhrase::active()->forValue('habits_streak', $habitsStreak)->get()
                ->map(fn ($p) => ['phrase' => $p, 'value' => $habitsStreak])
        );

        if ($candidates->isEmpty()) {
            $candidates = MotivationalPhrase::active()->where('condition_type', 'general')->get()
                ->map(fn ($p) => ['phrase' => $p, 'value' => null]);
        }

        if ($candidates->isEmpty()) {
            return json_custom_response(['data' => ['text' => null]]);
        }

        $chosen = $candidates->random();
        $text = $chosen['value'] !== null
            ? str_replace('{n}', (string) $chosen['value'], $chosen['phrase']->text)
            : $chosen['phrase']->text;

        return json_custom_response(['data' => [
            'text' => $text,
            'condition_type' => $chosen['phrase']->condition_type,
        ]]);
    }
}
