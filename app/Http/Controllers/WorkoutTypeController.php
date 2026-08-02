<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\WorkoutTypeDataTable;
use App\Helpers\AuthHelper;
use App\Models\WorkoutType;

use App\Http\Requests\WorkoutTypeRequest;


class WorkoutTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(WorkoutTypeDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.workouttype')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('workouttype-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('workouttype-add') ? '<a href="'.route('workouttype.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.workouttype')]).'</a>' : '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if( !auth()->user()->can('workouttype-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.add_form_title',[ 'form' => __('message.workouttype')]);

        return view('workouttype.form', compact('pageTitle'));
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(WorkoutTypeRequest $request)
    {
        if( !auth()->user()->can('workouttype-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $workouttype = WorkoutType::create($request->all());

        storeMediaFile($workouttype,$request->workouttype_image, 'workouttype_image'); 

        return redirect()->route('workouttype.index')->withSuccess(__('message.save_form', ['form' => __('message.workouttype')]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = WorkoutType::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if( !auth()->user()->can('workouttype-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = WorkoutType::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.workouttype') ]);

        return view('workouttype.form', compact('data','id','pageTitle'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(WorkoutTypeRequest $request, $id)
    {
        if( !auth()->user()->can('workouttype-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $workouttype = WorkoutType::findOrFail($id);

        // workouttype data...
        $workouttype->fill($request->all())->update();

        // Save workouttype image...
        if (isset($request->workouttype_image) && $request->workouttype_image != null) {
            $workouttype->clearMediaCollection('workouttype_image');
            $workouttype->addMediaFromRequest('workouttype_image')->toMediaCollection('workouttype_image');
        }

        if(auth()->check()){
            return redirect()->route('workouttype.index')->withSuccess(__('message.update_form',['form' => __('message.workouttype')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.workouttype') ] ));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('workouttype-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $workouttype = WorkoutType::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.workouttype')]);

        if($workouttype != '') {
            $workouttype->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.workouttype')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }
}
