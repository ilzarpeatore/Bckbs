<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Workout;
use App\Http\Resources\WorkoutResource;
use App\Models\WorkoutDay;
use App\Models\Exercise;
use App\Http\Resources\ExerciseDetailResource;
use App\Models\UserExercise;
use App\Http\Resources\UserWorkoutExerciseResource;

class WorkoutController extends Controller
{

    public function upNext(Request $request)
    {
        $workout_days = WorkoutDay::upNextWorkout();
        
        $response = [
            'data'  => $workout_days,
        ];

        return json_custom_response($response);
    }

    public function exerciseDetail(Request $request)
    {
        $exercise = Exercise::where('id',request('exercise_id'))->first();
           
        if( $exercise == null )
        {
            return json_message_response( __('message.not_found_entry',['name' => __('message.exercise') ]) );
        }

        $up_next_data = WorkoutDay::upNextWorkout();

        $exercise_data = new ExerciseDetailResource($exercise);
            $response = [
                'data' => $exercise_data,
                'up_next' => $up_next_data,
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

    public function storeUserWorkoutExercise(Request $request)
    {
        $user = auth()->user();
        $workout_exercise = [
            'user_id'           => $user->id,
            'exercise_id'       => request('exercise_id'),
            'workout_id'        => request('workout_id'),
            'workout_day_id'    => request('workout_day_id'),
            // La app manda 'sets' (peso/reps reales de la sesion) y 'date' -
            // antes se descartaban en silencio, el modelo/columna no
            // existian todavia cuando se escribio este endpoint.
            'logged_sets'       => request('sets'),
            'date'              => request('date'),
        ];

        $condition = [ 'user_id' => $user->id, 'exercise_id' => request('exercise_id') ];

        if( request('workout_id') != null ) {
            $condition['workout_id'] = request('workout_id');
        }

        if( request('workout_day_id') != null ) {
            $condition['workout_day_id'] = request('workout_day_id');
        }

        if ( request('status') == 'incomplete') {
            $user_exercise_data = UserExercise::where($condition)->first();
            
            if( $user_exercise_data ) {
                $user_exercise_data->delete();
                return json_message_response(__('message.mark_as_incomplete'));
            }
        }

        $user_exercise = UserExercise::updateOrCreate(
            $condition, $workout_exercise
        );

        return json_custom_response($user_exercise);
    }

    public function getUserWorkoutExercise(Request $request)
    {
        $user_exercises = UserExercise::myUserExercise()->whereNotNull('workout_id')->whereNotNull('workout_day_id')->with('exercise');

        $per_page = config('constant.PER_PAGE_LIMIT', 10);
        if ($request->has('per_page') && !empty($request->per_page)) {
            if (is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            } elseif ($request->per_page == -1) {
                $per_page = $user_exercises->count();
            }
        }

        $user_exercises = $user_exercises->orderBy('id', 'desc')->paginate($per_page);

        $items = UserWorkoutExerciseResource::collection($user_exercises);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];

        return json_custom_response($response);
    }

}

