<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeTagResource;
use App\Models\RecipeTag;
use Illuminate\Http\Request;

class RecipeTagController extends Controller
{
    public function getList(Request $request)
    {
        $recipetag = RecipeTag::active();

        $recipetag->when(request('title'), function ($q) {
            return $q->where('title', 'LIKE', '%' . request('title') . '%');
        });

        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page))
            {
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ){
                $per_page = $recipetag->count();
            }
        }

        $recipetag = $recipetag->orderBy('title', 'asc')->paginate($per_page);

        $items = RecipeTagResource::collection($recipetag);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];

        return json_custom_response($response);
    }
}
