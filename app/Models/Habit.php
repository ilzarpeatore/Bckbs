<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Habit extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_id', 'client_id', 'title', 'icon',
        'target_value', 'target_unit', 'frequency',
    ];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id', 'id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    public function logs()
    {
        return $this->hasMany(HabitLog::class, 'habit_id', 'id');
    }

    /** null = plantilla reutilizable del coach; con valor = ya asignado a un cliente. */
    public function scopeTemplates($query)
    {
        return $query->whereNull('client_id');
    }

    public function scopeForClient($query, $client_id)
    {
        return $query->where('client_id', $client_id);
    }

    /**
     * Racha actual (días consecutivos completados hasta hoy).
     * Se calcula sobre habit_logs, no se guarda como columna.
     */
    public function getCurrentStreakAttribute(): int
    {
        $logs = $this->logs()
            ->where('is_completed', true)
            ->orderByDesc('date')
            ->pluck('date');

        $streak = 0;
        $expected = now()->startOfDay();

        foreach ($logs as $date) {
            if ($date->isSameDay($expected)) {
                $streak++;
                $expected = $expected->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }
}
