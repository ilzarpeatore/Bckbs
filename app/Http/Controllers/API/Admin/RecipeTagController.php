<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\RecipeTag;
use App\Http\Resources\RecipeTagResource;
use Illuminate\Http\Request;

class RecipeTagController extends BaseController
{
    protected function getModelClass(): string
    {
        return RecipeTag::class;
    }

    protected function getResourceClass(): string
    {
        return RecipeTagResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'  => 'required|string|max:255',
            'slug'   => 'sometimes|string|max:255|unique:recipe_tags,slug,' . $id,
            'status' => 'sometimes|in:active,inactive',
            // NOTA: la UI admin real para recipe-tags hoy es la vista Blade
            // legacy (app/Http/Controllers/RecipeTagContoller.php,
            // routes/web.php), no este controlador JSON -- se añade la regla
            // aquí solo para que este endpoint (ya registrado) no rechace
            // `group` si alguna vez se usa.
            'group'  => 'nullable|in:duration,fat_loss,muscle_gain,performance,spain_regional,country,diet,meal_type,other',
        ];
    }
}
