<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\ProductCategoryDataTable;
use App\Models\ProductCategory;
use App\Helpers\AuthHelper;

use App\Http\Requests\ProductCategoryRequest;

class ProductCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(ProductCategoryDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.productcategory')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('productcategory-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('productcategory-add') ? '<a href="'.route('productcategory.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('productcategory')]).'</a>' : '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if( !auth()->user()->can('productcategory-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.add_form_title',[ 'form' => __('message.productcategory')]);

        return view('productcategory.form', compact('pageTitle'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(ProductCategoryRequest $request)
    {
        if( !auth()->user()->can('productcategory-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $product_category = ProductCategory::create($request->all());

        storeMediaFile($product_category,$request->productcategory_image, 'productcategory_image'); 

        return redirect()->route('productcategory.index')->withSuccess(__('message.save_form', ['form' => __('message.productcategory')]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = ProductCategory::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if( !auth()->user()->can('productcategory-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = ProductCategory::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.productcategory') ]);

        return view('productcategory.form', compact('data','id','pageTitle'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(ProductCategoryRequest $request, $id)
    {
        if( !auth()->user()->can('productcategory-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $product_category = ProductCategory::findOrFail($id);

        // ProductCategory data...
        $product_category->fill($request->all())->update();

        // Save ProductCategory image...
        if (isset($request->productcategory_image) && $request->productcategory_image != null) {
            $product_category->clearMediaCollection('productcategory_image');
            $product_category->addMediaFromRequest('productcategory_image')->toMediaCollection('productcategory_image');
        }

        if(auth()->check()){
            return redirect()->route('productcategory.index')->withSuccess(__('message.update_form',['form' => __('message.productcategory')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.productcategory') ] ));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('productcategory-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $product_category = ProductCategory::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.productcategory')]);

        if($product_category != '') {
            $product_category->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.productcategory')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }
}
