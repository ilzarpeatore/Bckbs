<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\UnitConversionService;

class RecipeIngredient extends Model
{
    use HasFactory;

    protected $fillable = [ 'recipe_id', 'ingredient_id', 'measurement_unit_id', 'quantity', 'quantity_grams', 'amount', 'calories', 'protein', 'fats', 'carbs' ];

    protected $casts = [
        'quantity' => 'double',
        'quantity_grams' => 'double',
        'amount' => 'double',
        'calories' => 'double',
        'protein' => 'double',
        'fats' => 'double',
        'carbs' => 'double',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function measurementUnit()
    {
        return $this->belongsTo(MeasurementUnit::class);
    }

    public function getDisplayQuantityAttribute()
    {
        $unit = $this->measurementUnit;
        $unitType = optional($unit)->unit_type ?? 'weight';
        if ($unitType === 'count') {
            $label = $unit->symbol ?? $unit->title ?? '';
            $qty = round((float) ($this->quantity ?? 0), 2);
            return $label !== '' ? ($qty . ' ' . $label) : (string) $qty;
        }

        return UnitConversionService::format(
            $this->quantity_grams, 
            $unitType,
            'metric',
            $this->ingredient
        );
    }
}
