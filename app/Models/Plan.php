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
        'is_pack', 'sold_on_web', 'short_description', 'image_url', 'habit_template_ids', 'resource_ids',
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
        'is_pack' => 'boolean',
        'sold_on_web' => 'boolean',
        'habit_template_ids' => 'array',
        'resource_ids' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($plan) {
            // El slug es la URL pública del pack (bestronger.es/packs/<slug>):
            // único, y con sufijo si el nombre ya existe.
            $base = Str::slug($plan->slug ?: $plan->name) ?: 'plan';
            $slug = $base;
            for ($i = 2; static::withTrashed()->where('slug', $slug)->exists(); $i++) {
                $slug = "{$base}-{$i}";
            }
            $plan->slug = $slug;
        });
        static::updating(function ($plan) {
            if ($plan->isDirty('slug')) {
                $plan->slug = Str::slug((string) $plan->slug) ?: $plan->getOriginal('slug');
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

    /**
     * Búsqueda del listado del panel (Admin\BaseController::index): por nombre,
     * y ?is_pack=1/0 para separar la página Packs de la de Planes.
     */
    public function scopeSearch($query, $request)
    {
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        if ($request->has('is_pack')) {
            $query->where('is_pack', filter_var($request->is_pack, FILTER_VALIDATE_BOOLEAN));
        }

        return $query;
    }

    /** URL pública del pack en la web, o null si no se vende allí. */
    public function packUrl(): ?string
    {
        $web = rtrim((string) config('services.packs.web_url'), '/');

        return $web && $this->sold_on_web ? "{$web}/packs/{$this->slug}" : null;
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

    // Relaciones heredadas del backend original
    public function trainingProgram()
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function mealPlanTemplate()
    {
        return $this->belongsTo(MealPlanTemplate::class, 'meal_plan_template_id');
    }
}
