<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\ClassScheduleDataTable;
use App\Models\ClassSchedule;
use App\Helpers\AuthHelper;

use App\Http\Requests\ClassScheduleRequest;
use Carbon\Carbon;

class ClassScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(ClassScheduleDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.class_schedule')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('classschedule-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('classschedule-add') ? '<a href="'.route('classschedule.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.class_schedule')]).'</a>' : '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if( !auth()->user()->can('classschedule-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.add_form_title',[ 'form' => __('message.class_schedule')]);

        return view('class_schedule.form', compact('pageTitle'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(ClassScheduleRequest $request)
    {
        if( !auth()->user()->can('classschedule-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = $request->all();
        $data['workout_title'] = $request->workout_id == 'other' ? $request->workout_title : null;
        $data['workout_id'] = $request->workout_id != 'other' ? $request->workout_id : null;
        $data['workout_type'] = $request->workout_id == 'other' ? 'other' : 'workout';
        $data['price'] = $request->is_paid == 1 ? $request->price : null;

        $data['start_time'] = Carbon::parse($request->start_time)->setTimezone('UTC')->toDateTimeString();
        $data['end_time'] = Carbon::parse($request->end_time)->setTimezone('UTC')->toDateTimeString();

        ClassSchedule::create($data);
        return redirect()->route('classschedule.index')->withSuccess(__('message.save_form', ['form' => __('message.class_schedule')]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = ClassSchedule::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if( !auth()->user()->can('classschedule-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = ClassSchedule::findOrFail($id);
        $workout_id = $data->workout_type == 'other' ? ['other' => __('message.other')] : [ $data->workout_id => optional($data->workout)->title ];

        $pageTitle = __('message.update_form_title',[ 'form' => __('message.class_schedule') ]);
        return view('class_schedule.form', compact('data','id','pageTitle','workout_id'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(ClassScheduleRequest $request, $id)
    {
        if( !auth()->user()->can('classschedule-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $class_schedule = ClassSchedule::findOrFail($id);

        $data = $request->all();
        $data['workout_title'] = $request->workout_id == 'other' ? $request->workout_title : null;
        $data['workout_id'] = $request->workout_id != 'other' ? $request->workout_id : null;
        $data['workout_type'] = $request->workout_id == 'other' ? 'other' : 'workout';
        $data['price'] = $request->is_paid == 1 ? $request->price : null;

        // ClassSchedule data...
        $class_schedule->fill($data)->update();

        if(auth()->check()){
            return redirect()->route('classschedule.index')->withSuccess(__('message.update_form',['form' => __('message.class_schedule')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.class_schedule') ] ));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('classschedule-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $class_schedule = ClassSchedule::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.class_schedule')]);

        if($class_schedule != '') {
            $class_schedule->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.class_schedule')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }
}
