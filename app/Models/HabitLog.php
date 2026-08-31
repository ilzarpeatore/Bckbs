<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HabitLog extends Model
{
    use HasFactory;

    protected $fillable = ['habit_id', 'date', 'value_logged', 'is_completed'];

    protected $casts = [
        'date'         => 'date',
        'is_completed' => 'boolean',
        'value_logged' => 'float',
    ];

    public function habit()
    {
        return $this->belongsTo(Habit::class, 'habit_id', 'id');
    }
}
