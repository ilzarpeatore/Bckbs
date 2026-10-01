<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\User;
use App\Models\MealPlanTemplate;
use App\Models\DailyPlan;
use App\Models\DailyPlanRecipe;
use App\Models\TrainingProgram;
use App\Models\ProgramClientAssignment;
use App\Models\Habit;
use App\Models\Resource;
use Carbon\Carbon;

/**
 * Adaptación de PackageFulfillmentService para el nuevo modelo Plan.
 * Misma lógica de negocio, pero usando Plan/PlanSubscription en vez de Package/Subscription.
 */
class PlanFulfillmentService
{
    public static function fulfill(PlanSubscription $subscription): void
    {
        if ($subscription->fulfilled_at) return;

        $plan = $subscription->plan;
        $subscriber = $subscription->subscriber;

        if (!$plan || !$subscriber) return;

        $startDate = Carbon::parse($subscription->starts_at ?? now())->startOfDay();

        if ($plan->meal_plan_template_id && $plan->mealPlanTemplate) {
            self::importMealPlanTemplate($subscriber, $plan->mealPlanTemplate, $startDate, $plan, $subscription->id);
        }

        if ($plan->training_program_id && $plan->trainingProgram) {
            self::assignTrainingProgram($subscriber, $plan->trainingProgram, $startDate, $subscription->id);
        }

        // Packs (2026-09-30): hábitos y recursos incluidos en el plan.
        self::assignHabitTemplates($subscriber, (array) ($plan->habit_template_ids ?? []));
        self::assignResources($subscriber, (array) ($plan->resource_ids ?? []));

        $subscription->fulfilled_at = now();
        $subscription->saveQuietly();
    }

    public static function revokeAccess(PlanSubscription $subscription): void
    {
        if ($subscription->access_revoked_at) return;

        DailyPlanRecipe::where('source_subscription_id', $subscription->id)
            ->whereHas('dailyplan', fn ($q) => $q->whereDate('date', '>', today()))
            ->delete();

        ProgramClientAssignment::where('source_subscription_id', $subscription->id)->delete();

        $subscription->access_revoked_at = now();
        $subscription->saveQuietly();
    }

    public static function computeEndDate(Plan $plan, Carbon $start): Carbon
    {
        $duration = max((int) $plan->invoice_period, 1);

        return match ($plan->invoice_interval) {
            'day' => $start->copy()->addDays($duration),
            'week' => $start->copy()->addWeeks($duration),
            'month' => $start->copy()->addMonths($duration),
            'year' => $start->copy()->addYears($duration),
            default => $start->copy()->addMonths($duration),
        };
    }

    private static function packageDurationInWeeks(Plan $plan): int
    {
        $duration = max((int) $plan->invoice_period, 1);
        $weeks = match ($plan->invoice_interval) {
            'day' => (int) ceil($duration / 7),
            'week' => $duration,
            'month' => $duration * 4,
            'year' => $duration * 52,
            default => $duration * 4,
        };
        return max(1, min(12, $weeks));
    }

    private static function importMealPlanTemplate(User $user, MealPlanTemplate $template, Carbon $startDate, Plan $plan, int $sourceSubscriptionId): void
    {
        $template->loadMissing('items');
        if ($template->type === 'sequential') {
            $itemsByOffset = $template->items->groupBy('day_key');
            foreach ($itemsByOffset as $dayKey => $items) {
                $date = $startDate->copy()->addDays((int) $dayKey)->toDateString();
                $dailyPlan = DailyPlan::findOrCreateDailyPlan($user, $date);
                foreach ($items as $item) {
                    DailyPlanRecipe::create([
                        'daily_plan_id' => $dailyPlan->id,
                        'recipe_id' => $item->recipe_id,
                        'meal_type' => $item->meal_type,
                        'calories' => $item->calories,
                        'protein' => $item->protein,
                        'fats' => $item->fats,
                        'carbs' => $item->carbs,
                        'assigned_by_user_id' => null,
                        'source_subscription_id' => $sourceSubscriptionId,
                    ]);
                }
            }
            return;
        }
        $weeks = self::packageDurationInWeeks($plan);
        $itemsByWeekday = $template->items->groupBy('day_key');
        $totalDays = $weeks * 7;
        for ($i = 0; $i < $totalDays; $i++) {
            $day = $startDate->copy()->addDays($i);
            $weekday = strtolower($day->format('l'));
            $items = $itemsByWeekday->get($weekday);
            if (!$items) continue;
            $dailyPlan = DailyPlan::findOrCreateDailyPlan($user, $day->toDateString());
            foreach ($items as $item) {
                DailyPlanRecipe::create([
                    'daily_plan_id' => $dailyPlan->id,
                    'recipe_id' => $item->recipe_id,
                    'meal_type' => $item->meal_type,
                    'calories' => $item->calories,
                    'protein' => $item->protein,
                    'fats' => $item->fats,
                    'carbs' => $item->carbs,
                    'assigned_by_user_id' => null,
                    'source_subscription_id' => $sourceSubscriptionId,
                ]);
            }
        }
    }

    /**
     * Copia propia de cada plantilla de hábito (mismo criterio que
     * ClientHabitController::adopt): si el cliente ya tenía ese hábito de
     * esa plantilla, no se duplica.
     */
    private static function assignHabitTemplates(User $user, array $templateIds): void
    {
        if (empty($templateIds)) return;
        $templates = Habit::templates()->whereIn('id', $templateIds)->get();
        foreach ($templates as $template) {
            $already = Habit::forClient($user->id)->where('source_template_id', $template->id)->exists();
            if ($already) continue;
            Habit::create([
                'coach_id'           => null,
                'client_id'          => $user->id,
                'source_template_id' => $template->id,
                'title'              => $template->title,
                'icon'               => $template->icon,
                'category'           => $template->category,
                'target_value'       => $template->target_value,
                'target_unit'        => $template->target_unit,
                'frequency'          => $template->frequency,
            ]);
        }
    }

    private static function assignResources(User $user, array $resourceIds): void
    {
        if (empty($resourceIds)) return;
        Resource::whereIn('id', $resourceIds)->get()
            ->each(fn (Resource $resource) => $resource->assignedClients()->syncWithoutDetaching([$user->id]));
    }

    /**
     * La compra asigna el programa con COPIA PROPIA para este cliente (nunca el
     * programa de la biblioteca directamente): ver TrainingProgram::assignToClient().
     */
    private static function assignTrainingProgram(User $user, TrainingProgram $trainingProgram, Carbon $startDate, int $sourceSubscriptionId): void
    {
        $trainingProgram->assignToClient((int) $user->id, $startDate, ['source_subscription_id' => $sourceSubscriptionId]);
    }
}
