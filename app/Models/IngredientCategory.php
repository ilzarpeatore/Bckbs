<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class IngredientCategory extends Model
{
    use HasSlug ,HasFactory;

    protected $fillable = [ 'title', 'slug', 'status' ];

    public function getSlugOptions() : SlugOptions
    {
        return SlugOptions::create()
                    ->generateSlugsFrom('title')
                    ->saveSlugsTo('slug')
                    ->doNotGenerateSlugsOnUpdate();
    }

    public function ingredients()
    {
        return $this->hasMany(Ingredient::class);
    }
}
