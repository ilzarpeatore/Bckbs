<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\TagsDataTable;
use App\Models\Tags;
use App\Helpers\AuthHelper;
use App\Http\Requests\TagsRequest;

class TagsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(TagsDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.tags')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('tags-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('tags-add') ? '<a href="'.route('tags.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.tags')]).'</a>' : '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if( !auth()->user()->can('tags-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.add_form_title',[ 'form' => __('message.tags')]);

        return view('tags.form', compact('pageTitle'));
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(TagsRequest $request)
    {
        if( !auth()->user()->can('tags-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $tags = Tags::create($request->all());

        storeMediaFile($tags,$request->tags_image, 'tags_image'); 

        return redirect()->route('tags.index')->withSuccess(__('message.save_form', ['form' => __('message.tags')]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = Tags::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if( !auth()->user()->can('tags-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = Tags::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.tags') ]);

        return view('tags.form', compact('data','id','pageTitle'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(TagsRequest $request, $id)
    {
        if( !auth()->user()->can('tags-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $tags = Tags::findOrFail($id);

        // tags data...
        $tags->fill($request->all())->update();

        if(auth()->check()){
            return redirect()->route('tags.index')->withSuccess(__('message.update_form',['form' => __('message.tags')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.tags') ] ));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('tags-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $tags = Tags::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.tags')]);

        if($tags != '') {
            $tags->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.tags')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }
}
