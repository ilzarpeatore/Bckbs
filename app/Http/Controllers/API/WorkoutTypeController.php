<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WorkoutType;
use App\Http\Resources\WorkoutTypeResource;

class WorkoutTypeController extends Controller
{
    public function getList(Request $request)
    {
        $workouttype = WorkoutType::query();

        $workouttype->when(request('title'), function ($q) {
            return $q->where('title', 'LIKE', '%' . request('title') . '%');
        });

        if( $request->has('status') && isset($request->status) ) {
            $workouttype = $workouttype->where('status',request('status'));
        } else {
            $workouttype = $workouttype->where('status', 'active');
        }
        
        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page))
            {
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ){
                $per_page = $workouttype->count();
            }
        }

        $workouttype = $workouttype->orderBy('title', 'asc')->paginate($per_page);

        $items = WorkoutTypeResource::collection($workouttype);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];
        
        return json_custom_response($response);
    }
}

