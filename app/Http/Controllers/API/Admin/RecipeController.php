<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Recipe;
use App\Models\RecipeStep;
use App\Http\Resources\RecipeResource;
use Illuminate\Http\Request;

class RecipeController extends BaseController
{
    protected function getModelClass(): string
    {
        return Recipe::class;
    }

    protected function getResourceClass(): string
    {
        return RecipeResource::class;
    }

    public function index(Request $request)
    {
        $query = Recipe::query();

        // Text search across title and description
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'LIKE', "%{$s}%")
                  ->orWhere('description', 'LIKE', "%{$s}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by category IDs (comma-separated or array)
        if ($request->filled('category_ids')) {
            $ids = is_array($request->category_ids) ? $request->category_ids : explode(',', $request->category_ids);
            $query->whereHas('categories', function ($q) use ($ids) {
                $q->whereIn('recipe_categories.id', $ids);
            });
        }

        // Filter by tag IDs (comma-separated or array)
        if ($request->filled('tag_ids')) {
            $ids = is_array($request->tag_ids) ? $request->tag_ids : explode(',', $request->tag_ids);
            $query->whereHas('tags', function ($q) use ($ids) {
                $q->whereIn('recipe_tags.id', $ids);
            });
        }

        // Filter by ingredient name (search in recipe ingredient names)
        if ($request->filled('ingredient')) {
            $ing = $request->ingredient;
            $query->whereHas('recipeIngredients.ingredient', function ($q) use ($ing) {
                $q->where('title', 'LIKE', "%{$ing}%");
            });
        }

        // Default: paginate with max 250 per page
        $perPage = $request->get('per_page', 50);
        if ($perPage == -1 || $perPage > 250) {
            $perPage = 250;
        }

        // Sorting
        $allowedSorts = ['id', 'title', 'calories', 'protein', 'fats', 'carbs', 'preparation_time', 'created_at'];
        $sortBy = in_array($request->get('orderby'), $allowedSorts) ? $request->get('orderby') : 'id';
        $sortOrder = $request->get('order', 'desc') === 'asc' ? 'asc' : 'desc';

        $items = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        $resourceClass = $this->getResourceClass();
        $items = $resourceClass::collection($items);

        $response = [
            'pagination' => json_pagination_response($items),
            'data'       => $items,
        ];

        return json_custom_response($response);
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'            => 'required|string|max:255',
            'slug'             => 'sometimes|string|max:255|unique:recipes,slug,' . $id,
            'preparation_time' => 'nullable|string',
            'type'             => 'nullable|string',
            'meal_type'        => 'nullable|string',
            'description'      => 'nullable|string',
            'calories'         => 'nullable|numeric',
            'protein'          => 'nullable|numeric',
            'fats'             => 'nullable|numeric',
            'carbs'            => 'nullable|numeric',
            'status'           => 'sometimes|in:active,inactive',
            'is_premium'       => 'sometimes|boolean',
        ];
    }

    protected function afterSave($item, Request $request): void
    {
        if ($request->hasFile('recipe_image')) {
            $item->clearMediaCollection('recipe_image');
            $item->addMediaFromRequest('recipe_image')->toMediaCollection('recipe_image');
        }

        if ($request->has('categories')) {
            $item->categories()->sync($request->categories);
        }

        if ($request->has('tags')) {
            $item->tags()->sync($request->tags);
        }
    }

    /**
     * Reordenar pasos de una receta (item 7, auditoria de migracion
     * 2026-09-11) -- misma logica que RecipeController::reorderSteps
     * (Blade): un array ordenado de IDs, sequence = posicion + 1.
     */
    public function reorderSteps(Request $request, $recipeId)
    {
        $request->validate(['ids' => 'required|array']);

        foreach ($request->ids as $key => $stepId) {
            RecipeStep::where('id', $stepId)->where('recipe_id', $recipeId)->update(['sequence' => $key + 1]);
        }

        return json_custom_response(['message' => 'Orden de pasos actualizado.']);
    }
}
