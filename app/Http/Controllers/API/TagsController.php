<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tags;
use App\Http\Resources\TagsResource;

class TagsController extends Controller
{
    public function getList(Request $request)
    {
        $tags = Tags::query();

        $tags->when(request('title'), function ($q) {
            return $q->where('title', 'LIKE', '%' . request('title') . '%');
        });
        
        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page))
            {
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ){
                $per_page = $tags->count();
            }
        }

        $tags = $tags->orderBy('title', 'asc')->paginate($per_page);

        $items = TagsResource::collection($tags);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];
        
        return json_custom_response($response);
    }
}
