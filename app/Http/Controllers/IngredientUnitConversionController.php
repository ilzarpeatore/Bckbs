<?php

namespace App\Http\Controllers;

use App\DataTables\IngredientUnitConversionDataTable;
use App\Helpers\AuthHelper;
use App\Models\IngredientUnitConversion;
use App\Http\Requests\IngredientUnitConversionRequest;

class IngredientUnitConversionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IngredientUnitConversionDataTable $dataTable)
    {
        $auth_user = AuthHelper::authSession();

        if (!$auth_user->can('ingredient-unit-conversion-list')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $pageTitle = __('message.list_form_title', ['form' => __('message.ingredient_unit_conversion')]);
        $assets = ['data-table'];

        $headerAction = $auth_user->can('ingredient-unit-conversion-add') ? '<a href="' . route('ingredient-unit-conversion.create') . '" class="btn btn-sm btn-primary" role="button">' . __('message.add_form_title', ['form' => __('message.ingredient_unit_conversion')]) . '</a>' : '';

        return $dataTable->with(['auth_user' => $auth_user])->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!auth()->user()->can('ingredient-unit-conversion-add')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.add_form_title', ['form' => __('message.ingredient_unit_conversion')]);

        return view('ingredient_unit_conversion.form', compact('pageTitle'));
    }

    /**
     * Store a newly created resource.
     */
    public function store(IngredientUnitConversionRequest $request)
    {
        if (!auth()->user()->can('ingredient-unit-conversion-add')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $data = $request->all();
        
        IngredientUnitConversion::create($data);

        return redirect()->route('ingredient-unit-conversion.index')->withSuccess(__('message.save_form', ['form' => __('message.ingredient_unit_conversion')]));
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        if (!auth()->user()->can('ingredient-unit-conversion-edit')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = IngredientUnitConversion::findOrFail($id);
        $pageTitle = __('message.update_form_title', ['form' => __('message.ingredient_unit_conversion')]);

        return view('ingredient_unit_conversion.form', compact('data', 'id', 'pageTitle'));
    }

    /**
     * Update the specified resource.
     */
    public function update(IngredientUnitConversionRequest $request, $id)
    {
        if (!auth()->user()->can('ingredient-unit-conversion-edit')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $ingredientUnitConversion = IngredientUnitConversion::findOrFail($id);
        
        $ingredientUnitConversion->fill($request->all())->update();

        if (auth()->check()) {
            return redirect()->route('ingredient-unit-conversion.index')->withSuccess(__('message.update_form', ['form' => __('message.ingredient_unit_conversion')]));
        }

        return redirect()->back()->withSuccess(__('message.update_form', ['form' => __('message.ingredient_unit_conversion')]));
    }

    /**
     * Remove the specified resource.
     */
    public function destroy($id)
    {
        if (!auth()->user()->can('ingredient-unit-conversion-delete')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $ingredientUnitConversion = IngredientUnitConversion::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.ingredient_unit_conversion')]);

        if ($ingredientUnitConversion != '') {
            $ingredientUnitConversion->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.ingredient_unit_conversion')]);
        }

        if (request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message]);
        }

        return redirect()->back()->with($status, $message);
    }
}
