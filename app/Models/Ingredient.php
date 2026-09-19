<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Ingredient extends Model
{
    use HasSlug,HasFactory;

    protected $fillable = [
        'title', 'slug', 'ingredient_category_id', 'calories_per_gram', 'protein_per_gram', 'fat_per_gram', 'carbs_per_gram', 'density', 'status',
        // Integración FatSecret (2026-09-19) -- fatsecret_serving_id se guarda
        // para que el refresco recalcule siempre con la misma ración, ver
        // docs/FATSECRET_INTEGRATION.md sección 4.2.
        'fatsecret_food_id', 'fatsecret_serving_id', 'fatsecret_synced_at',
    ];

    protected $casts = [
        'calories_per_gram' => 'double',
        'protein_per_gram' => 'double',
        'fat_per_gram' => 'double',
        'carbs_per_gram' => 'double',
        'density' => 'double',
        'fatsecret_synced_at' => 'datetime',
    ];

    public function getSlugOptions() : SlugOptions
    {
        return SlugOptions::create()
                    ->generateSlugsFrom('title')
                    ->saveSlugsTo('slug')
                    ->doNotGenerateSlugsOnUpdate();
    }

    public function ingredientcategory()
    {
        return $this->belongsTo(IngredientCategory::class, 'ingredient_category_id');
    }

    public function unitConversions()
    {
        return $this->hasMany(IngredientUnitConversion::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');  
    }

    public function getCaloriesAttribute()
    {
        return $this->calories_per_gram * 100;
    }

    public function getProteinAttribute()
    {
        return $this->protein_per_gram * 100;
    }

    public function getFatAttribute()
    {
        return $this->fat_per_gram * 100;
    }

    public function getCarbsAttribute()
    {
        return $this->carbs_per_gram * 100;
    }
}
