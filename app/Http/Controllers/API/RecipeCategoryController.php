<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeCategoryResource;
use App\Models\RecipeCategory;
use Illuminate\Http\Request;

class RecipeCategoryController extends Controller
{
    public function getList(Request $request)
    {
        $recipecategory = RecipeCategory::active();

        $recipecategory->when(request('title'), function ($q) {
            return $q->where('title', 'LIKE', '%' . request('title') . '%');
        });

        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page))
            {
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ){
                $per_page = $recipecategory->count();
            }
        }

        $recipecategory = $recipecategory->orderBy('title', 'asc')->paginate($per_page);

        $items = RecipeCategoryResource::collection($recipecategory);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];
        
        return json_custom_response($response);
    }
}
