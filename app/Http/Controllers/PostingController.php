<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\PostingDataTable;
use App\Helpers\AuthHelper;
use App\Models\Posting;
use App\Models\Comment;
use App\DataTables\ReportPostingDataTable;

class PostingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(PostingDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.posting')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('posting-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $assets = ['data-table'];

        $headerAction = '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    public function reportPostingList(ReportPostingDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.reported_posting')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('reported-posting-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $assets = ['data-table'];

        $headerAction = '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show( ReportPostingDataTable $dataTable, $id )
    {
        $auth_user = AuthHelper::authSession();
        
        if( !$auth_user->can('posting-show') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = Posting::withCount(['comment', 'postingLike'])->findOrFail($id);
        $pageTitle = __('message.detail_form_title' , [ 'form' => __('message.posting') ]);
        $assets = ['community'];
        $data->attachments = getAttachmentArray( $data->getMedia('posting_media'), null);
        return $dataTable->with('posting_id', $id)->render('posting.show', compact( 'pageTitle', 'data', 'auth_user', 'assets'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if( !auth()->user()->can('posting-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->route('posting.index')->withErrors($message);
        }

        $data = Posting::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.posting') ]);
        
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('posting-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->route('posting.index')->withErrors($message);
        }

        $posting = Posting::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', [ 'name' => __('message.posting') ] );

        if ( $posting != null ) {
            $posting->delete();
            $status = 'success';
            $message = __('message.delete_form', [ 'form' => __('message.posting')]);
        }

        if ( request()->ajax() ) {
            return response()->json(['status' => true, 'message' => $message, 'route' => route('posting.index')]);
        }

        return redirect()->route('posting.index')->with($status,$message);
    }
}
