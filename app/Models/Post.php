<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Post extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;
    use HasSlug;

    protected $fillable = [
        'title', 'slug', 'tags_id', 'category_ids', 'blog_category_id',
        'datetime', 'status', 'channel', 'is_featured', 'description', 'content', 'bibliography',
    ];

    protected $casts = [
        'datetime' => 'datetime',
    ];

    public function getTagsIdAttribute($value)
    {
        return isset($value) ? json_decode($value, true) : null;
    }

    public function setTagsIdAttribute($value)
    {
        $this->attributes['tags_id'] = isset($value) ? json_encode($value) : null;
    }

    public function getCategoryIdsAttribute($value)
    {
        return isset($value) ? json_decode($value, true) : null;
    }

    public function setCategoryIdsAttribute($value)
    {
        $this->attributes['category_ids'] = isset($value) ? json_encode($value) : null;
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function scopePublish($query)
    {
        return $query->where('status', 'publish');
    }

    /**
     * Filtra por canal de publicación ('app'/'web') incluyendo siempre los
     * posts marcados 'both' -- un post 'both' debe verse en los dos
     * consumidores, no solo en el que coincide literalmente.
     */
    public function scopeChannel($query, string $channel)
    {
        return $query->whereIn('channel', [$channel, 'both']);
    }

    public function blogCategory()
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }
}
