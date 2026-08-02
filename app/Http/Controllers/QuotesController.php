<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\QuotesDataTable;
use App\Models\Quotes;
use App\Helpers\AuthHelper;
use App\Http\Requests\QuotesRequest;

class QuotesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(QuotesDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.quotes')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('quotes-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('quotes-add') ? '<a href="'.route('quotes.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.quotes')]).'</a>' : '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if( !auth()->user()->can('quotes-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.add_form_title',[ 'form' => __('message.quotes')]);

        return view('quotes.form', compact('pageTitle'));
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(QuotesRequest $request)
    {
        if( !auth()->user()->can('quotes-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $quotes = Quotes::create($request->all());

        return redirect()->route('quotes.index')->withSuccess(__('message.save_form', ['form' => __('message.quotes')]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = Quotes::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if( !auth()->user()->can('quotes-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $data = Quotes::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.quotes') ]);

        return view('quotes.form', compact('data','id','pageTitle'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(QuotesRequest $request, $id)
    {
        if( !auth()->user()->can('quotes-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $quotes = Quotes::findOrFail($id);

        // quotes data...
        $quotes->fill($request->all())->update();

        if(auth()->check()){
            return redirect()->route('quotes.index')->withSuccess(__('message.update_form',['form' => __('message.quotes')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.quotes') ] ));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('quotes-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $quotes = Quotes::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.quotes')]);

        if($quotes != '') {
            $quotes->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.quotes')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }
}
