<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\DietDataTable;
use App\Helpers\AuthHelper;
use App\Models\Diet;

use App\Http\Requests\DietRequest;


class DietController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(DietDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.diet')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('diet-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('diet-add') ? '<a href="'.route('diet.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.diet')]).'</a>' : '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if( !auth()->user()->can('diet-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.add_form_title',[ 'form' => __('message.diet')]);

        return view('diet.form', compact('pageTitle'));
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(DietRequest $request)
    {
        if( !auth()->user()->can('diet-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $diet = Diet::create($request->all());

        storeMediaFile($diet,$request->diet_image, 'diet_image'); 

        return redirect()->route('diet.index')->withSuccess(__('message.save_form', ['form' => __('message.diet')]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = Diet::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if( !auth()->user()->can('diet-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = Diet::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.diet') ]);

        return view('diet.form', compact('data','id','pageTitle'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(DietRequest $request, $id)
    {
        if( !auth()->user()->can('diet-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $diet = Diet::findOrFail($id);

        // diet data...
        $diet->fill($request->all())->update();

        // Save diet image...
        if (isset($request->diet_image) && $request->diet_image != null) {
            $diet->clearMediaCollection('diet_image');
            $diet->addMediaFromRequest('diet_image')->toMediaCollection('diet_image');
        }

        if(auth()->check()){
            return redirect()->route('diet.index')->withSuccess(__('message.update_form',['form' => __('message.diet')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.diet') ] ));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('diet-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $diet = Diet::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.diet')]);

        if($diet != '') {
            $diet->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.diet')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }
}
