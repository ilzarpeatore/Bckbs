<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class CategoryDiet extends Model implements HasMedia

{
    use HasFactory, InteractsWithMedia;
    use HasSlug;

    protected $fillable = [ 'title', 'slug', 'status' ];

    public function getSlugOptions() : SlugOptions
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