<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AssignWorkout extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = ['user_id','workout_id'];

    protected $casts = [
            'user_id'      => 'integer',
            'workout_id'   => 'integer',
        ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function workout()
    {
        return $this->belongsTo(Workout::class, 'workout_id', 'id');
    }

    public function scopeMyAssignWorkout($query)
    {
        $user = auth('sanctum')->user();

        return $query->where('user_id', $user->id);
        
    }
}
