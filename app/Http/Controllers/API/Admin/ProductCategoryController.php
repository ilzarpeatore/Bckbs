<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\ProductCategory;
use App\Http\Resources\ProductCategoryResource;
use Illuminate\Http\Request;

class ProductCategoryController extends BaseController
{
    protected function getModelClass(): string
    {
        return ProductCategory::class;
    }

    protected function getResourceClass(): string
    {
        return ProductCategoryResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title' => 'required|string|max:255',
            'slug'  => 'sometimes|string|max:255|unique:product_categories,slug,' . $id,
        ];
    }
}
