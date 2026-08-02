<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Workout;
use App\Http\Resources\WorkoutResource;
use App\Http\Resources\WorkoutDetailResource;
use App\Models\UserFavouriteWorkout;
use App\Models\WorkoutDay;
use App\Http\Resources\WorkoutDayResource;
use App\Models\WorkoutDayExercise;
use App\Http\Resources\WorkoutDayExerciseResource;

class WorkoutController extends Controller
{
    public function getList(Request $request)
    {
        $workout = Workout::active()->showAssignPrivateWorkout();

        $workout->when(request('title'), function ($q) {
            return $q->where('title', 'LIKE', '%' . request('title') . '%');
        });

        $workout->when(request('level_id'), function ($q) {
            $level_ids = explode(',', request('level_id'));
            return $q->whereIn('level_id', $level_ids);
        });

        $workout->when(request('level_ids'), function ($q) {
            $level_ids = explode(',', request('level_ids'));
            return $q->whereIn('level_id', $level_ids);
        });

        $workout->when(request('workout_type_id'), function ($q) {
            return $q->where('workout_type_id', request('workout_type_id'));
        });

        $workout->when(request('workout_type_ids'), function ($q) {
            $workout_type_ids = explode(',', request('workout_type_ids'));
            return $q->whereIn('workout_type_id', $workout_type_ids);
        });
        
        $workout->when(request('equipment_ids'), function ($query) {
            return $query->whereHas('workoutExercise',function ($q1) {
                $q1->whereHas('exercise', function ($q) {
                    $equipment_ids = explode(',', request('equipment_ids'));
                    $q->whereIn('equipment_id', $equipment_ids);
                });
            });
        });

        // Musculo trabajado - se deriva de los ejercicios que incluye el
        // workout (bodypart_ids es JSON en exercises, no un pivot).
        $workout->when(request('bodypart_ids'), function ($query) {
            return $query->whereHas('workoutExercise', function ($q1) {
                $q1->whereHas('exercise', function ($q) {
                    $bodypart_ids = explode(',', request('bodypart_ids'));
                    $q->where(function ($sub) use ($bodypart_ids) {
                        foreach ($bodypart_ids as $bodypart_id) {
                            $sub->orWhereJsonContains('bodypart_ids', (int) $bodypart_id);
                        }
                    });
                });
            });
        });

        if( $request->has('is_premium') && isset($request->is_premium) ) {
            $workout = $workout->where('is_premium', request('is_premium'));
        }
        
        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page))
            {
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ){
                $per_page = $workout->count();
            }
        }

        $workout = $workout->orderBy('title', 'asc')->paginate($per_page);

        $items = WorkoutResource::collection($workout);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];
        
        return json_custom_response($response);
    }

    public function getDetail(Request $request)
    {
        $workout = Workout::with('workoutDay')->where('id',request('id'))->showAssignPrivateWorkout()->first();
           
        if( $workout == null )
        {
            return json_message_response( __('message.not_found_entry',['name' => __('message.workout') ]) );
        }

        $workout_data = new WorkoutDetailResource($workout);

        $response = [
            'data' => $workout_data,
            'workoutday' => $workout->workoutDay ?? [],
        ];
             
        return json_custom_response($response);
    }

    public function getUserFavouriteWorkout(Request $request)
    {
        $workout = Workout::myWorkout()->showAssignPrivateWorkout();

        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)) {
            if(is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ) {
                $per_page = $workout->count();
            }
        }

        $workout = $workout->orderByMyFavouriteDesc()->paginate($per_page);

        $items = WorkoutResource::collection($workout);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];
        
        return json_custom_response($response);
    }

    public function userFavouriteWorkout(Request $request)
    {
        $user_id = auth()->id();
        $workout_id = $request->workout_id;

        $workout = Workout::where('id', $workout_id )->showAssignPrivateWorkout()->first();
        if( $workout == null )
        {
            return json_message_response( __('message.not_found_entry',['name' => __('message.workout') ]) );
        }
        $user_favourite_workout = UserFavouriteWorkout::where('user_id', $user_id)->where('workout_id', $workout_id)->first();
        
        if($user_favourite_workout != null) {
            $user_favourite_workout->delete();
            $message = __('message.unfavourite_workout_list');
        } else {
            $data = [
                'user_id'      => $user_id,
                'workout_id'   => $workout_id,
            ];
            
            UserFavouriteWorkout::create($data);
            $message = __('message.favourite_workout_list');
        }

        return json_message_response($message);
    }

    public function workoutDayList(Request $request)
    {
        $workoutday = WorkoutDay::where('workout_id',request('workout_id'));
        
        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page))
            {
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ){
                $per_page = $workoutday->count();
            }
        }

        $workoutday = $workoutday->paginate($per_page);

        $items = WorkoutDayResource::collection($workoutday);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];
        
        return json_custom_response($response);
    }
    
    public function workoutDayExerciseList(Request $request)
    {
        $day_exercise = WorkoutDayExercise::where('workout_day_id',request('workout_day_id'));
        
        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page))
            {
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ){
                $per_page = $day_exercise->count();
            }
        }

        $day_exercise = $day_exercise->paginate($per_page);

        $items = WorkoutDayExerciseResource::collection($day_exercise);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];
        
        return json_custom_response($response);
    }
}

