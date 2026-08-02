<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Equipment;
use App\Http\Resources\EquipmentResource;

class EquipmentController extends Controller
{
    public function getList(Request $request)
    {
        $equipment = Equipment::where('status', 'active');

        $equipment->when(request('title'), function ($q) {
            return $q->where('title', 'LIKE', '%' . request('title') . '%');
        });
                
        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page))
            {
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ){
                $per_page = $equipment->count();
            }
        }

        $equipment = $equipment->orderBy('title', 'asc')->paginate($per_page);

        $items = EquipmentResource::collection($equipment);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];
        
        return json_custom_response($response);
    }
}