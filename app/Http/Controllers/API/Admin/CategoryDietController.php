<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\CategoryDiet;
use App\Http\Resources\CategoryDietResource;
use Illuminate\Http\Request;

class CategoryDietController extends BaseController
{
    protected function getModelClass(): string
    {
        return CategoryDiet::class;
    }

    protected function getResourceClass(): string
    {
        return CategoryDietResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'  => 'required|string|max:255',
            'slug'   => 'sometimes|string|max:255|unique:category_diets,slug,' . $id,
            'status' => 'sometimes|in:active,inactive',
        ];
    }
}
