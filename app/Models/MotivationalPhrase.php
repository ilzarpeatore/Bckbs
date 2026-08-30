<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotivationalPhrase extends Model
{
    use HasFactory;

    protected $fillable = ['text', 'condition_type', 'min_value', 'max_value', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForValue($query, string $conditionType, ?int $value)
    {
        return $query->where('condition_type', $conditionType)
            ->when($value !== null, function ($q) use ($value) {
                $q->where(function ($q2) use ($value) {
                    $q2->whereNull('min_value')->orWhere('min_value', '<=', $value);
                })->where(function ($q2) use ($value) {
                    $q2->whereNull('max_value')->orWhere('max_value', '>=', $value);
                });
            });
    }
}
