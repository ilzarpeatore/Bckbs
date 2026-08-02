<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class MeasurementUnit extends Model
{
    use HasFactory, HasSlug;

    protected $fillable = [ 'title', 'symbol', 'unit_type', 'base_conversion_factor', 'is_standard', 'slug', 'status' ];

    protected $casts = [
        'base_conversion_factor' => 'double',
        'is_standard' => 'boolean',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

}
