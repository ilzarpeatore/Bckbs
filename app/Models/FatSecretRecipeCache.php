<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cache-aside de contenido de receta de FatSecret -- NUNCA es la fuente de
 * verdad, solo evita repetir la llamada a la API en una ventana corta. Ver
 * docs/FATSECRET_INTEGRATION.md sección 9 y App\Services\FatSecret\FatSecretRecipeService.
 */
class FatSecretRecipeCache extends Model
{
    protected $primaryKey = 'fatsecret_recipe_id';
    public $incrementing = false;

    protected $fillable = [
        'fatsecret_recipe_id', 'name', 'image_url', 'calories', 'protein', 'fat', 'carbs',
        'number_of_servings', 'preparation_time_min', 'cooking_time_min',
        'directions', 'ingredients', 'fetched_at',
    ];

    protected $casts = [
        'fatsecret_recipe_id'  => 'integer',
        'calories'             => 'double',
        'protein'              => 'double',
        'fat'                  => 'double',
        'carbs'                => 'double',
        'number_of_servings'   => 'double',
        'preparation_time_min' => 'integer',
        'cooking_time_min'     => 'integer',
        'directions'           => 'array',
        'ingredients'          => 'array',
        'fetched_at'           => 'datetime',
    ];

    // Deliberadamente muy por debajo de las 24h que permite la política de
    // FatSecret (ver docs/FATSECRET_INTEGRATION.md sección 0) -- nunca
    // acercarse al límite real.
    public const TTL_HOURS = 6;

    public function isFresh(): bool
    {
        return $this->fetched_at && $this->fetched_at->gt(now()->subHours(self::TTL_HOURS));
    }
}
