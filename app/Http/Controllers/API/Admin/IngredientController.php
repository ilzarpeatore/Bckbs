<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Ingredient;
use App\Http\Resources\IngredientResource;
use Illuminate\Http\Request;

class IngredientController extends BaseController
{
    protected function getModelClass(): string
    {
        return Ingredient::class;
    }

    protected function getResourceClass(): string
    {
        return IngredientResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'                   => 'required|string|max:255',
            'slug'                    => 'sometimes|string|max:255|unique:ingredients,slug,' . $id,
            'ingredient_category_id'  => 'required|exists:ingredient_categories,id',
            'calories_per_gram'       => 'required|numeric',
            'protein_per_gram'        => 'required|numeric',
            'fat_per_gram'            => 'required|numeric',
            'carbs_per_gram'          => 'required|numeric',
            'density'                 => 'nullable|numeric',
            'status'                  => 'sometimes|in:active,inactive',
            // Integración FatSecret (2026-09-19) -- opcionales, el admin
            // sigue pudiendo crear/editar un ingrediente 100% a mano sin
            // rellenar nada de esto. Ver docs/FATSECRET_INTEGRATION.md.
            'fatsecret_food_id'       => 'nullable|integer',
            'fatsecret_serving_id'    => 'nullable|integer',
            'fatsecret_synced_at'     => 'nullable|date',
        ];
    }
}
