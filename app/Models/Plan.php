<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Plan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'is_active', 'price', 'signup_fee',
        'currency', 'trial_period', 'trial_interval', 'invoice_period',
        'invoice_interval', 'grace_period', 'grace_interval',
        'prorate_day', 'prorate_period', 'prorate_extend_due',
        'active_subscribers_limit', 'sort_order',
        'training_program_id', 'meal_plan_template_id',
        // Packs vendidos en la web (2026-09-30, ver docs/PACKS_WEB.md).
        'sold_on_web', 'short_description', 'image_url', 'habit_template_ids', 'resource_ids',
        'grants_full_workout_library', 'grants_full_recipe_library',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'float',
        'signup_fee' => 'float',
        'trial_period' => 'integer',
        'invoice_period' => 'integer',
        'grace_period' => 'integer',
        'sort_order' => 'integer',
        'grants_full_workout_library' => 'boolean',
        'grants_full_recipe_library' => 'boolean',
        'sold_on_web' => 'boolean',
        'habit_template_ids' => 'array',
        'resource_ids' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($plan) {
            if (empty($plan->slug)) {
                $plan->slug = Str::slug($plan->name);
            }
        });
        static::deleted(function ($plan) {
            $plan->features()->delete();
            $plan->subscriptions()->delete();
        });
    }

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(PlanSubscription::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isFree(): bool
    {
        return $this->price <= 0.00;
    }

    public function hasTrial(): bool
    {
        return $this->trial_period > 0;
    }

    // MightyFitness relations
    public function trainingProgram()
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function mealPlanTemplate()
    {
        return $this->belongsTo(MealPlanTemplate::class, 'meal_plan_template_id');
    }
}
