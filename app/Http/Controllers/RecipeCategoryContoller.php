<?php

namespace App\Http\Controllers;

use App\DataTables\RecipeCategoryDataTable;
use App\Models\RecipeCategory;
use Illuminate\Http\Request;
use App\Helpers\AuthHelper;
use App\Http\Requests\RecipeCategoryRequest;

class RecipeCategoryContoller extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(RecipeCategoryDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.recipecategory')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('recipe-category-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('recipe-category-add') ? '<a href="'.route('recipe-category.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.recipecategory')]).'</a>' : '';

        return $dataTable->with(['auth_user' => $auth_user])->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if( !auth()->user()->can('recipe-category-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.add_form_title',[ 'form' => __('message.recipecategory')]);

        return view('recipecategory.form', compact('pageTitle'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RecipeCategoryRequest $request)
    {
        if( !auth()->user()->can('recipe-category-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $recipecategory = RecipeCategory::create($request->all());

        storeMediaFile($recipecategory,$request->recipe_category_image, 'recipe_category_image'); 

        return redirect()->route('recipe-category.index')->withSuccess(__('message.save_form', ['form' => __('message.recipecategory')]));
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
        if( !auth()->user()->can('recipe-category-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = RecipeCategory::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.recipecategory') ]);

        return view('recipecategory.form', compact('data','id','pageTitle'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(RecipeCategoryRequest $request, $id)
    {
        if( !auth()->user()->can('recipe-category-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $recipecategory = RecipeCategory::findOrFail($id);
        
        $recipecategory->fill($request->all())->update();
        
        if (isset($request->recipe_category_image) && $request->recipe_category_image != null) {
            $recipecategory->clearMediaCollection('recipe_category_image');
            $recipecategory->addMediaFromRequest('recipe_category_image')->toMediaCollection('recipe_category_image');
        }

        if(auth()->check()){
            return redirect()->route('recipe-category.index')->withSuccess(__('message.update_form',['form' => __('message.recipecategory')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.recipecategory') ] ));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('recipe-category-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $recipecategory = RecipeCategory::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.recipecategory')]);

        if($recipecategory != '') {
            $recipecategory->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.recipecategory')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }
}
