<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\PlanFeature;
use App\Http\Resources\PlanFeatureResource;
use Illuminate\Http\Request;

class PlanFeatureController extends BaseController
{
    protected function getModelClass(): string
    {
        return PlanFeature::class;
    }

    protected function getResourceClass(): string
    {
        return PlanFeatureResource::class;
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [
            'plan_id' => 'required_without:id|exists:plans,id',
            'name' => 'required|string|max:255',
            'value' => 'required|string|max:255',
            'description' => 'nullable|string',
            'resettable_period' => 'nullable|integer|min:0',
            'resettable_interval' => 'nullable|in:day,week,month,year',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }

    public function index(Request $request)
    {
        $query = PlanFeature::query();

        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }

        $perPage = $request->get('per_page', 50);
        $items = $query->orderBy('sort_order')->paginate($perPage);
        $items = PlanFeatureResource::collection($items);

        return json_custom_response([
            'pagination' => json_pagination_response($items),
            'data' => $items,
        ]);
    }
}
