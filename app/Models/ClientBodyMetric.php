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
        'source',
        'recorded_by_user_id',
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

    // 10 tipos soportados hoy — antropometría clásica + composición corporal.
    // Compartido con la validación del controller cliente y del admin.
    public const METRIC_TYPES = ['weight', 'body_fat', 'muscle_mass', 'chest', 'waist', 'hips', 'neck', 'thigh', 'calf', 'bicep'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
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
