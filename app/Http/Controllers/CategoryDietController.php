<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\CategoryDietDataTable;
use App\Helpers\AuthHelper;
use App\Models\CategoryDiet;

use App\Http\Requests\CategoryDietRequest;


class CategoryDietController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(CategoryDietDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.categorydiet')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('categorydiet-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $assets = ['data-table'];

        $headerAction = $auth_user->can('categorydiet-add') ? '<a href="'.route('categorydiet.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.categorydiet')]).'</a>' : '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if( !auth()->user()->can('categorydiet-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.add_form_title',[ 'form' => __('message.categorydiet')]);

        return view('categorydiet.form', compact('pageTitle'));
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CategoryDietRequest $request)
    {
        if( !auth()->user()->can('categorydiet-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $categorydiet = CategoryDiet::create($request->all());

        storeMediaFile($categorydiet,$request->categorydiet_image, 'categorydiet_image'); 

        return redirect()->route('categorydiet.index')->withSuccess(__('message.save_form', ['form' => __('message.categorydiet')]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = CategoryDiet::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if( !auth()->user()->can('categorydiet-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = CategoryDiet::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.categorydiet') ]);

        return view('categorydiet.form', compact('data','id','pageTitle'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(categorydietRequest $request, $id)
    {
        if( !auth()->user()->can('categorydiet-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $categorydiet = CategoryDiet::findOrFail($id);

        // categorydiet data...
        $categorydiet->fill($request->all())->update();

        // Save categorydiet image...
        if (isset($request->categorydiet_image) && $request->categorydiet_image != null) {
            $categorydiet->clearMediaCollection('categorydiet_image');
            $categorydiet->addMediaFromRequest('categorydiet_image')->toMediaCollection('categorydiet_image');
        }

        if(auth()->check()){
            return redirect()->route('categorydiet.index')->withSuccess(__('message.update_form',['form' => __('message.categorydiet')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.categorydiet') ] ));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('categorydiet-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $categorydiet = CategoryDiet::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.categorydiet')]);

        if($categorydiet != '') {
            $categorydiet->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.categorydiet')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }
}
