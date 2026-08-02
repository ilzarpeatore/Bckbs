<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductDetailResource;
use App\Models\ProductCategory;
use App\Http\Resources\ProductCategoryResource;

class ProductController extends Controller
{
    public function getList(Request $request)
    {
        $product = Product::where('status', 'active');

        $product->when(request('title'), function ($q) {
            return $q->where('title', 'LIKE', '%' . request('title') . '%');
        });

        $product->when(request('productcategory_id'), function ($q) {
            return $q->where('productcategory_id', request('productcategory_id'));
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

        $items = ProductResource::collection($product);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];
        
        return json_custom_response($response);
    }

    public function getDetail(Request $request)
    {
        $product = Product::where('id',request('id'))->first();
           
        if( $product == null )
        {
            return json_message_response( __('message.not_found_entry',['name' => __('message.product') ]) );
        }

        $product_data = new ProductDetailResource($product);
            $response = [
                'data' => $product_data,
            ];
             
        return json_custom_response($response);
    }

    public function dashboard()
    {
        $product_category = ProductCategory::orderBy('title','asc')->take(10)->get();
        $product = Product::active()->orderBy('title','asc')->take(10)->get();

        $product = ProductResource::collection($product);
        $product_category = ProductCategoryResource::collection($product_category);

        $response = [
            'product' => $product,
            'product_category' => $product_category,
        ];
        return json_custom_response($response);
    }
}
