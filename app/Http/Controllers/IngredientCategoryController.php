<?php

namespace App\Http\Controllers;

use App\DataTables\IngredientCategoryDataTable;
use App\Helpers\AuthHelper;
use App\Models\IngredientCategory;
use Illuminate\Http\Request;
use App\Http\Requests\IngredientCategoryRequest;

class IngredientCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IngredientCategoryDataTable $dataTable)
    {
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('ingredientcategory-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $pageTitle = __('message.list_form_title',['form' => __('message.ingredient_category')] );
        $assets = ['data-table'];

        $headerAction = $auth_user->can('ingredientcategory-add') ? '<a href="'.route('ingredientcategory.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.ingredient_category')]).'</a>' : '';

        return $dataTable->with(['auth_user' => $auth_user])->render('global.datatable', compact('pageTitle', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if( !auth()->user()->can('ingredientcategory-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.add_form_title',[ 'form' => __('message.ingredient_category')]);

        return view('ingredientcategory.form', compact('pageTitle'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(IngredientCategoryRequest $request)
    {
        if( !auth()->user()->can('ingredientcategory-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        IngredientCategory::create($request->all());

        return redirect()->route('ingredientcategory.index')->withSuccess(__('message.save_form', ['form' => __('message.ingredient_category')]));
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
    public function edit(string $id)
    {
        if( !auth()->user()->can('ingredientcategory-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $data = IngredientCategory::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.ingredient_category') ]);

        return view('ingredientcategory.form', compact('data','id','pageTitle'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(IngredientCategoryRequest $request, string $id)
    {
        if( !auth()->user()->can('ingredientcategory-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $ingredientCategory = IngredientCategory::findOrFail($id);

        // ingredientcategory data...
        $ingredientCategory->fill($request->all())->update();

        if(auth()->check()){
            return redirect()->route('ingredientcategory.index')->withSuccess(__('message.update_form',['form' => __('message.ingredient_category')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.ingredient_category') ] ));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if( !auth()->user()->can('ingredientcategory-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $ingredientCategory = IngredientCategory::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.ingredient_category')]);

        if($ingredientCategory != '') {
            $ingredientCategory->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.ingredient_category')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }
}
