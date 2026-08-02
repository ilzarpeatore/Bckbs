<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IngredientUnitConversion extends Model
{
    use HasFactory;

    protected $fillable = [ 'ingredient_id', 'measurement_unit_id', 'gram_equivalent' ];

    protected $casts = [
        'gram_equivalent' => 'double',
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function measurementUnit()
    {
        return $this->belongsTo(MeasurementUnit::class);
    }
}
