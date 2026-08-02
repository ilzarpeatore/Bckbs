<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductCategory;
use App\Http\Resources\ProductCategoryResource;


class ProductCategoryController extends Controller
{
    public function getList(Request $request)
    {
        $product = ProductCategory::query();

        $product->when(request('title'), function ($q) {
            return $q->where('title', 'LIKE', '%' . request('title') . '%');
        });
        
        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page))
            {
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ){
                $per_page = $product->count();
            }
        }

        $product = $product->orderBy('title', 'asc')->paginate($per_page);

        $items = ProductCategoryResource::collection($product);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];
        
        return json_custom_response($response);
    }
}
