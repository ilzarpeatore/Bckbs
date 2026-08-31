<?php

namespace App\Http\Middleware;

use App\Models\Plan;
use App\Models\TrainingProgram;
use App\Models\WorkoutTemplate;
use Closure;
use Illuminate\Http\Request;

class CheckSubscriptionAccess
{
    /**
     * Comprueba acceso a contenido según nivel de suscripción:
     * 1. Suscripción activa a Entrenamiento Personal → acceso completo
     * 2. Suscripción activa al Plan vinculado al recurso (billing_plan_id) → acceso a ese programa
     * 3. Sin suscripción activa → acceso solo si recurso tiene is_free_accessible = true
     * 4. Ninguno → 403
     */
    public function handle(Request $request, Closure $next, string $resourceType = 'training_program')
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $resourceId = $request->route('training_program')
            ?? $request->route('program')
            ?? $request->route('id')
            ?? $request->input('training_program_id');

        $personalPlan = Plan::where('slug', 'entrenamiento-personal')->first();

        // Caso 1: Suscripción activa a Entrenamiento Personal → full access
        if ($personalPlan && $this->hasActivePlan($user, $personalPlan->id)) {
            return $next($request);
        }

        // Caso 2: Suscripción activa al billing_plan_id del recurso
        if ($resourceId && $resourceType === 'training_program') {
            $program = TrainingProgram::find($resourceId);
            if ($program && $program->billing_plan_id) {
                if ($this->hasActivePlan($user, $program->billing_plan_id)) {
                    return $next($request);
                }
            }
        }

        // Caso 3: Sin suscripción → solo recursos free
        if ($resourceId) {
            $isFree = false;
            if ($resourceType === 'training_program') {
                $program = TrainingProgram::find($resourceId);
                $isFree = $program && $program->is_free_accessible;
            } elseif ($resourceType === 'workout_template') {
                $template = WorkoutTemplate::find($resourceId);
                $isFree = $template && $template->is_free_accessible;
            }

            if ($isFree) {
                return $next($request);
            }
        }

        return response()->json(['error' => 'subscription_expired', 'message' => 'Necesitas una suscripción activa para acceder a este contenido.'], 403);
    }

    private function hasActivePlan($user, int $planId): bool
    {
        return \App\Models\PlanSubscription::where('subscriber_type', get_class($user))
            ->where('subscriber_id', $user->id)
            ->where('plan_id', $planId)
            ->where(function ($q) {
                $q->where('ends_at', '>', now())->orWhereNull('ends_at');
            })
            ->whereNull('canceled_at')
            ->exists();
    }
}
