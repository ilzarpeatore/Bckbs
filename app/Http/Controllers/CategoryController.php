<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\CategoryDataTable;
use App\Helpers\AuthHelper;
use App\Models\Category;

use App\Http\Requests\CategoryRequest;


class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(CategoryDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.category')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('category-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('category-add') ? '<a href="'.route('category.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.category')]).'</a>' : '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if( !auth()->user()->can('category-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.add_form_title',[ 'form' => __('message.category')]);

        return view('category.form', compact('pageTitle'));
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CategoryRequest $request)
    {
        if( !auth()->user()->can('category-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $category = Category::create($request->all());

        storeMediaFile($category,$request->category_image, 'category_image'); 

        return redirect()->route('category.index')->withSuccess(__('message.save_form', ['form' => __('message.category')]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = Category::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if( !auth()->user()->can('category-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = Category::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.category') ]);

        return view('category.form', compact('data','id','pageTitle'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(CategoryRequest $request, $id)
    {
        if( !auth()->user()->can('category-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $category = Category::findOrFail($id);

        // category data...
        $category->fill($request->all())->update();

        // Save category image...
        if (isset($request->category_image) && $request->category_image != null) {
            $category->clearMediaCollection('category_image');
            $category->addMediaFromRequest('category_image')->toMediaCollection('category_image');
        }

        if(auth()->check()){
            return redirect()->route('category.index')->withSuccess(__('message.update_form',['form' => __('message.category')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.category') ] ));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('category-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $category = Category::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.category')]);

        if($category != '') {
            $category->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.category')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }
}
