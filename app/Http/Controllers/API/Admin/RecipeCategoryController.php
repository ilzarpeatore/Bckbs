<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\RecipeCategory;
use App\Http\Resources\RecipeCategoryResource;
use Illuminate\Http\Request;

class RecipeCategoryController extends BaseController
{
    protected function getModelClass(): string
    {
        return RecipeCategory::class;
    }

    protected function getResourceClass(): string
    {
        return RecipeCategoryResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'  => 'required|string|max:255',
            'slug'   => 'sometimes|string|max:255|unique:recipe_categories,slug,' . $id,
            'status' => 'sometimes|in:active,inactive',
        ];
    }
}
