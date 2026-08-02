<?php

namespace App\Services;

use App\Models\User;

/**
 * Chequeo de acceso a contenido ligado a un Package (TrainingProgram/MealPlanTemplate
 * de librería). Reglas de negocio cerradas el 2026-07-30:
 * - is_personal_client = true → acceso total, sin gate.
 * - si no, hace falta una Subscription activa+pagada+no vencida cuyo Package
 *   incluya ese contenido concreto. Un cliente free puede tener varias
 *   Subscriptions activas en paralelo (a paquetes distintos).
 *
 * No sustituye a Diet::isAccessible()/is_premium (gate legacy, más simple,
 * por flag en el propio item) — este servicio es específico para el contenido
 * nuevo ligado a Package (TrainingProgram/MealPlanTemplate).
 */
class PackageAccessService
{
    public static function canAccessTrainingProgram(User $user, int $trainingProgramId): bool
    {
        if ($user->is_personal_client) {
            return true;
        }

        return $user->activePackageSubscriptions()
            ->whereHas('package', fn ($q) => $q->where('training_program_id', $trainingProgramId))
            ->exists();
    }

    public static function canAccessMealPlanTemplate(User $user, int $mealPlanTemplateId): bool
    {
        if ($user->is_personal_client) {
            return true;
        }

        return $user->activePackageSubscriptions()
            ->whereHas('package', fn ($q) => $q->where('meal_plan_template_id', $mealPlanTemplateId))
            ->exists();
    }

    // AÑADIDO 2026-07-30: entitlements de "librería completa" (Workouts sueltos
    // exclusivos / Recetas exclusivas) — no ligados a un TrainingProgram/
    // MealPlanTemplate concreto, se conceden vía los flags grants_full_*
    // del Package (ej. paquete "Full Access Workouts", o el paquete de un
    // Programa de pago que además regala ambas librerías).
    public static function canAccessPremiumWorkouts(User $user): bool
    {
        if ($user->is_personal_client) {
            return true;
        }

        return $user->activePackageSubscriptions()
            ->whereHas('package', fn ($q) => $q->where('grants_full_workout_library', true))
            ->exists();
    }

    public static function canAccessPremiumRecipes(User $user): bool
    {
        if ($user->is_personal_client) {
            return true;
        }

        return $user->activePackageSubscriptions()
            ->whereHas('package', fn ($q) => $q->where('grants_full_recipe_library', true))
            ->exists();
    }
}
