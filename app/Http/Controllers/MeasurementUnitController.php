<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\MeasurementUnitDataTable;
use App\Helpers\AuthHelper;
use App\Models\MeasurementUnit;
use App\Http\Requests\MeasurementUnitRequest;

class MeasurementUnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(MeasurementUnitDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title', ['form' => __('message.measurement_unit')]);
        $auth_user = AuthHelper::authSession();

        if (!$auth_user->can('measurementunit-list')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $assets = ['data-table'];

        $headerAction = $auth_user->can('measurementunit-add') ? '<a href="' . route('measurementunit.create') . '" class="btn btn-sm btn-primary" role="button">'. __('message.add_form_title', ['form' => __('message.measurement_unit')]) . '</a>' : '';

        return $dataTable->with(['auth_user' => $auth_user])->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!auth()->user()->can('measurementunit-add')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.add_form_title', ['form' => __('message.measurement_unit')]);

        return view('measurement_unit.form', compact('pageTitle'));
    }

    /**
     * Store a newly created resource.
     */
    public function store(MeasurementUnitRequest $request)
    {
        if (!auth()->user()->can('measurementunit-add')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $measurementUnit = MeasurementUnit::create($request->all());

        return redirect()->route('measurementunit.index')
            ->withSuccess(__('message.save_form', ['form' => __('message.measurement_unit')]));
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $data = MeasurementUnit::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        if (!auth()->user()->can('measurementunit-edit')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = MeasurementUnit::findOrFail($id);
        $pageTitle = __('message.update_form_title', ['form' => __('message.measurement_unit')]);

        return view('measurement_unit.form', compact('data', 'id', 'pageTitle'));
    }

    /**
     * Update the specified resource.
     */
    public function update(MeasurementUnitRequest $request, $id)
    {
        if (!auth()->user()->can('measurementunit-edit')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $measurementUnit = MeasurementUnit::findOrFail($id);

        $measurementUnit->fill($request->all())->update();

        if (auth()->check()) {
            return redirect()->route('measurementunit.index')
                ->withSuccess(__('message.update_form', ['form' => __('message.measurement_unit')]));
        }

        return redirect()->back()
            ->withSuccess(__('message.update_form', ['form' => __('message.measurement_unit')]));
    }

    /**
     * Remove the specified resource.
     */
    public function destroy($id)
    {
        if (!auth()->user()->can('measurementunit-delete')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $measurementUnit = MeasurementUnit::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.measurement_unit')]);

        if ($measurementUnit != '') {
            $measurementUnit->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.measurement_unit')]);
        }

        if (request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message]);
        }

        return redirect()->back()->with($status, $message);
    }
}
