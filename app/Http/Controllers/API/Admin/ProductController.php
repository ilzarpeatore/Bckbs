<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Product;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;

class ProductController extends BaseController
{
    protected function getModelClass(): string
    {
        return Product::class;
    }

    protected function getResourceClass(): string
    {
        return ProductResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'              => 'required|string|max:255',
            'slug'               => 'sometimes|string|max:255|unique:products,slug,' . $id,
            'description'        => 'nullable|string',
            'affiliate_link'     => 'nullable|string',
            'price'              => 'nullable|numeric',
            'productcategory_id' => 'nullable|exists:product_categories,id',
            'featured'           => 'sometimes|boolean',
            'status'             => 'sometimes|in:active,inactive',
        ];
    }

    protected function afterSave($item, Request $request): void
    {
        if ($request->hasFile('image')) {
            $item->clearMediaCollection('image');
            $item->addMediaFromRequest('image')->toMediaCollection('image');
        }
    }
}
