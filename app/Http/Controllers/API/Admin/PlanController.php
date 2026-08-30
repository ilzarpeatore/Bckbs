<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Plan;
use App\Http\Resources\PlanResource;
use Illuminate\Http\Request;

class PlanController extends BaseController
{
    protected function getModelClass(): string
    {
        return Plan::class;
    }

    protected function getResourceClass(): string
    {
        return PlanResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string',
            'is_active'       => 'sometimes|boolean',
            'price'           => 'required|numeric|min:0',
            'signup_fee'       => 'nullable|numeric|min:0',
            'currency'        => 'nullable|string|max:3',
            'trial_period'    => 'nullable|integer|min:0',
            'trial_interval'  => 'nullable|in:day,week,month',
            'invoice_period'  => 'nullable|integer|min:1',
            'invoice_interval'=> 'nullable|in:day,week,month,year',
            'grace_period'    => 'nullable|integer|min:0',
            'grace_interval'  => 'nullable|in:day,week',
            'active_subscribers_limit' => 'nullable|integer|min:0',
            'sort_order'      => 'nullable|integer|min:0',
            'training_program_id'     => 'nullable|exists:training_programs,id',
            'meal_plan_template_id'   => 'nullable|exists:meal_plan_templates,id',
            'grants_full_workout_library' => 'sometimes|boolean',
            'grants_full_recipe_library'  => 'sometimes|boolean',
        ];
    }
}
