<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\EquipmentDataTable;
use App\Models\Equipment;
use App\Helpers\AuthHelper;

use App\Http\Requests\EquipmentRequest;

class EquipmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(EquipmentDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.equipment')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('equipment-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('equipment-add') ? '<a href="'.route('equipment.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.equipment')]).'</a>' : '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if( !auth()->user()->can('equipment-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.add_form_title',[ 'form' => __('message.equipment')]);

        return view('equipment.form', compact('pageTitle'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(EquipmentRequest $request)
    {
        if( !auth()->user()->can('equipment-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $equipment = Equipment::create($request->all());

        storeMediaFile($equipment,$request->equipment_image, 'equipment_image'); 

        return redirect()->route('equipment.index')->withSuccess(__('message.save_form', ['form' => __('message.equipment')]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = Equipment::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if( !auth()->user()->can('equipment-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = Equipment::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.equipment') ]);

        return view('equipment.form', compact('data','id','pageTitle'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(EquipmentRequest $request, $id)
    {
        if( !auth()->user()->can('equipment-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $equipment = Equipment::findOrFail($id);

        // Equipment data...
        $equipment->fill($request->all())->update();

        // Save equipment image...
        if (isset($request->equipment_image) && $request->equipment_image != null) {
            $equipment->clearMediaCollection('equipment_image');
            $equipment->addMediaFromRequest('equipment_image')->toMediaCollection('equipment_image');
        }

        if(auth()->check()){
            return redirect()->route('equipment.index')->withSuccess(__('message.update_form',['form' => __('message.equipment')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.equipment') ] ));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('equipment-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $equipment = Equipment::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.equipment')]);

        if($equipment != '') {
            $equipment->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.equipment')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }
}
