<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipeCategoryMapping extends Model
{
    use HasFactory;

    protected $fillable = [ 'recipe_id', 'recipe_category_id' ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function recipeCategory()
    {
        return $this->belongsTo(RecipeCategory::class, 'recipe_category_id');
    }
}
