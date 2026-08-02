<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

abstract class BaseController extends Controller
{
    abstract protected function getModelClass(): string;

    abstract protected function getResourceClass(): string;

    protected function getModel(): Model
    {
        $class = $this->getModelClass();
        return new $class();
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
        if ($perPage == -1 || $perPage > 250) {
            $perPage = 250;
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

    public function store(Request $request)
    {
        $model = $this->getModel();
        $rules = $this->getValidationRules($request, null);

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return json_custom_response([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $item = $model->create($request->all());
            $this->afterSave($item, $request);
        } catch (\Exception $e) {
            report($e);
            return json_message_response('Failed to create record.', 500);
        }

        $response = [
            'message' => __('message.save_form', ['form' => $this->getEntityName()]),
            'data'    => $item,
        ];

        return json_custom_response($response, 201);
    }

    public function show($id)
    {
        $model = $this->getModel();
        $item = $model->find($id);

        if (!$item) {
            return json_message_response(
                __('message.not_found_entry', ['name' => $this->getEntityName()]),
                404
            );
        }

        $resourceClass = $this->getResourceClass();
        $response = [
            'data' => new $resourceClass($item),
        ];

        return json_custom_response($response);
    }

    public function update(Request $request, $id)
    {
        $model = $this->getModel();
        $item = $model->find($id);

        if (!$item) {
            return json_message_response(
                __('message.not_found_entry', ['name' => $this->getEntityName()]),
                404
            );
        }

        $rules = $this->getValidationRules($request, $id);

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return json_custom_response([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $item->update($request->all());
            $this->afterSave($item, $request);
        } catch (\Exception $e) {
            report($e);
            return json_message_response('Failed to update record.', 500);
        }

        $resourceClass = $this->getResourceClass();

        $response = [
            'message' => __('message.updated'),
            'data'    => new $resourceClass($item),
        ];

        return json_custom_response($response);
    }

    public function destroy($id)
    {
        $model = $this->getModel();
        $item = $model->find($id);

        if (!$item) {
            return json_message_response(
                __('message.not_found_entry', ['name' => $this->getEntityName()]),
                404
            );
        }

        $item->delete();

        return json_message_response(__('message.deleted'));
    }

    public function updateStatus(Request $request, $id)
    {
        $model = $this->getModel();
        $item = $model->find($id);

        if (!$item) {
            return json_message_response(
                __('message.not_found_entry', ['name' => $this->getEntityName()]),
                404
            );
        }

        $request->validate([
            'status' => 'required|in:active,inactive,banned',
        ]);

        $item->update(['status' => $request->status]);

        $message = __('message.update_form', ['form' => __('message.status')]);

        return json_custom_response([
            'message' => $message,
            'data'    => $item,
        ]);
    }

    protected function getValidationRules(Request $request, ?int $id): array
    {
        return [];
    }

    protected function getEntityName(): string
    {
        $class = class_basename($this->getModelClass());
        return strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $class));
    }

    protected function afterSave(Model $item, Request $request): void
    {
        // Override in child controllers for media, relationships, etc.
    }
}
