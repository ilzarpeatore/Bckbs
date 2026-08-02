<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\PostDataTable;
use App\Helpers\AuthHelper;
use App\Models\Post;
use App\Models\Tags;
use App\Models\Category;

use App\Http\Requests\PostRequest;

use Modules\Frontend\Jobs\SendPostNotification;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(PostDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.post')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('post-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('post-add') ? '<a href="'.route('post.create').'" class="btn btn-sm btn-primary" role="button">'.__('message.add_form_title', [ 'form' => __('message.post')]).'</a>' : '';

        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if( !auth()->user()->can('post-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $pageTitle = __('message.add_form_title',[ 'form' => __('message.post')]);

        return view('post.form', compact('pageTitle'));
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(PostRequest $request)
    {
        if( !auth()->user()->can('post-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $post = Post::create($request->all());

        storeMediaFile($post,$request->post_image, 'post_image'); 

        if ($request->has('send_mail_to_subscribers')) {
            SendPostNotification::dispatch($post);
        }

        return redirect()->route('post.index')->withSuccess(__('message.save_form', ['form' => __('message.post')]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = Post::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if( !auth()->user()->can('post-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = Post::findOrFail($id);
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.post') ]);
        $selected_tags = [];
        if(isset($data->tags_id)){            
            $selected_tags = Tags::whereIn('id', $data->tags_id)->get()->mapWithKeys(function ($item) {
                return [ $item->id => $item->title ];
            });
        }
        $selected_category = [];
        if(isset($data->category_ids)){
            $selected_category = Category::whereIn('id', $data->category_ids)->get()->mapWithKeys(function ($item) {
                return [ $item->id => $item->title ];
            });
        }
            return view('post.form', compact('data','id','pageTitle','selected_tags','selected_category'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(PostRequest $request, $id)
    {
        if( !auth()->user()->can('post-edit') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $post = Post::findOrFail($id);

        // post data...
        $post->fill($request->all())->update();

        // Save post image...
        if (isset($request->post_image) && $request->post_image != null) {
            $post->clearMediaCollection('post_image');
            $post->addMediaFromRequest('post_image')->toMediaCollection('post_image');
        }
        
        if ($request->has('send_mail_to_subscribers')) {
            SendPostNotification::dispatch($post); 
        }

        if(auth()->check()){
            return redirect()->route('post.index')->withSuccess(__('message.update_form',['form' => __('message.post')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form',['form' => __('message.post') ] ));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if( !auth()->user()->can('post-delete') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $post = Post::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.post')]);

        if($post != '') {
            $post->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.post')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status,$message);
    }
}