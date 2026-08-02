<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Package;
use App\Http\Resources\PackageResource;
use Illuminate\Http\Request;

class PackageController extends BaseController
{
    protected function getModelClass(): string
    {
        return Package::class;
    }

    protected function getResourceClass(): string
    {
        return PackageResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'name'           => 'required|string|max:255',
            'duration_unit'  => 'required|in:day,week,month,year',
            'duration'       => 'required|integer',
            'price'          => 'required|numeric',
            'description'    => 'nullable|string',
            'status'         => 'sometimes|in:active,inactive',
            'training_program_id'   => 'nullable|exists:training_programs,id',
            'meal_plan_template_id' => 'nullable|exists:meal_plan_templates,id',
            'grants_full_workout_library' => 'sometimes|boolean',
            'grants_full_recipe_library'  => 'sometimes|boolean',
        ];
    }
}
