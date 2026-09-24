<?php

namespace App\Models;

use App\Support\FuzzySearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Recipe extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, HasSlug;

    protected $fillable = [ 'title', 'slug', 'preparation_time', 'type', 'meal_type', 'description', 'calories', 'protein', 'fats', 'carbs', 'status', 'is_premium', 'fatsecret_recipe_id' ];

    protected $casts = [
        'calories' => 'double',
        'protein' => 'double',
        'fats' => 'double',
        'carbs' => 'double',
        'preparation_time' => 'integer',
        'is_premium' => 'boolean',
    ];

    public function getSlugOptions() : SlugOptions
    {
        return SlugOptions::create()
                    ->generateSlugsFrom('title')
                    ->saveSlugsTo('slug')
                    ->doNotGenerateSlugsOnUpdate();
    }
    public function categories()
    {
        return $this->belongsToMany(RecipeCategory::class, 'recipe_category_mappings', 'recipe_id', 'recipe_category_id')->withTimestamps();
    }

    public function tags()
    {
        return $this->belongsToMany(RecipeTag::class, 'recipe_tag_mappings', 'recipe_id', 'recipe_tag_id')->withTimestamps();
    }

    public function steps()
    {
        return $this->hasMany(RecipeStep::class)->orderBy('sequence');
    }

    public function recipeIngredients()
    {
        return $this->hasMany(RecipeIngredient::class);
    }
    
    public function recipeCategoryMapping()
    {
        return $this->hasMany(RecipeCategoryMapping::class);
    }

    public function recipeTagMapping()
    {
        return $this->hasMany(RecipeTagMapping::class);
    }

    public function getMealTypeAttribute($value)
    {
        return isset($value) ? json_decode($value, true) : null; 
    }

    public function setMealTypeAttribute($value)
    {
        $this->attributes['meal_type'] = isset($value) ? json_encode($value) : null;
    }
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
   
    protected static function boot() {
        parent::boot();
        static::deleted(function ($row) {
            $row->steps()->delete();
            $row->recipeIngredients()->delete();
            $row->recipeCategoryMapping()->delete();
            $row->recipeTagMapping()->delete();
        });
    }

    public function scopeRecipeFilter($query)
    {
        $query->where('status', 'active');

        $query->when(request('title'), function ($q) {
            FuzzySearch::apply($q, ['title'], request('title'));
        });

        // recipes.meal_type (columna JSON) esta vacia en todo el catalogo real -
        // la categorizacion real vive en recipe_category_mappings, asi que se
        // traduce cada meal_type pedido a su recipe_categories.id (config
        // macro-nutrient.MEAL_TYPE_CATEGORY) y se filtra por esa relacion, en
        // vez de comparar contra una columna que nunca se rellena. Antes esto
        // dejaba "Añadir comida" sin resultados siempre, para cualquier busqueda.
        $query->when(is_array(request('meal_type')), function ($q) {
            $categoryIds = collect(request('meal_type'))
                ->map(fn ($type) => config('macro-nutrient.MEAL_TYPE_CATEGORY.' . $type))
                ->filter()
                ->values();
            if ($categoryIds->isNotEmpty()) {
                $q->whereHas('categories', function ($sub) use ($categoryIds) {
                    $sub->whereIn('recipe_category_id', $categoryIds);
                });
            }
        });

        $query->when(is_array(request('recipe_category_ids')), function ($q) {
            $q->whereHas('categories', function ($sub) {
                $sub->whereIn('recipe_category_id', request('recipe_category_ids'));
            });
        });

        $query->when(is_array(request('recipe_tag_ids')), function ($q) {
            $q->whereHas('tags', function ($t) {
                $t->whereIn('recipe_tag_id', request('recipe_tag_ids'));
            });
        });

        $query->when(request('start_calories'),
            fn($q) => $q->where('calories', '>=', request('start_calories'))
        );

        $query->when(request('end_calories'),
            fn($q) => $q->where('calories', '<=', request('end_calories'))
        );

        $query->when(request('start_protein'),
            fn($q) => $q->where('protein', '>=', request('start_protein'))
        );

        $query->when(request('end_protein'),
            fn($q) => $q->where('protein', '<=', request('end_protein'))
        );

        $query->when(request('start_carbs'),
            fn($q) => $q->where('carbs', '>=', request('start_carbs'))
        );

        $query->when(request('end_carbs'),
            fn($q) => $q->where('carbs', '<=', request('end_carbs'))
        );

        $query->when(request('start_fats'),
            fn($q) => $q->where('fats', '>=', request('start_fats'))
        );

        $query->when(request('end_fats'),
            fn($q) => $q->where('fats', '<=', request('end_fats'))
        );

        $query->when(request('min_preparation_time'),
            fn($q) => $q->where('preparation_time', '>=', request('min_preparation_time'))
        );

        $query->when(request('max_preparation_time'),
            fn($q) => $q->where('preparation_time', '<=', request('max_preparation_time'))
        );
        
        $query->when(request()->has('is_favourite'), function ($q) {
            $user_id = auth('sanctum')->id();
            if(request('is_favourite') == 1){
                $q->whereHas('userFavouriteRecipe', function ($fav) use ($user_id) {
                    $fav->where('user_id', $user_id);
                });
            }
        });

        $orderby = request('orderby', 'id');
        $order   = strtolower(request('order', 'desc'));
        $order   = in_array($order, ['asc','desc']) ? $order : 'desc';

        $query->orderBy($orderby, $order);
        
        return $query;
    }

    public function userFavouriteRecipe()
    {
        return $this->hasMany(UserFavouriteRecipe::class, 'recipe_id', 'id');
    }

    public function scopeMyFavouriteRecipe($query)
    {
        $user = auth()->user();

        if($user->hasRole(['user'])){
            $query = $query->whereHas('userFavouriteRecipe', function ($q) use($user) {
                $q->where('user_id', $user->id);
            });
        }
    }

    public function scopeOrderByMyFavouriteDesc($query)
    {
        return $query
            ->withMax(
                ['userFavouriteRecipe as favourited_at' => fn ($q) =>
                    $q->where('user_id', auth()->id())
                ],
                'created_at'
            )
            ->orderByDesc('favourited_at');
    }

}
