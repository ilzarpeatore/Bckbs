<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\BodyPartDataTable;
use App\Helpers\AuthHelper;
use App\Models\BodyPart;

use App\Http\Requests\BodyPartRequest;

class BodyPartController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(BodyPartDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.bodypart')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('bodyparts-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('bodyparts-add') ? '<a href="'.route('bodypart.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.bodypart')]).'</a>' : '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if( !auth()->user()->can('bodyparts-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.add_form_title',[ 'form' => __('message.bodypart')]);

        return view('bodypart.form', compact('pageTitle'));
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(BodyPartRequest $request)
    {
        if( !auth()->user()->can('bodyparts-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $bodypart = BodyPart::create($request->all());

        storeMediaFile($bodypart,$request->bodypart_image, 'bodypart_image'); 

        return redirect()->route('bodypart.index')->withSuccess(__('message.save_form', ['form' => __('message.bodypart')]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = BodyPart::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if( !auth()->user()->can('bodyparts-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = BodyPart::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.bodypart') ]);

        return view('bodypart.form', compact('data','id','pageTitle'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(BodyPartRequest $request, $id)
    {
        if( !auth()->user()->can('bodyparts-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $bodypart = BodyPart::findOrFail($id);

        // bodypart data...
        $bodypart->fill($request->all())->update();

        // Save bodypart image...
        if (isset($request->bodypart_image) && $request->bodypart_image != null) {
            $bodypart->clearMediaCollection('bodypart_image');
            $bodypart->addMediaFromRequest('bodypart_image')->toMediaCollection('bodypart_image');
        }

        if(auth()->check()){
            return redirect()->route('bodypart.index')->withSuccess(__('message.update_form',['form' => __('message.bodypart')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.bodypart') ] ));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('bodyparts-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $bodypart = BodyPart::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.bodypart')]);

        if($bodypart != '') {
            $bodypart->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.bodypart')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }
}
