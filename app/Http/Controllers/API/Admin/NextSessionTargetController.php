<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExerciseSessionMetric;
use App\Models\NextSessionTarget;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Motor de Auto-Regulación de Carga — visión admin de solo lectura de lo
 * que decidió el motor para la próxima sesión de cada cliente/ejercicio
 * (NextSessionTarget), tanto si quedó "aplicado" automáticamente como si
 * generó una excepción ("pendiente"/"rechazado" se ven ya en el Panel de
 * Excepciones, pero aquí se listan todos los estados). Se adjunta el
 * ExerciseSessionMetric de la misma sesión/ejercicio/cliente (no hay FK
 * entre ambas tablas, se empareja por la clave natural
 * workout_session_review_id+exercise_id+client_id, el mismo patrón que usa
 * SessionProgressionRuleEngine al hacer updateOrCreate de NextSessionTarget)
 * para mostrar el "por qué" de la decisión (RIR real vs. pedido, series
 * completadas, carga efectiva conseguida).
 */
class NextSessionTargetController extends Controller
{
    /** GET /admin/next-session-targets */
    public function index(Request $request)
    {
        $request->validate([
            'coach_id'    => 'required|exists:users,id',
            'client_id'   => 'nullable|exists:users,id',
            'exercise_id' => 'nullable|exists:exercises,id',
            'from'        => 'nullable|date',
            'to'          => 'nullable|date',
        ]);

        $coachId = $request->input('coach_id');

        $query = NextSessionTarget::whereHas('client', fn ($q) => $q->where('coach_id', $coachId));

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }
        if ($request->filled('exercise_id')) {
            $query->where('exercise_id', $request->input('exercise_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('generated_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('generated_at', '<=', Carbon::parse($request->input('to'))->endOfDay());
        }

        $targets = $query->with([
                'client:id,first_name,last_name,email',
                'exercise:id,title',
                'proposedExercise:id,title',
                'rule:id,name',
                'rule.action',
                'resolvedBy:id,first_name,last_name',
            ])
            ->orderByDesc('generated_at')
            ->limit(200)
            ->get();

        // Adjunta el "por qué" (ExerciseSessionMetric) en batch, sin N+1:
        // no hay FK entre las dos tablas, se empareja por la clave natural
        // workout_session_review_id+exercise_id+client_id.
        $reviewIds = $targets->pluck('workout_session_review_id')->filter()->unique()->values();
        $exerciseIds = $targets->pluck('exercise_id')->filter()->unique()->values();

        $metricsByKey = [];
        if ($reviewIds->isNotEmpty() && $exerciseIds->isNotEmpty()) {
            $metrics = ExerciseSessionMetric::whereIn('workout_session_review_id', $reviewIds)
                ->whereIn('exercise_id', $exerciseIds)
                ->get([
                    'workout_session_review_id', 'exercise_id', 'client_id',
                    'rir_delta_sesion', 'completion_ratio', 'carga_efectiva',
                    'carga_efectiva_reps', 'tendencia_rir', 'e1rm_estimado',
                ]);

            foreach ($metrics as $metric) {
                $key = "{$metric->workout_session_review_id}-{$metric->exercise_id}-{$metric->client_id}";
                $metricsByKey[$key] = [
                    'rir_delta_sesion'    => $metric->rir_delta_sesion,
                    'completion_ratio'    => $metric->completion_ratio,
                    'carga_efectiva'      => $metric->carga_efectiva,
                    'carga_efectiva_reps' => $metric->carga_efectiva_reps,
                    'tendencia_rir'       => $metric->tendencia_rir,
                    'e1rm_estimado'       => $metric->e1rm_estimado,
                ];
            }
        }

        $data = $targets->map(function (NextSessionTarget $target) use ($metricsByKey) {
            $key = "{$target->workout_session_review_id}-{$target->exercise_id}-{$target->client_id}";
            $item = $target->toArray();
            $item['metrics'] = $metricsByKey[$key] ?? null;

            return $item;
        });

        return json_custom_response(['data' => $data]);
    }
}
