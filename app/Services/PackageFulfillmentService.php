<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Package;
use App\Models\User;
use App\Models\MealPlanTemplate;
use App\Models\DailyPlan;
use App\Models\DailyPlanRecipe;
use App\Models\TrainingProgram;
use App\Models\ProgramClientAssignment;
use Carbon\Carbon;

/**
 * Cumple (fulfill) una Subscription activa+pagada: importa el contenido del
 * Package (TrainingProgram y/o MealPlanTemplate) al calendario real del
 * cliente. Disparado automáticamente por Subscription::boot() (evento
 * `saved`), idempotente vía `subscriptions.fulfilled_at`.
 *
 * No toca SubscriptionTrait::get_plan_expiration_date() (usado por el flujo
 * legacy de cliente SubscriptionController::subscriptionSave, que además
 * asume una sola suscripción activa por usuario — decisión de la Fase 0: no
 * tocar ese flujo). computeEndDate() de aquí es independiente y usa los
 * valores reales de duration_unit ('day'/'week'/'month'/'year', los que de
 * verdad valida Admin\PackageController) — NOTA: get_plan_expiration_date()
 * solo reconoce 'monthly'/'yearly', que ningún Package real puede tener
 * (bug latente nunca ejercitado: 0 packages/subscriptions existían antes de
 * hoy — ver TAREAS.md, no se toca aquí a propósito).
 */
class PackageFulfillmentService
{
    public static function fulfill(Subscription $subscription): void
    {
        if ($subscription->fulfilled_at) {
            return;
        }

        $package = $subscription->package;
        $user = $subscription->user;

        if (!$package || !$user) {
            return;
        }

        $startDate = Carbon::parse($subscription->subscription_start_date ?? now())->startOfDay();

        if ($package->meal_plan_template_id && $package->mealPlanTemplate) {
            self::importMealPlanTemplate($user, $package->mealPlanTemplate, $startDate, $package, $subscription->id);
        }

        if ($package->training_program_id && $package->trainingProgram) {
            self::assignTrainingProgram($user, $package->trainingProgram, $startDate, $subscription->id);
        }

        $subscription->fulfilled_at = now();
        $subscription->saveQuietly();

        // Motor de Auto-Regulación de Carga (2026-08-12): un cliente free
        // que compra un Package pasa a paid-tier por esta vía (no por
        // is_personal_client) -- mismo backfill que el observer de User,
        // pero este es el punto de enganche real para suscripciones.
        \App\Jobs\BackfillClientSessionHistory::dispatch($user);
    }

    /**
     * AÑADIDO — Fase 2: al expirar el Package, retira del calendario del
     * cliente solo las entradas FUTURAS que vinieron de esta Subscription.
     * Lo ya pasado/completado no se toca (nutrición: las filas de
     * daily_plan_recipes de fechas pasadas se quedan; entrenamiento: el
     * historial real de series completadas vive en client_exercise_logs,
     * una tabla totalmente aparte que esto no toca).
     *
     * Para Entrenamiento se borra la fila entera de ProgramClientAssignment
     * (no solo "lo futuro") porque su calendario se resuelve en vivo a partir
     * de esa única fila — no hay filas por día que filtrar por fecha como en
     * Nutrición. Ver TAREAS.md para el razonamiento completo.
     */
    public static function revokeAccess(Subscription $subscription): void
    {
        if ($subscription->access_revoked_at) {
            return;
        }

        DailyPlanRecipe::where('source_subscription_id', $subscription->id)
            ->whereHas('dailyplan', fn ($q) => $q->whereDate('date', '>', today()))
            ->delete();

        ProgramClientAssignment::where('source_subscription_id', $subscription->id)->delete();

        $subscription->access_revoked_at = now();
        $subscription->saveQuietly();
    }

    public static function computeEndDate(Package $package, Carbon $start): Carbon
    {
        $duration = max((int) $package->duration, 1);

        return match ($package->duration_unit) {
            'day'   => $start->copy()->addDays($duration),
            'week'  => $start->copy()->addWeeks($duration),
            'month' => $start->copy()->addMonths($duration),
            'year'  => $start->copy()->addYears($duration),
            default => $start->copy()->addMonths($duration),
        };
    }

    private static function packageDurationInWeeks(Package $package): int
    {
        $duration = max((int) $package->duration, 1);

        $weeks = match ($package->duration_unit) {
            'day'   => (int) ceil($duration / 7),
            'week'  => $duration,
            'month' => $duration * 4,
            'year'  => $duration * 52,
            default => $duration * 4,
        };

        return max(1, min(12, $weeks));
    }

    private static function importMealPlanTemplate(User $user, MealPlanTemplate $template, Carbon $startDate, Package $package, int $sourceSubscriptionId): void
    {
        $template->loadMissing('items');

        if ($template->type === 'sequential') {
            $itemsByOffset = $template->items->groupBy('day_key');

            foreach ($itemsByOffset as $dayKey => $items) {
                $date = $startDate->copy()->addDays((int) $dayKey)->toDateString();
                $dailyPlan = DailyPlan::findOrCreateDailyPlan($user, $date);

                foreach ($items as $item) {
                    DailyPlanRecipe::create([
                        'daily_plan_id'          => $dailyPlan->id,
                        'recipe_id'              => $item->recipe_id,
                        'meal_type'              => $item->meal_type,
                        'calories'               => $item->calories,
                        'protein'                => $item->protein,
                        'fats'                   => $item->fats,
                        'carbs'                  => $item->carbs,
                        'assigned_by_user_id'    => null,
                        'source_subscription_id' => $sourceSubscriptionId,
                    ]);
                }
            }

            return;
        }

        $weeks = self::packageDurationInWeeks($package);
        $itemsByWeekday = $template->items->groupBy('day_key');
        $totalDays = $weeks * 7;

        for ($i = 0; $i < $totalDays; $i++) {
            $day = $startDate->copy()->addDays($i);
            $weekday = strtolower($day->format('l'));
            $items = $itemsByWeekday->get($weekday);
            if (!$items) {
                continue;
            }

            $dailyPlan = DailyPlan::findOrCreateDailyPlan($user, $day->toDateString());

            foreach ($items as $item) {
                DailyPlanRecipe::create([
                    'daily_plan_id'          => $dailyPlan->id,
                    'recipe_id'              => $item->recipe_id,
                    'meal_type'              => $item->meal_type,
                    'calories'               => $item->calories,
                    'protein'                => $item->protein,
                    'fats'                   => $item->fats,
                    'carbs'                  => $item->carbs,
                    'assigned_by_user_id'    => null,
                    'source_subscription_id' => $sourceSubscriptionId,
                ]);
            }
        }
    }

    private static function assignTrainingProgram(User $user, TrainingProgram $trainingProgram, Carbon $startDate, int $sourceSubscriptionId): void
    {
        (new ProgramAssignmentService())->assignOrRenew(
            $user->id,
            $trainingProgram,
            [
                'start_date'              => $startDate,
                'source_subscription_id' => $sourceSubscriptionId,
            ]
        );
    }
}
