<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\IngredientUnitConversion;
use App\Http\Resources\IngredientUnitConversionResource;
use Illuminate\Http\Request;

class IngredientUnitConversionController extends BaseController
{
    protected function getModelClass(): string
    {
        return IngredientUnitConversion::class;
    }

    protected function getResourceClass(): string
    {
        return IngredientUnitConversionResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'ingredient_id'       => 'required|exists:ingredients,id',
            'measurement_unit_id' => 'required|exists:measurement_units,id',
            'gram_equivalent'     => 'required|numeric',
        ];
    }
}
