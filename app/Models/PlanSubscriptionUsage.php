<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanSubscriptionUsage extends Model
{
    use SoftDeletes;

    protected $table = 'plan_subscription_usage';

    protected $fillable = [
        'subscription_id', 'feature_id', 'used', 'valid_until', 'timezone',
    ];

    protected $casts = [
        'used' => 'integer',
        'valid_until' => 'datetime',
    ];

    public function feature(): BelongsTo
    {
        return $this->belongsTo(PlanFeature::class, 'feature_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PlanSubscription::class, 'subscription_id');
    }

    public function expired(): bool
    {
        if (!$this->valid_until) return false;
        return Carbon::now()->gte($this->valid_until);
    }
}
