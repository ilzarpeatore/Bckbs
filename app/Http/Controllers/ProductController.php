<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\ProductDataTable;
use App\Models\Product;
use App\Helpers\AuthHelper;

use App\Http\Requests\ProductRequest;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index(ProductDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.product')] ); 
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('product-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('product-add') ? '<a href="'.route('product.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('product')]).'</a>' : '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if( !auth()->user()->can('product-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $pageTitle = __('message.add_form_title',[ 'form' => __('message.product')]);

        return view('product.form', compact('pageTitle'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(ProductRequest $request)
    {
        if( !auth()->user()->can('product-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $product = Product::create($request->all());

        storeMediaFile($product,$request->product_image, 'product_image'); 

        return redirect()->route('product.index')->withSuccess(__('message.save_form', ['form' => __('message.product')]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = Product::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if( !auth()->user()->can('product-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = Product::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.product') ]);

        return view('product.form', compact('data','id','pageTitle'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(ProductRequest $request, $id)
    {
        if( !auth()->user()->can('product-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $product = Product::findOrFail($id);

        // product data...
        $product->fill($request->all())->update();

        // Save product image...
        if (isset($request->product_image) && $request->product_image != null) {
            $product->clearMediaCollection('product_image');
            $product->addMediaFromRequest('product_image')->toMediaCollection('product_image');
        }

        if(auth()->check()){
            return redirect()->route('product.index')->withSuccess(__('message.update_form',['form' => __('message.product')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.product') ] ));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('product-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $level = Product::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.product')]);

        if($level != '') {
            $level->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.product')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }
}
