<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Diet;
use App\Http\Resources\DietResource;
use App\Http\Resources\DietDetailResource;
use App\Models\UserFavouriteDiet;
use App\Http\Resources\CategoryDietResource;
use App\Models\CategoryDiet;
use App\Models\AssignDiet;
use App\Http\Resources\AssignDietResource;

class DietController extends Controller
{
    public function getList(Request $request)
    {
        $diet = Diet::active()->showAssignPrivateDiet();

        $diet->when(request('title'), function ($q) {
            return $q->where('title', 'LIKE', '%' . request('title') . '%');
        });
        // if( $request->has('is_premium') && isset($request->is_premium) ) {
        //     $diet = $diet->where('is_premium',request('is_premium'));
        // }

        if( $request->has('is_featured') && isset($request->is_featured) ) {
            $diet = $diet->where('is_featured',request('is_featured'));
        }

        if( $request->has('is_premium') && isset($request->is_premium) ) {
            $diet = $diet->where('is_premium', request('is_premium'));
        }

        // $diet->when(request('is_featured'), function ($q) {
        //     return $q->where('is_featured',request('is_featured'));
        // });
        
        $diet->when(request('categorydiet_id'), function ($q) {
            return $q->where('categorydiet_id', request('categorydiet_id'));
        });

        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page))
            {
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ){
                $per_page = $diet->count();
            }
        }

        $diet = $diet->with('categorydiet', 'userFavouriteDiet')->orderBy('title', 'asc')->paginate($per_page);

        $items = DietResource::collection($diet);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];
        
        return json_custom_response($response);
    }

    public function getDetail(Request $request)
    {
        $diet = Diet::where('id',request('id'))->showAssignPrivateDiet()->first();
           
        if( $diet == null )
        {
            return json_message_response( __('message.not_found_entry',['name' => __('message.diet') ]) );
        }

        $diet_data = new DietDetailResource($diet);
            $response = [
                'data' => $diet_data,
            ];
             
        return json_custom_response($response);
        
    }

    /**
     * A single Diet's recipes grouped by meal_type (breakfast/lunch/dinner/snacks)
     * — same shape/design as assigned-meals-summary, but scoped to one Diet
     * instead of pooling the client's whole calendar.
     */
    public function getDietMealItems(Request $request)
    {
        $diet = Diet::where('id', request('id'))->showAssignPrivateDiet()->first();

        if ($diet == null) {
            return json_message_response(__('message.not_found_entry', ['name' => __('message.diet')]));
        }

        $itemsByType = $diet->mealItems()->with('recipe')->get()->groupBy('meal_type');

        $meals = [];
        foreach (config('macro-nutrient.MEAL_TYPE') as $mealType) {
            $meals[$mealType] = ($itemsByType->get($mealType) ?? collect())
                ->filter(fn ($item) => $item->recipe !== null)
                ->values()
                ->map(fn ($item) => new \App\Http\Resources\RecipePlanResource($item->recipe))
                ->all();
        }

        return json_custom_response([
            'diet'  => ['id' => $diet->id, 'title' => $diet->title],
            'meals' => $meals,
        ]);
    }

    public function getUserFavouriteDiet(Request $request)
    {
        $diet = Diet::myDiet()->showAssignPrivateDiet();

        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)) {
            if(is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ) {
                $per_page = $diet->count();
            }
        }

        $diet = $diet->with('categorydiet', 'userFavouriteDiet')->orderByMyFavouriteDesc()->paginate($per_page);

        $items = DietResource::collection($diet);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];
        
        return json_custom_response($response);
    }

    public function userFavouriteDiet(Request $request)
    {
        $user_id = auth()->id();
        $diet_id = $request->diet_id;

        $diet = Diet::where('id', $diet_id )->showAssignPrivateDiet()->first();
        if( $diet == null )
        {
            return json_message_response( __('message.not_found_entry',['name' => __('message.diet') ]) );
        }
        $user_favourite_diet = UserFavouriteDiet::where('user_id', $user_id)->where('diet_id',$diet_id)->first();
        
        if($user_favourite_diet != null) {
            $user_favourite_diet->delete();
            $message = __('message.unfavourite_diet_list');
        } else {
            $data = [
                'user_id'   => $user_id,
                'diet_id'   => $diet_id,
            ];
            
            UserFavouriteDiet::create($data);
            $message = __('message.favourite_diet_list');
        }

        return json_message_response($message);
    }

    public function dashboard()
    {
        $data['category_diet'] = CategoryDiet::active()->orderBy('title','asc')->take(12)->get();
        $data['best_diet'] = Diet::active()->showAssignPrivateDiet()->with('categorydiet', 'userFavouriteDiet')->where('is_featured','yes')->orderBy('title','asc')->take(12)->get();
        $data['diet'] = Diet::active()->showAssignPrivateDiet()->with('categorydiet', 'userFavouriteDiet')->orderBy('title','asc')->take(12)->get();

        $category_diet = CategoryDietResource::collection($data['category_diet']);
        $best_diet = DietResource::collection($data['best_diet']);
        $diet = DietResource::collection($data['diet']);

        $user = auth('sanctum')->user();
        if( $user ) {
            $data['assign_diet'] = AssignDiet::myAssignDiet()->has('diet')->with('diet.categorydiet', 'diet.userFavouriteDiet')->orderBy('id', 'desc')->take(12)->get();
            $assign_diet = AssignDietResource::collection($data['assign_diet']);
        }

        $response = [
            'category_diet' => $category_diet,
            'best_diet'     => $best_diet,
            'diet'          => $diet,
            'assign_diet'   => $assign_diet ?? [],
        ];
        
        return json_custom_response($response);
    }
}
