<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\PackageDataTable;
use App\Models\Package;
use App\Helpers\AuthHelper;

use App\Http\Requests\PackageRequest;

class PackageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(PackageDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.package')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('package-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('package-add') ? '<a href="'.route('package.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.package')]).'</a>' : '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if( !auth()->user()->can('package-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.add_form_title',[ 'form' => __('message.package')]);

        return view('package.form', compact('pageTitle'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(PackageRequest $request)
    {
        if( !auth()->user()->can('package-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $package = Package::create($request->all());

        storeMediaFile($package,$request->package_image, 'package_image'); 

        return redirect()->route('package.index')->withSuccess(__('message.save_form', ['form' => __('message.package')]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = Package::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if( !auth()->user()->can('package-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = Package::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.package') ]);

        return view('package.form', compact('data','id','pageTitle'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(PackageRequest $request, $id)
    {
        if( !auth()->user()->can('package-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $package = Package::findOrFail($id);

        // package data...
        $package->fill($request->all())->update();

        // Save package image...
        if (isset($request->package_image) && $request->package_image != null) {
            $package->clearMediaCollection('package_image');
            $package->addMediaFromRequest('package_image')->toMediaCollection('package_image');
        }

        if(auth()->check()){
            return redirect()->route('package.index')->withSuccess(__('message.update_form',['form' => __('message.package')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.package') ] ));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('package-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $package = Package::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.package')]);

        if($package != '') {
            $package->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.package')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }
}
