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
    // FIX (2026-09-19, bug real detectado probando el flujo completo):
    // Eloquent adivina el nombre de tabla como `fat_secret_recipe_caches`
    // (snake_case + plural del nombre de la clase) -- la migración creó
    // `fatsecret_recipe_cache` (una sola palabra, singular), sin esto la
    // primera consulta real explota con "Base table or view not found".
    protected $table = 'fatsecret_recipe_cache';
    protected $primaryKey = 'fatsecret_recipe_id';
    public $incrementing = false;

    // name/directions/ingredients contienen el texto YA TRADUCIDO (o el
    // original en inglés si la traducción no está disponible/falla) --
    // *_en guarda siempre el inglés real de FatSecret aparte, ver
    // docs/FATSECRET_INTEGRATION.md sección 10 y FatSecretRecipeService.
    protected $fillable = [
        'fatsecret_recipe_id', 'name', 'name_en', 'image_url', 'calories', 'protein', 'fat', 'carbs',
        'number_of_servings', 'preparation_time_min', 'cooking_time_min',
        'directions', 'directions_en', 'ingredients', 'ingredients_en', 'is_translated', 'fetched_at',
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
        'directions_en'        => 'array',
        'ingredients'          => 'array',
        'ingredients_en'       => 'array',
        'is_translated'        => 'boolean',
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
