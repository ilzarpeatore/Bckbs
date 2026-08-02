<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\MeasurementUnit;
use App\Http\Resources\MeasurementUnitResource;
use Illuminate\Http\Request;

class MeasurementUnitController extends BaseController
{
    protected function getModelClass(): string
    {
        return MeasurementUnit::class;
    }

    protected function getResourceClass(): string
    {
        return MeasurementUnitResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'title'                   => 'required|string|max:255',
            'symbol'                  => 'required|string|max:50',
            'unit_type'               => 'required|in:weight,volume,count',
            'base_conversion_factor'  => 'required|numeric',
            'is_standard'             => 'sometimes|boolean',
            'slug'                    => 'sometimes|string|max:255|unique:measurement_units,slug,' . $id,
            'status'                  => 'sometimes|in:active,inactive',
        ];
    }
}
