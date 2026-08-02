<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClassSchedule;
use App\Http\Resources\ClassScheduleResource;
use App\Http\Requests\ClassSchedulePlanRequest;
use App\Models\ClassSchedulePlan;

class ClassScheduleController extends Controller
{
    public function getList(Request $request)
    {
        $dates = explode(',', request('date')) ?? [];
        
        $class_schedule = ClassSchedule::where(function ($query) use ($dates) {
            foreach ($dates as $date) {
                $query->orWhere(function ($q) use ($date) {
                    $q->whereDate('start_date', '<=', $date)
                      ->whereDate('end_date', '>=', $date);
                });
            }
        });

        $timezone = SettingData ('string', 'timezone') ?? config('app.timezone');
        $now = now()->setTimezone($timezone);
        $class_schedule->where(function ($query) use ($now) {
            $query->where('end_date', '>', $now->toDateString()) // Ends in future
                  ->orWhere(function ($q) use ($now) {
                      $q->where('end_date', '=', $now->toDateString()) // Ends today
                        ->where('end_time', '>', $now->format('H:i:s')); // Only if end_time is in the future
                  });
        });
        
       
        $class_schedule->when(request('class_name'), function ($q) {
            return $q->where('class_name', 'LIKE', '%' . request('class_name') . '%');
        });
                
        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page))
            {
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ){
                $per_page = $class_schedule->count();
            }
        }

        $class_schedule = $class_schedule->orderBy('id', 'Desc')->paginate($per_page);

        $items = ClassScheduleResource::collection($class_schedule);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];
        
        return json_custom_response($response);
    }

    public function storeClassSchedulePlan(ClassSchedulePlanRequest $request)
    {
        $auth_user = auth()->user();
        $my_class_schedule_plan = ClassSchedulePlan::where('user_id', $auth_user->id)
            ->where('class_schedule_id', $request->class_schedule_id)
            ->first();

        if ( isset($my_class_schedule_plan) ) {
            return json_custom_response(['message' =>__('validation.unique', ['attribute' => __('message.class_schedule_plan')])],400);
        }

        // $class_schedule = ClassSchedule::where('id',$request->class_schedule_id)->first();
        $request['user_id'] = $auth_user->id;
        ClassSchedulePlan::create($request->all());

        return json_message_response(__('message.save_form', ['form' => __('message.class_schedule_plan')]));
    }
}
