<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Product extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;
    use HasSlug;
    
    protected $fillable = [ 'title', 'slug', 'description', 'affiliate_link', 'price', 'productcategory_id', 'featured', 'status'];

    protected $casts = [
        'productcategory_id'  => 'integer',
        'price' => 'double',
    ];

    public function productcategory()
    {
        return $this->belongsTo(ProductCategory::class, 'productcategory_id', 'id');
    }

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
