<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ClientBodyMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'metric_type',
        'value',
        'unit',
        'recorded_at',
        'notes',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('metric_type', $type);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('recorded_at', '>=', now()->subDays($days));
    }
}
