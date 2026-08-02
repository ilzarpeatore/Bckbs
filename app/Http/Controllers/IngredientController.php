<?php

namespace App\Http\Controllers;

use App\DataTables\IngredientDataTable;
use App\Helpers\AuthHelper;
use Illuminate\Http\Request;
use App\Http\Requests\IngredientRequest;
use App\Models\Ingredient;

class IngredientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IngredientDataTable $dataTable)
    {
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('ingredient-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $pageTitle = __('message.list_form_title',['form' => __('message.ingredient')] );
        $assets = ['data-table'];

        $headerAction = $auth_user->can('ingredient-add') ? '<a href="'.route('ingredient.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.ingredient')]).'</a>' : '';

        return $dataTable->with(['auth_user' => $auth_user])->render('global.datatable', compact('pageTitle', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if( !auth()->user()->can('ingredient-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $pageTitle = __('message.add_form_title',[ 'form' => __('message.ingredient')]);

        return view('ingredient.form', compact('pageTitle'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(IngredientRequest $request)
    {
        if( !auth()->user()->can('ingredient-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $data = $request->all();
        $data = array_merge($data, [ 'protein_per_gram' => $request->protein_per_gram ?? 0,'fat_per_gram' => $request->fat_per_gram ?? 0,'carbs_per_gram' => $request->carbs_per_gram ?? 0 ]);

        Ingredient::create($data);

        return redirect()->route('ingredient.index')->withSuccess(__('message.save_form', ['form' => __('message.ingredient')]));
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
         if( !auth()->user()->can('ingredient-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $data = Ingredient::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.ingredient') ]);

        return view('ingredient.form', compact('data','id','pageTitle'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(IngredientRequest $request, string $id)
    {
        if( !auth()->user()->can('ingredient-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $ingredient = Ingredient::findOrFail($id);

        $data = $request->all();
        $data = array_merge($data, [ 'protein_per_gram' => $request->protein_per_gram ?? 0,'fat_per_gram' => $request->fat_per_gram ?? 0,'carbs_per_gram' => $request->carbs_per_gram ?? 0 ]);

        $ingredient->fill($data)->update();

        if(auth()->check()){
            return redirect()->route('ingredient.index')->withSuccess(__('message.update_form',['form' => __('message.ingredient')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.ingredient') ] ));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
       if( !auth()->user()->can('ingredient-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $ingredient = Ingredient::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.ingredient')]);

        if($ingredient != '') {
            $ingredient->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.ingredient')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }
}
