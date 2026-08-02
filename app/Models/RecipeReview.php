<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipeReview extends Model
{
    use HasFactory;
    protected $fillable = [ 'user_id', 'recipe_id', 'rating', 'review' ];

    protected $casts = [
        'user_id'   => 'integer',
        'recipe_id' => 'integer',
        'rating'    => 'double',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class,'recipe_id','id');
    }

    public function scopeMyReviewRecipe($query)
    {
        $user = auth()->user();
              
        if( isset($user) && $user->hasRole(['user']) ) {
            $query = $query->where('user_id', $user->id);
        }

        return $query;
    }
}
