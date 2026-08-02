<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Diet extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;
    use HasSlug;

    protected $fillable = [ 'title', 'slug', 'categorydiet_id', 'calories', 'carbs', 'protein', 'fat', 'servings', 'total_time', 'is_featured', 'status', 'ingredients', 'description', 'is_premium', 'visibility' ];

    protected $casts = [
        'is_premium'        => 'integer',
        'categorydiet_id'   => 'integer',
    ];

    public function categorydiet()
    {
        return $this->belongsTo(CategoryDiet::class, 'categorydiet_id', 'id');
    }

    public function userFavouriteDiet()
    {
        return $this->hasMany(UserFavouriteDiet::class, 'diet_id', 'id');
    }

    public function userAssignDiet()
    {
        return $this->hasMany(AssignDiet::class, 'diet_id', 'id');
    }

    public function mealItems()
    {
        return $this->hasMany(DietMealItem::class, 'diet_id', 'id');
    }

    public function scopeMyDiet($query, $user_id =null)
    {
        $user = auth()->user();

        if($user->hasRole(['user'])){
            $query = $query->whereHas('userFavouriteDiet', function ($q) use($user) {
                $q->where('user_id', $user->id);
            });
        }

        if($user_id != null) {
            return $query->whereHas('userAssignDiet', function ($q) use($user_id) {
                $q->where('user_id', $user_id);
            });
             
        }

        return $query;
    }

    public function scopeMyAssignDiet($query, $user_id =null)
    {
        $user = auth()->user();
        if($user->hasRole(['user'])){
            $query = $query->whereHas('userAssignDiet', function ($q) use($user) {
                $q->where('user_id', $user->id);
            });
        }

        return $query;
    }

    public function scopeMyNotAssignDiet($query)
    {
        return $query->whereDoesntHave('userAssignDiet', function ($q) {
            $q->where('user_id', request('user_id'));
        });
    }

    protected static function boot() {
        parent::boot();
        static::deleted(function ($row) {
            $row->userFavouriteDiet()->delete();
            $row->userAssignDiet()->delete();
        });
    }

    public function getIsFavoriteAttribute()
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return $this->userFavouriteDiet()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function getSlugOptions() : SlugOptions
    {
        return SlugOptions::create()
                    ->generateSlugsFrom('title')
                    ->saveSlugsTo('slug')
                    ->doNotGenerateSlugsOnUpdate();
    }

    public function isAccessible($subscription_system = null, $is_premium = 0, $user = null)
    {
        $is_accessible = false;

        if( $subscription_system == 1 && $is_premium) {
            if( $user != null && $user->is_subscribe) {
                $is_accessible = true;
            }
        } else {
            $is_accessible = true;
        }

        return $is_accessible;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');  
    }

    public function scopeShowAssignPrivateDiet($query, $user_id = null)
    {
        $user = auth('sanctum')->user();

        return $query->where(function ($q) use ($user) {

            $q->whereIn('visibility', ['public', 'all']);
            if ($user) {
                $q->orWhere(function ($q1) use ($user) {
                    $q1->where('visibility', 'private')
                    ->whereHas('userAssignDiet', fn ($q2) =>
                        $q2->where('user_id', $user->id)
                    );
                });
            }
        });
    }
    public function scopeOrderByMyFavouriteDesc($query)
    {
        return $query
            ->withMax(
                ['userFavouriteDiet as favourited_at' => fn ($q) =>
                    $q->where('user_id', auth()->id())
                ],
                'created_at'
            )
            ->orderByDesc('favourited_at');
    }
    public function scopeOrderByMyAssignDesc($query)
    {
        return $query
            ->withMax(
                ['userAssignDiet as assigned_at' => function ($q) {
                    $q->where('user_id', auth()->id());
                }],
                'created_at'
            )
            ->orderByDesc('assigned_at');
    }
}
