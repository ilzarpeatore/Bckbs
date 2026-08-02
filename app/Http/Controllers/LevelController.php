<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\LevelDataTable;
use App\Models\Level;
use App\Helpers\AuthHelper;

use App\Http\Requests\LevelRequest;

class LevelController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(LevelDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.level')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('level-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('level-add') ? '<a href="'.route('level.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.level')]).'</a>' : '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if( !auth()->user()->can('level-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $pageTitle = __('message.add_form_title',[ 'form' => __('message.level')]);

        return view('level.form', compact('pageTitle'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(LevelRequest $request)
    {
        if( !auth()->user()->can('level-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $level = Level::create($request->all());

        storeMediaFile($level,$request->level_image, 'level_image'); 

        return redirect()->route('level.index')->withSuccess(__('message.save_form', ['form' => __('message.level')]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = Level::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if( !auth()->user()->can('level-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = Level::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.level') ]);

        return view('level.form', compact('data','id','pageTitle'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(LevelRequest $request, $id)
    {
        if( !auth()->user()->can('level-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $level = Level::findOrFail($id);

        // level data...
        $level->fill($request->all())->update();

        // Save level image...
        if (isset($request->level_image) && $request->level_image != null) {
            $level->clearMediaCollection('level_image');
            $level->addMediaFromRequest('level_image')->toMediaCollection('level_image');
        }

        if(auth()->check()){
            return redirect()->route('level.index')->withSuccess(__('message.update_form',['form' => __('message.level')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.level') ] ));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('level-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $level = Level::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.level')]);

        if($level != '') {
            $level->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.level')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }
}
