<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Exercise;
use App\Http\Resources\ExerciseResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExerciseController extends BaseController
{
    protected function getModelClass(): string
    {
        return Exercise::class;
    }

    protected function getResourceClass(): string
    {
        return ExerciseResource::class;
    }

    public function index(Request $request)
    {
        $model = $this->getModel();
        $query = $model->query();

        if (method_exists($model, 'scopeSearch')) {
            $query->search($request);
        } elseif ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search, $model) {
                foreach ($model->getFillable() as $field) {
                    if (!in_array($field, ['password', 'remember_token'])) {
                        $q->orWhere($field, 'LIKE', "%{$search}%");
                    }
                }
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', config('constant.PER_PAGE_LIMIT', 10));
        if ($perPage == -1 || $perPage > 5000) {
            $perPage = 5000;
        }

        $items = $query->orderBy('id', 'desc')->paginate($perPage);

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
            'title'           => 'required|string|max:255',
            'slug'            => 'sometimes|string|max:255|unique:exercises,slug,' . $id,
            'instruction'     => 'nullable|string',
            'tips'            => 'nullable|string',
            'video_type'      => 'sometimes|in:youtube,vimeo',
            'video_url'       => 'nullable|string',
            'bodypart_ids'    => 'nullable|string',
            'duration'        => 'nullable|string',
            'sets'            => 'nullable|string',
            'exercise_type'   => ['nullable', Rule::in(array_keys(Exercise::EXERCISE_TYPES))],
            'equipment_id'    => 'nullable|exists:equipment,id',
            'level_id'        => 'nullable|exists:levels,id',
            'is_premium'      => 'sometimes|boolean',
            'seconds_per_rep' => 'nullable|numeric',
            'status'          => 'sometimes|in:active,inactive',
        ];
    }

    protected function afterSave($item, Request $request): void
    {
        if ($request->hasFile('image')) {
            $item->clearMediaCollection('exercise_image');
            $item->addMediaFromRequest('image')->toMediaCollection('exercise_image');
        }
    }
}
