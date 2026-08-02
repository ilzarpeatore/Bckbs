<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserFavouriteRecipe extends Model
{
    protected $fillable = [ 'user_id', 'recipe_id' ];
    
    protected $casts = [
        'user_id'   => 'integer',
        'recipe_id' => 'integer',
    ];

    public function scopeMyFavouriteRecipe($query)
    {
        $user = auth()->user();
        
        if( isset($user) && $user->hasRole(['user']) ) {
            $query = $query->where('user_id', $user->id);
        }

        return $query;
    }
}
