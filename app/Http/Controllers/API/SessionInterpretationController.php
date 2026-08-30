<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ExerciseSessionMetric;
use App\Models\PainReport;
use App\Models\ProgramDayAssignment;
use App\Models\User;
use App\Models\WorkoutTemplate;
use Illuminate\Http\Request;

/**
 * Motor de Auto-Regulación de Carga — Fase 1. Endpoints nuevos que NO
 * duplican logSets()/finishSession() (ClientCalendarController), que ya
 * existen y siguen sin cambios salvo la extensión de finishSession() para
 * disparar el job de interpretación.
 */
class SessionInterpretationController extends Controller
{
    /**
     * POST /api/sessions/{id}/pain-report
     *
     * No existe un session_id único (decisión ya confirmada, ver
     * SessionInterpretationService) — {id} identifica la sesión igual que
     * ya hace finishSession(): por defecto es un program_day_assignment_id
     * (caso mayoritario, día de programa asignado, un único slot de
     * calendario); si el cliente está haciendo un workout suelto sin
     * programa, se manda is_workout_template=true y {id} pasa a
     * interpretarse como workout_template_id.
     *
     * Se registra DURANTE la sesión (antes de finishSession/
     * WorkoutSessionReview) — el bloqueo por dolor NUNCA se gatea por
     * tier, aplica a todos los clientes.
     */
    public function painReport(Request $request, $id)
    {
        $request->validate([
            'exercise_id'          => 'required|exists:exercises,id',
            'set_number'           => 'nullable|integer|min:1',
            'tipo'                 => 'required|in:molestia_leve,dolor_agudo,dolor_que_empeora_durante_sesion',
            'localizacion'         => 'required|string|max:100',
            'intensidad'           => 'required|integer|min:1|max:5',
            'momento'              => 'required|in:al_iniciar,durante_ejecucion,al_finalizar,al_dia_siguiente',
            'is_workout_template'  => 'nullable|boolean',
        ]);

        $clientId = auth('sanctum')->id();
        $isTemplate = $request->boolean('is_workout_template');

        $programDayAssignmentId = null;
        $workoutTemplateId = null;

        if ($isTemplate) {
            if (!WorkoutTemplate::where('id', $id)->exists()) {
                return json_custom_response(['message' => 'Sesión no encontrada.'], 404);
            }
            $workoutTemplateId = (int) $id;
        } else {
            if (!ProgramDayAssignment::where('id', $id)->exists()) {
                return json_custom_response(['message' => 'Sesión no encontrada.'], 404);
            }
            $programDayAssignmentId = (int) $id;
        }

        $report = PainReport::create([
            'client_id'                  => $clientId,
            'exercise_id'                => $request->exercise_id,
            'program_day_assignment_id'  => $programDayAssignmentId,
            'workout_template_id'        => $workoutTemplateId,
            'set_number'                 => $request->set_number,
            'tipo'                       => $request->tipo,
            'localizacion'               => $request->localizacion,
            'intensidad'                 => $request->intensidad,
            'momento'                    => $request->momento,
        ]);

        return json_custom_response(['data' => $report]);
    }

    /**
     * GET /api/clients/{id}/exercises/{exerciseId}/metrics
     *
     * Debug/coach (comentario del documento original) — el propio cliente
     * puede consultar sus métricas, y también su coach (vía User.coach_id),
     * mismo criterio de autorización ya usado en el resto de endpoints
     * cliente-o-coach de este proyecto.
     */
    public function exerciseMetrics(Request $request, $id, $exerciseId)
    {
        $authUser = auth('sanctum')->user();
        $clientId = (int) $id;

        $isSelf = $authUser->id === $clientId;
        $isCoach = false;
        if (!$isSelf) {
            $client = User::find($clientId);
            $isCoach = $client && $client->coach_id === $authUser->id;
        }

        if (!$isSelf && !$isCoach) {
            return json_custom_response(['message' => 'No autorizado.'], 403);
        }

        $metrics = ExerciseSessionMetric::where('client_id', $clientId)
            ->where('exercise_id', $exerciseId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return json_custom_response(['data' => $metrics]);
    }
}
