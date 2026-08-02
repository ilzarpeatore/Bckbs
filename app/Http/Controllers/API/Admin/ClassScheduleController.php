<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\ClassSchedule;
use App\Http\Resources\ClassScheduleResource;
use Illuminate\Http\Request;

class ClassScheduleController extends BaseController
{
    protected function getModelClass(): string
    {
        return ClassSchedule::class;
    }

    protected function getResourceClass(): string
    {
        return ClassScheduleResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'class_name'    => 'required|string|max:255',
            'workout_id'    => 'nullable|exists:workouts,id',
            'workout_title' => 'nullable|string',
            'workout_type'  => 'nullable|string',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'start_time'    => 'required|string',
            'end_time'      => 'required|string',
            'name'          => 'nullable|string',
            'link'          => 'nullable|string',
            'is_paid'       => 'sometimes|boolean',
            'price'         => 'nullable|numeric',
        ];
    }
}
