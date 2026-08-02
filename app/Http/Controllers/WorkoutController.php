<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\WorkoutDataTable;
use App\Helpers\AuthHelper;
use App\Models\Workout;
use App\Models\WorkoutDayExercise;
use App\Models\WorkoutDay;
use App\Http\Requests\WorkoutRequest;
use App\Models\Exercise;

class WorkoutController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(WorkoutDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.workout')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('workout-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('workout-add') ? '<a href="'.route('workout.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.workout')]).'</a>' : '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if( !auth()->user()->can('workout-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $pageTitle = __('message.add_form_title',[ 'form' => __('message.workout')]);

        return view('workout.form', compact('pageTitle'));
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(WorkoutRequest $request)
    {
        if( !auth()->user()->can('workout-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $workout = Workout::create($request->all());

        storeMediaFile($workout,$request->workout_image, 'workout_image'); 

        if(isset($request->is_rest) && $request->is_rest != null ){
            foreach($request->is_rest as $i => $value){
                if($value != null ){
                    if($value == 1){
                        $exercise_ids = null;
                    } else {
                        $exercise_ids = isset($request->exercise_ids[$i]) ? $request->exercise_ids[$i] : null;
                    }
                    $save_workdays_data = [
                        'id' => null,
                        'workout_id' => $workout->id,
                        'is_rest' => $value,
                        'sequence' => $i,
                    ];

                    $workoutday = WorkoutDay::create($save_workdays_data);

                    if( $workoutday->is_rest == 0 && !empty($exercise_ids) ) {
                        foreach ($exercise_ids as $key => $value) {
                            $days_exercise = [
                                'id' => null,
                                'workout_id' => $workout->id,
                                'workout_day_id' => $workoutday->id,
                                'exercise_id' => (int) $value,
                                'sequence' => $key,
                            ];
                            $workout_days_exercise = WorkoutDayExercise::create($days_exercise);
                        }
                    }
                }
            }
        }

        return redirect()->route('workout.index')->withSuccess(__('message.save_form', ['form' => __('message.workout')]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = Workout::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if( !auth()->user()->can('workout-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $data = Workout::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.workout') ]);
        if(isset($id) && count($data->workoutDay) > 0){
            foreach($data->workoutDay as &$field){
                $exercise_ids = [];
                if($field->is_rest == 0){   
                    $exercise_ids = $field->workoutDayExercise->mapWithKeys(function ($item) {
                        return [ $item->exercise_id => optional($item->exercise)->title ];
                    });
                    $field['exercise_data'] = $exercise_ids;
                    
                    $exercise_id = $field->workoutDayExercise->pluck('exercise_id')->toArray();
                    $field['exercise_ids'] = array_map('strval', $exercise_id);
                }
            }
        }
        return view('workout.form', compact('data','id','pageTitle'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(WorkoutRequest $request, $id)
    {
        if( !auth()->user()->can('workout-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $workout = Workout::findOrFail($id);
        // workout data...
        $workout->fill($request->all())->update();

        // Save workout image...
        if (isset($request->workout_image) && $request->workout_image != null) {
            $workout->clearMediaCollection('workout_image');
            $workout->addMediaFromRequest('workout_image')->toMediaCollection('workout_image');
        }

        if(isset($request->is_rest) && $request->is_rest != null ){
            foreach($request->is_rest as $i => $value){
                if($value != null ){
                    if($value == 1){
                        $exercise_ids = null;
                    } else {
                        $exercise_ids = isset($request->exercise_ids[$i]) ? $request->exercise_ids[$i] : null;
                    }
                    $save_workdays_data = [
                        'id' => $request->workout_days_id[$i] ?? null,
                        'workout_id' => $workout->id,
                        'is_rest' => $value,
                        'sequence' => $i,
                    ];

                    $workoutday = WorkoutDay::updateOrCreate(['id' => $save_workdays_data['id']],$save_workdays_data);
                    $workoutday->workoutDayExercise()->delete();
                    if( $workoutday->is_rest == 0 && !empty($exercise_ids) ) {
                        foreach ($exercise_ids as $key => $value) {
                            $days_exercise = [
                                'id' => null,
                                'workout_id' => $workout->id,
                                'workout_day_id' => $workoutday->id,
                                'exercise_id' => (int) $value,
                                'sequence' => $key,
                            ];
                            $workout_days_exercise = WorkoutDayExercise::create($days_exercise);
                        }
                    }
                }
            }
        }

        if(auth()->check()){
            return redirect()->route('workout.index')->withSuccess(__('message.update_form',['form' => __('message.workout')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.workout') ] ));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('workout-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $workout = Workout::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.workout')]);

        if($workout != '') {
            $workout->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.workout')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }

    public function workoutDaysExerciseDelete(Request $request)
    {
        $id = $request->id;

        $workout = WorkoutDay::findOrFail($id);
        $status = false;
        $message = __('message.not_found_entry', ['name' => __('message.workout_days')]);

        if($workout != '') {
            $workout->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.workout_days')]);
        }
        return response()->json(['status'=> $status, 'message'=> $message ]);
    }
}
