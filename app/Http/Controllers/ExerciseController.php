<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\ExerciseDataTable;
use App\Models\Exercise;
use App\Helpers\AuthHelper;
use App\Models\BodyPart;
use App\Http\Requests\ExerciseRequest;

class ExerciseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(ExerciseDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.exercise')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('exercise-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('exercise-add') ? '<a href="'.route('exercise.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.exercise')]).'</a>' : '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if( !auth()->user()->can('exercise-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.add_form_title',[ 'form' => __('message.exercise')]);

        return view('exercise.form', compact('pageTitle'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(ExerciseRequest $request)
    {
        if( !auth()->user()->can('exercise-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = $request->all();
        if( request('type') == 'duration' ) {
            if(request('hours') != null && request('minute') != null && request('second') != null ) {
                $data['duration'] = request('hours').':'.request('minute').':'.request('second') ?? null;
                $data['based'] = null;
                $data['sets'] = null;
                $data['seconds_per_rep'] = null;
            }
        }

        if( request('type') == 'sets' ) {
            if(request('reps') != null && !in_array(null, request('reps')) ) {
                foreach($request->reps as $i => $value) {
                    $save_data[] = [
                        'reps' => $value,
                        'weight' => $request->weight[$i] != null ? $request->weight[$i] : null,
                        'rest' => $request->rest[$i] ?? null,
                        'time' => $request->time[$i] ?? null,
                    ];
                }
                $data['sets'] = $save_data;
                $data['duration'] = null;
            }
        }
        unset($data['weight']);
        unset($data['reps']);
        unset($data['rest']);
        unset($data['time']);
        unset($data['hours']);
        unset($data['minute']);
        unset($data['second']);

        $exercise = Exercise::create($data);
    
        storeMediaFile($exercise,$request->exercise_image, 'exercise_image');
        if( $exercise->video_type == 'upload_video' ) {
            storeMediaFile($exercise,$request->exercise_video, 'exercise_video');
        }

        return redirect()->route('exercise.index')->withSuccess(__('message.save_form', ['form' => __('message.exercise')]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = Exercise::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if( !auth()->user()->can('exercise-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = Exercise::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.exercise') ]);

        $selected_bodypart = [];
        if(isset($data->bodypart_ids)) {
            $selected_bodypart = BodyPart::whereIn('id', $data->bodypart_ids)->get()->mapWithKeys(function ($item) {
                return [ $item->id => $item->title ];
            });
        }
        return view('exercise.form', compact('data','id','pageTitle','selected_bodypart'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(ExerciseRequest $request, $id)
    {
        if( !auth()->user()->can('exercise-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = $request->all();
        
        $exercise = Exercise::findOrFail($id);
        // Exercise data...
        if(request('equipment_id') == null) {
            $data['equipment_id'] = null;
        }
        if( request('type') == 'duration' ) {
            if( request('hours') != null && request('minute') != null && request('second') != null ) {
                $data['duration'] = request('hours').':'.request('minute').':'.request('second') ?? null;
                $data['sets'] = null;
                $data['based'] = null;
                $data['seconds_per_rep'] = null;
            }
        }
        if( request('type') == 'sets' ) {
            if(request('reps') != null && !in_array(null, request('reps')) ) {
                foreach($request->reps as $i => $value) {
                    $save_data[] = [
                        'reps' => $value,
                        'weight' => $request->weight[$i] != null ? $request->weight[$i] : null,
                        'rest' => $request->rest[$i] ?? null,
                        'time' => $request->time[$i] ?? null,
                    ];
                }
                $data['sets'] = $save_data;
                $data['duration'] = null;
            }
        }
        unset($data['weight']);
        unset($data['reps']);
        unset($data['time']);
        unset($data['hours']);
        unset($data['minute']);
        unset($data['second']);

        $exercise->fill($data)->update();           

        // Save exercise image...
        if (isset($request->exercise_image) && $request->exercise_image != null) {
            $exercise->clearMediaCollection('exercise_image');
            $exercise->addMediaFromRequest('exercise_image')->toMediaCollection('exercise_image');
        }

        if( $exercise->video_type == 'upload_video' ) {
            if (isset($request->exercise_video) && $request->exercise_video != null) {
                $exercise->clearMediaCollection('exercise_video');
                $exercise->addMediaFromRequest('exercise_video')->toMediaCollection('exercise_video');
            }
        }

        if(auth()->check()){
            return redirect()->route('exercise.index')->withSuccess(__('message.update_form',['form' => __('message.exercise')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.exercise') ] ));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('exercise-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $exercise = Exercise::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.exercise')]);

        if($exercise != '') {
            $exercise->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.exercise')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }
}
