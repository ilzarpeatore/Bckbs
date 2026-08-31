<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigratePackagesToPlansSeeder extends Seeder
{
    public function run(): void
    {
        $packages = DB::table('packages')->get();

        foreach ($packages as $pkg) {
            $planId = DB::table('plans')->insertGetId([
                'name' => $pkg->name,
                'slug' => Str::slug($pkg->name),
                'description' => $pkg->description ?? null,
                'is_active' => $pkg->status === 'active',
                'price' => $pkg->price ?? 0,
                'signup_fee' => 0,
                'currency' => 'EUR',
                'invoice_period' => $pkg->duration ?? 1,
                'invoice_interval' => $pkg->duration_unit ?? 'month',
                'trial_period' => 0,
                'trial_interval' => 'day',
                'grace_period' => 0,
                'grace_interval' => 'day',
                'sort_order' => 0,
                'training_program_id' => $pkg->training_program_id ?? null,
                'meal_plan_template_id' => $pkg->meal_plan_template_id ?? null,
                'grants_full_workout_library' => $pkg->grants_full_workout_library ?? false,
                'grants_full_recipe_library' => $pkg->grants_full_recipe_library ?? false,
                'created_at' => $pkg->created_at ?? now(),
                'updated_at' => $pkg->updated_at ?? now(),
            ]);

            $this->command?->info("Plan migrado: {$pkg->name} (ID: {$planId})");
        }

        $oldSubscriptions = DB::table('subscriptions')->get();
        foreach ($oldSubscriptions as $sub) {
            $plan = DB::table('plans')->where('name', $sub->package_id ?? null)->first();
            if (!$plan) continue;

            DB::table('plan_subscriptions')->insert([
                'subscriber_type' => 'App\\Models\\User',
                'subscriber_id' => $sub->user_id,
                'plan_id' => $plan->id,
                'name' => "Suscripción {$plan->name}",
                'slug' => 'main',
                'total_amount' => $sub->total_amount ?? 0,
                'payment_status' => $sub->payment_status ?? 'paid',
                'starts_at' => $sub->subscription_start_date ?? now(),
                'ends_at' => $sub->subscription_end_date ?? now()->addMonth(),
                'fulfilled_at' => $sub->fulfilled_at,
                'created_at' => $sub->created_at ?? now(),
                'updated_at' => $sub->updated_at ?? now(),
            ]);

            $this->command?->info("Suscripción migrada: User {$sub->user_id} → Plan {$plan->name}");
        }
    }
}
