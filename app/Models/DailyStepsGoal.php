<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyStepsGoal extends Model
{

    protected $fillable = [ 'value', 'goal_value', 'date', 'time', 'user_id' ];

    protected $casts = [
        'user_id'   => 'integer',
    ];

    public function scopeMyStepsGoal($query)
    {
        $user = auth()->user();

        if($user->hasRole(['user'])){
            $query = $query->where('user_id', $user->id);
        }
        
        return $query;
    }

    public function scopeLatestData($query, $userId)
    {
        return $query->whereRaw(
            'id IN (SELECT MAX(id) FROM daily_steps_goals WHERE user_id = ? GROUP BY date)',
            [$userId]
        );
    }

    public function scopeFilter($query, $type)
    {
        return match ($type) {
            'week'  => $query->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereMonth('date', now()->month),
            'year'  => $query->whereYear('date', now()->year),
            'every' => $query,
            default => $query,
        };
    }

    public function scopeLastRecordByDate($query, $userId, $date)
    {
        return $query->where('user_id', $userId)
            ->where('date', $date)
            ->orderBy('id', 'desc');
    }
}
