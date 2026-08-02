<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShoppingListItemResource;
use App\Http\Resources\ShoppingListResource;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Services\DailyPlanShoppingListService;
use Illuminate\Http\Request;
use App\Models\MeasurementUnit;
use App\Http\Requests\StoreShoppingListItemRequest;
use App\Http\Requests\StoreShoppingListRequest;
use App\Http\Requests\UpdateShoppingListItemRequest;
use App\Http\Resources\MeasurementUnitResource;
use App\Http\Resources\ShoppingDetailResource;

class ShoppingListController extends Controller
{
    public function getList(Request $request)
    {
        $lists = ShoppingList::myShoppingList()->withCount('items');

        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page))
            {
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ){
                $per_page = $lists->count();
            }
        }
        $lists = $lists->orderByDesc('id')->paginate($per_page);

        $items = ShoppingListResource::collection($lists);

        return json_custom_response([
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ]);
    }

    public function getDetail(Request $request)
    {
        $list = ShoppingList::with(['items.ingredient.ingredientcategory', 'items.measurementUnit'])
                ->myShoppingList()
                ->find($request->id);

        if (!$list) {
            return json_message_response(__('message.not_found_entry', ['name' => __('message.shopping_list')]), 400);
        }

        return json_custom_response([
            'data' => new ShoppingDetailResource($list)
        ]);
    }

    public function generateFromDailyPlan(StoreShoppingListRequest $request, DailyPlanShoppingListService $service)
    {
        $refreshList = null;
        if ($request->filled('shopping_list_id')) {
            $refreshList = ShoppingList::myShoppingList()->find($request->shopping_list_id);
            if (!$refreshList) {
                return json_message_response(__('message.not_found_entry', ['name' => __('message.shopping_list')]), 400);
            }
        }

        $dailyPlanId = $request->daily_plan_id ?: optional($refreshList)->daily_plan_id;
        $start_date = $request->start_date ?: optional($refreshList)->start_date;
        $end_date = $request->end_date ?: optional($refreshList)->end_date;
        $servings = $request->servings ?: optional($refreshList)->servings ?? 1;

        if (!$dailyPlanId && !($start_date && $end_date)) {
            return json_message_response(__('validation.required', ['attribute' => 'daily_plan_id or date range']), 400);
        }

        $user = auth()->user();

        $list = $service->generate($user, [
            'daily_plan_id'     => $dailyPlanId,
            'start_date'        => $start_date,
            'end_date'          => $end_date,
            'meal_types'        => $request->meal_types,
            'is_complete_only'  => $request->has('is_complete_only') ? (bool) $request->is_complete_only : true,
            'title'             => $request->title ?: optional($refreshList)->title,
            'servings'          => $servings,
        ], $refreshList);

        return json_custom_response([
            'message' => __('message.save_form', ['form' => __('message.shopping_list')]),
            'data' => new ShoppingListResource($list),
        ]);
    }

    public function deleteShoppingList(Request $request)
    {
        $list = ShoppingList::myShoppingList()->find($request->id);
        if (!$list) {
            return json_message_response(__('message.not_found_entry', ['name' => __('message.shopping_list')]), 400);
        }

        $list->delete();

        return json_message_response(__('message.delete_form', ['form' => __('message.shopping_list')]));
    }

    public function toggleItem(Request $request)
    {
        $item = ShoppingListItem::whereHas('shoppingList', function ($q) {
            $q->myShoppingList();
        })->find($request->item_id);

        if (!$item) {
            return json_message_response(__('message.not_found_entry', ['name' => __('message.shopping_list')]), 400);
        }

        $item->update(['is_checked' => (bool) $request->is_checked]);

        return json_custom_response([
            'message' => __('message.update_form', ['form' => __('message.shopping_list')]),
            'data' => new ShoppingListItemResource($item->fresh(['ingredient', 'measurementUnit'])),
        ]);
    }

    public function deleteItem(Request $request)
    {
        $item = ShoppingListItem::whereHas('shoppingList', function ($q) {
            $q->myShoppingList();
        })->find($request->item_id);

        if (!$item) {
            return json_message_response(__('message.not_found_entry', ['name' => __('message.shopping_list')]), 400);
        }

        $item->delete();

        return json_message_response(__('message.delete_form', ['form' => __('message.shopping_list')]));
    }

    public function addCustomItem(StoreShoppingListItemRequest $request)
    {        
        $list = ShoppingList::myShoppingList()->find($request->shopping_list_id);
        
        if (!$list) {
            return json_message_response(__('message.not_found_entry', ['name' => __('message.shopping_list')]), 400);
        }

        $item = ShoppingListItem::create([
            'shopping_list_id' => $list->id,
            'custom_item_name' => $request->custom_item_name,
            'display_quantity' => $request->display_quantity ?? 1,
            'measurement_unit_id'  => $request->measurement_unit_id,
            'is_checked' => 0,
            'manually_added' => 1,
        ]);

        return json_custom_response([
            'message' => __('message.save_form', ['form' => __('message.shopping_list')]),
            'data' => new ShoppingListItemResource($item->fresh(['ingredient', 'measurementUnit'])),
        ]);
    }

    public function updateItem(UpdateShoppingListItemRequest $request)
    {
        $item = ShoppingListItem::whereHas('shoppingList', function ($q) {
            $q->myShoppingList();
        })->find($request->item_id);

        if (!$item) {
            return json_message_response(__('message.not_found_entry', ['name' => __('message.shopping_list')]), 400);
        }

        $data = [];

        if ($request->has('display_quantity')) {
            $data['display_quantity'] = $request->display_quantity;
        }

        if ($request->has('measurement_unit_id')) {
            $data['measurement_unit_id'] = $request->measurement_unit_id;
        }

        if ($request->has('is_checked')) {
            $data['is_checked'] = (bool) $request->is_checked;
        }

        if ($request->has('custom_item_name') && (bool) $item->manually_added) {
            $data['custom_item_name'] = $request->custom_item_name;
        }

        if (empty($data)) {
            return json_message_response(__('validation.required', ['attribute' => 'at least one updatable field']), 400);
        }

        $item->update($data);

        return json_custom_response([
            'message' => __('message.update_form', ['form' => __('message.shopping_list')]),
            'data' => new ShoppingListItemResource($item->fresh(['ingredient', 'measurementUnit'])),
        ]);
    }

    public function getMeasurementUnit(Request $request)
    {
        $measurement_unit = MeasurementUnit::active();
        
        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)) {
            if(is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ) {
                $per_page = $measurement_unit->count();
            }
        }
        $measurement_unit = $measurement_unit->paginate($per_page);

        $items = MeasurementUnitResource::collection($measurement_unit);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];
        
        return json_custom_response($response);
    }
}
