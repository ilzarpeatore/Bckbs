<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkoutDayBlock extends Model
{
    use HasFactory;

    protected $fillable = ['workout_day_id', 'title', 'order'];

    protected $casts = ['order' => 'integer'];

    public function workoutDay()
    {
        return $this->belongsTo(WorkoutDay::class, 'workout_day_id', 'id');
    }

    public function exercises()
    {
        return $this->hasMany(WorkoutDayExercise::class, 'workout_day_block_id', 'id')
            ->orderBy('sequence');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleted(function ($block) {
            $block->exercises()->update(['workout_day_block_id' => null]);
        });
    }
}
