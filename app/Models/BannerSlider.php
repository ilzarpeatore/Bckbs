<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class BannerSlider extends Model implements HasMedia
{
    use HasSlug, InteractsWithMedia;
    
    protected $fillable = [ 'title', 'slug', 'status', 'workout_id', 'type', 'url' ];

    public function getSlugOptions() : SlugOptions
    {
        return SlugOptions::create()
                    ->generateSlugsFrom('title')
                    ->saveSlugsTo('slug')
                    ->doNotGenerateSlugsOnUpdate();
    }
    public function workout()
    {
        return $this->belongsTo(Workout::class, 'workout_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

}
