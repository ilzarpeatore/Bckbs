<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipeStep extends Model
{
    use HasFactory;

    protected $fillable = [ 'recipe_id', 'instruction', 'sequence' ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }
}
