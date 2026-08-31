<?php

namespace App\Services;

use App\Models\User;

/**
 * Chequeo de acceso a contenido ligado a un Plan (TrainingProgram/MealPlanTemplate
 * de librería, o librería completa vía grants_full_*). Reglas de negocio cerradas
 * el 2026-07-30:
 * - is_personal_client = true → acceso total, sin gate.
 * - si no, hace falta una PlanSubscription activa+pagada cuyo Plan incluya ese
 *   contenido concreto. Un cliente free puede tener varias PlanSubscription
 *   activas en paralelo (a planes distintos).
 *
 * Reescrito 2026-08-13: antes leía Package/Subscription (User::activePackageSubscriptions()),
 * un sistema sin caller real — el único flujo real que concede acceso
 * (PlanSubscriptionController::grantPlan(), y el futuro webhook de Stripe) escribe
 * en Plan/PlanSubscription. Con el nombre viejo (Package) escrito en la tabla
 * legacy, esto nunca se desbloqueaba de verdad para nadie salvo is_personal_client.
 * Mismo nombre de clase para no tener que renombrar los 2+ callers reales
 * (RecipeController, WorkoutTemplateController).
 *
 * No sustituye a Diet::isAccessible()/is_premium (gate legacy, más simple,
 * por flag en el propio item) — este servicio es específico para el contenido
 * nuevo ligado a Plan (TrainingProgram/MealPlanTemplate).
 */
class PackageAccessService
{
    public static function canAccessTrainingProgram(User $user, int $trainingProgramId): bool
    {
        if ($user->is_personal_client) {
            return true;
        }

        return $user->activePlanSubscriptions()
            ->whereHas('plan', fn ($q) => $q->where('training_program_id', $trainingProgramId))
            ->exists();
    }

    public static function canAccessMealPlanTemplate(User $user, int $mealPlanTemplateId): bool
    {
        if ($user->is_personal_client) {
            return true;
        }

        return $user->activePlanSubscriptions()
            ->whereHas('plan', fn ($q) => $q->where('meal_plan_template_id', $mealPlanTemplateId))
            ->exists();
    }

    // AÑADIDO 2026-07-30: entitlements de "librería completa" (Workouts sueltos
    // exclusivos / Recetas exclusivas) — no ligados a un TrainingProgram/
    // MealPlanTemplate concreto, se conceden vía los flags grants_full_*
    // del Plan (ej. plan "Full Access Workouts", o el plan de un Programa de
    // pago que además regala ambas librerías).
    public static function canAccessPremiumWorkouts(User $user): bool
    {
        if ($user->is_personal_client) {
            return true;
        }

        return $user->activePlanSubscriptions()
            ->whereHas('plan', fn ($q) => $q->where('grants_full_workout_library', true))
            ->exists();
    }

    public static function canAccessPremiumRecipes(User $user): bool
    {
        if ($user->is_personal_client) {
            return true;
        }

        return $user->activePlanSubscriptions()
            ->whereHas('plan', fn ($q) => $q->where('grants_full_recipe_library', true))
            ->exists();
    }
}
