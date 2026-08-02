<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserExercise;
use App\Models\Metric;
use App\Models\User;

class ExerciseHistoryController extends Controller
{
    /**
     * Qué métricas hay disponibles para este ejercicio, según lo que
     * realmente se ha registrado (no una lista fija) — así el selector
     * de pestañas de la app (Series/Reps/Carga/RPE/...) solo muestra lo
     * que de verdad aplica a ese ejercicio para ese cliente.
     */
    public function getAvailableMetrics(Request $request)
    {
        $request->validate(['exercise_id' => 'required|exists:exercises,id']);

        $client_id = $this->resolveClientId($request);

        $sessions = UserExercise::where('user_id', $client_id)
            ->where('exercise_id', $request->exercise_id)
            ->whereNotNull('logged_sets')
            ->select('id', 'logged_sets')
            ->limit(200)
            ->get();

        $used_keys = collect();
        foreach ($sessions as $session) {
            foreach (($session->logged_sets ?? []) as $set) {
                $used_keys = $used_keys->merge(array_keys($set));
            }
        }

        $metrics = Metric::whereIn('key', $used_keys->unique())->ordered()->get();

        return json_custom_response(['data' => $metrics]);
    }

    /**
     * Historial de una métrica concreta: lista sesión a sesión (para la
     * tabla) + serie lista para gráfica (fecha + valor), más el valor
     * actual (última sesión) y el mejor valor conseguido — calculado
     * según higher_is_better de la métrica (para RIR, "mejor" es menor).
     */
    public function getMetricHistory(Request $request)
    {
        $request->validate([
            'exercise_id' => 'required|exists:exercises,id',
            'metric_key'  => 'required|exists:metrics_catalog,key',
        ]);

        $client_id = $this->resolveClientId($request);
        $metric = Metric::where('key', $request->metric_key)->first();

        $sessions = UserExercise::where('user_id', $client_id)
            ->where('exercise_id', $request->exercise_id)
            ->whereNotNull('logged_sets')
            ->select('id', 'created_at', 'logged_sets')
            ->orderBy('created_at')
            ->limit(200)
            ->get();

        $chart_series = [];
        $session_detail = [];

        foreach ($sessions as $session) {
            $values_this_session = collect($session->logged_sets ?? [])
                ->pluck($request->metric_key)
                ->filter(fn ($v) => $v !== null && $v !== '')
                ->map(fn ($v) => (float) $v);

            if ($values_this_session->isEmpty()) {
                continue;
            }

            // Para la gráfica: el mejor valor de esa sesión (según higher_is_better).
            $session_value = $metric && $metric->higher_is_better === false
                ? $values_this_session->min()
                : $values_this_session->max();

            $chart_series[] = [
                'date'  => $session->created_at->toDateString(),
                'value' => $session_value,
            ];

            $session_detail[] = [
                'date'  => $session->created_at->toDateString(),
                'sets'  => $session->logged_sets,
                'value' => $session_value,
            ];
        }

        $all_values = collect($chart_series)->pluck('value');
        $best_value = $metric && $metric->higher_is_better === false
            ? $all_values->min()
            : $all_values->max();

        $response = [
            'metric'         => $metric,
            'chart_series'   => $chart_series,   // listo para pintar la gráfica
            'session_detail' => array_reverse($session_detail), // más reciente primero, para la lista
            'current_value'  => collect($chart_series)->last()['value'] ?? null,
            'best_value'     => $best_value,
            'sessions_count' => count($sessions),
        ];

        return json_custom_response($response);
    }

    /**
     * Si quien pregunta es el coach (consultando desde el perfil del
     * cliente en el panel Admin), usa `client_id` del request — pero
     * solo si ese cliente es suyo. Si es el propio cliente desde la app,
     * usa su propio id. Así un mismo endpoint sirve a ambos casos de uso
     * que pediste ("tanto el cliente como yo dentro del admin").
     */
    private function resolveClientId(Request $request): int
    {
        $auth_user = auth('sanctum')->user();

        if ($request->has('client_id') && $auth_user->user_type != 'user') {
            $client = User::where('id', $request->client_id)
                ->where('coach_id', $auth_user->id)
                ->first();

            if ($client) {
                return $client->id;
            }
        }

        return $auth_user->id;
    }
}
