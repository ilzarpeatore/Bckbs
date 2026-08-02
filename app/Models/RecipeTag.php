<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class RecipeTag extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, HasSlug;
    protected $fillable = [ 'title', 'slug', 'status' ];

    public function getSlugOptions() : SlugOptions
    {
        return SlugOptions::create()
                    ->generateSlugsFrom('title')
                    ->saveSlugsTo('slug')
                    ->doNotGenerateSlugsOnUpdate();
    }

    public function recipes()
    {
        return $this->belongsToMany(Recipe::class, 'recipe_tag_mappings', 'recipe_tag_id', 'recipe_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
