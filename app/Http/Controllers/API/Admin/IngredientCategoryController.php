<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\IngredientCategory;
use App\Http\Resources\IngredientCategoryResource;
use Illuminate\Http\Request;

class IngredientCategoryController extends BaseController
{
    protected function getModelClass(): string
    {
        return IngredientCategory::class;
    }

    protected function getResourceClass(): string
    {
        return IngredientCategoryResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'  => 'required|string|max:255',
            'slug'   => 'sometimes|string|max:255|unique:ingredient_categories,slug,' . $id,
            'status' => 'sometimes|in:active,inactive',
        ];
    }
}
