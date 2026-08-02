<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFavouriteDiet extends Model
{
    use HasFactory;

    protected $fillable = [ 'user_id', 'diet_id' ];
    
    protected $casts = [
        'user_id'   => 'integer',
        'diet_id'   => 'integer',
    ];

    public function scopeMyFavouriteDiet($query, $user_id = null)
    {
        if( $user_id != null ) {
            $query = $query->where('user_id', $user_id);             
        }

        return $query;
    }
}
