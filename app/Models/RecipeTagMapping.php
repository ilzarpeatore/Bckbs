<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipeTagMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_id',
        'recipe_tag_id',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function tag()
    {
        return $this->belongsTo(RecipeTag::class, 'recipe_tag_id');
    }
}
