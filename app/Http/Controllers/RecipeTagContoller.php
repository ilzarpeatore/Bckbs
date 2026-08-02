<?php

namespace App\Http\Controllers;

use App\DataTables\RecipeTagDataTable;
use Illuminate\Http\Request;
use App\Helpers\AuthHelper;
use App\Http\Requests\RecipeTagRequest;
use App\Models\RecipeTag;

class RecipeTagContoller extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(RecipeTagDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.recipetag')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('recipe-tag-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('recipe-tag-add') ? '<a href="'.route('recipe-tag.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.recipetag')]).'</a>' : '';

        return $dataTable->with(['auth_user' => $auth_user])->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if( !auth()->user()->can('recipe-tag-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.add_form_title',[ 'form' => __('message.recipetag')]);

        return view('recipetag.form', compact('pageTitle'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RecipeTagRequest $request)
    {
        if( !auth()->user()->can('recipe-tag-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $recipetag = RecipeTag::create($request->all());

        storeMediaFile($recipetag,$request->recipe_tag_image, 'recipe_tag_image'); 

        return redirect()->route('recipe-tag.index')->withSuccess(__('message.save_form', ['form' => __('message.recipetag')]));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        if( !auth()->user()->can('recipe-tag-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = RecipeTag::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.recipetag') ]);

        return view('recipetag.form', compact('data','id','pageTitle'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(RecipeTagRequest $request, string $id)
    {
        if( !auth()->user()->can('recipe-tag-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $recipetag = RecipeTag::findOrFail($id);
        
        $recipetag->fill($request->all())->update();
        
        if (isset($request->recipe_tag_image) && $request->recipe_tag_image != null) {
            $recipetag->clearMediaCollection('recipe_tag_image');
            $recipetag->addMediaFromRequest('recipe_tag_image')->toMediaCollection('recipe_tag_image');
        }

        if(auth()->check()){
            return redirect()->route('recipe-tag.index')->withSuccess(__('message.update_form',['form' => __('message.recipetag')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.recipetag') ] ));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('recipe-tag-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $recipetag = RecipeTag::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.recipetag')]);

        if($recipetag != '') {
            $recipetag->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.recipetag')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }
}
