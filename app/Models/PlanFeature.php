<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanFeature extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'plan_id', 'name', 'slug', 'description', 'value',
        'resettable_period', 'resettable_interval', 'sort_order',
    ];

    protected $casts = [
        'resettable_period' => 'integer',
        'resettable_interval' => 'string',
        'sort_order' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::deleted(function ($feature) {
            $feature->usages()->delete();
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PlanSubscriptionUsage::class, 'feature_id');
    }
}
