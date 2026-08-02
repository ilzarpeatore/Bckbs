<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFavouriteWorkoutTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'workout_template_id'];

    protected $casts = [
        'user_id'             => 'integer',
        'workout_template_id' => 'integer',
    ];
}
