<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Quotes extends Model
{
    use HasFactory;
    use HasSlug;

    protected $fillable = [ 'title', 'slug', 'message', 'date' ];

    public function getSlugOptions() : SlugOptions
    {
        return SlugOptions::create()
                    ->generateSlugsFrom('title')
                    ->saveSlugsTo('slug')
                    ->doNotGenerateSlugsOnUpdate();
    }
}
