<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFavouriteWorkout extends Model
{
    use HasFactory;

    protected $fillable = [ 'user_id', 'workout_id' ];
    
    protected $casts = [
        'user_id'      => 'integer',
        'workout_id'   => 'integer',
    ];

    public function scopeMyFavouriteWorkout($query, $user_id = null)
    {
        if( $user_id != null ) {
            $query = $query->where('user_id', $user_id);             
        }

        return $query;
    }
}
