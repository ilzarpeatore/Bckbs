<?php

namespace App\Http\Controllers;

use App\Models\Posting;
use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\ReportPosting;
use App\Models\PostingLike;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\ReportPostingRequest;

class CommunityController extends Controller
{

    public function postComment(Request $request)
    {
        $perPage = 10;

        $data['posting_id'] = request('posting_id');
        $data['post'] = Posting::where('id', request('posting_id'))
            ->with('user')
            ->withCount(['comment', 'postingLike'])
            ->first();
        $data['comments'] = Comment::where('posting_id', request('posting_id'))
            ->with([
                'commentReply' => function ($query) {
                    $query->orderBy('id', 'asc')->take(5); // replies in ascending order
                }
            ])
            ->withCount('commentReply')
            ->orderBy('id', 'desc') // main comments newest first
            ->paginate($perPage);

        $data['pagination'] = json_pagination_response($data['comments']);

        $view = 'community.comment.index';
        $footer = null;

        switch (request('type')) {
            case 'comment':
            $view = 'community.comment.comments';
                break;

            default:
                $view = 'community.comment.index';
                $footer = request('page') > 1 ? null : view('community.comment.footer', with([ 'posting_id' => $data['posting_id'] ]))->render();
                # code...
                break;
        }
        $html = view($view, compact('data'))->render();

        return response()->json([
            'data' => $html,
            'footer' => $footer,
            'pagination' => $data['pagination'],
        ]);
    }

    public function deletePosting($id)
    {
        $posting = Posting::myPosting()->where('id', $id)->first();

        $status = true;
        $message = null;
        if( $posting == null ) {
            $message = __('message.not_found_entry', ['name' => __('message.posting') ]);
            $status = false;
        } else {
            $posting->delete();
        }
        
        return response()->json(['status' => $status, 'event' => 'posting', 'id' => $id, 'message' => $message]);
    }

    public function commentReply(Request $request)
    {
        $perPage = 5;

        $data['comment'] = Comment::where('id', request('comment_id'))->first();

        $data['comment_reply'] = CommentReply::where('comment_id', request('comment_id'))
                                ->orderBy('id', 'asc')->paginate($perPage);
        $data['pagination'] = json_pagination_response($data['comment_reply']);

        $html = view('community.comment.reply', compact('data'))->render();

        return response()->json([
            'data' => $html,
            'pagination' => $data['pagination'],
        ]);
    }

    public function saveCommentReply(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'redirect' => route('frontend.signin')]);
        }
        $request_data = $request->all();

        $validator = Validator::make($request_data,[
            'comment' => 'required',
        ]);

        if($validator->fails()) {
            $message = $validator->errors()->first();
            return response()->json(['status' => false, 'message' => $message, 'event' => 'validation']);
        }

        $user = auth()->user();

        $comment_id = request('comment_id');
        $view = null;
        if ( $request_data['comment_type'] == 'comment' ) {
            $result = Comment::updateOrCreate([
               'id' => $comment_id
            ], [
                'posting_id' => request('posting_id'),
                'user_id' => $user->id,
                'comment' => request('comment'),
            ]);
            $view = 'community.comment.comments';

            $data['comments'] = Comment::where('id', $result->id)
                ->with([
                    'commentReply' => function ($query) {
                        $query->orderBy('id', 'asc')->take(5); // replies in ascending order
                    }
                ])
                ->withCount('commentReply')
                ->orderBy('id', 'desc') // main comments newest first
                ->paginate(1);
            $event = 'comment';

        } else {
            $comment_reply_id = request('comment_reply_id');

            $result = CommentReply::updateOrCreate([
                'id' => $comment_reply_id,
            ], [
                'comment_id'    => $comment_id,
                'user_id'       => $user->id,
                'comment'       => request('comment')
            ]);
            $view = 'community.comment.reply';

            $data = [
                'comment_reply' => CommentReply::where('id', $result->id)->paginate(1),
            ];
            $event = 'commentreply';

        }
        $html = view($view, compact('data'))->render();

        return response()->json([
            'data'  => $html,
            'event' => $event,
            'status'=> true,
            'id'    => $result->id,
            'comment_id' => $comment_id,
            'is_updated' => !$result->wasRecentlyCreated
        ]);
    }

    public function deleteComment($id)
    {
        $comment = Comment::canBeDeletedBy()->where('id', $id)->first();

        $status = true;
        $message = null;
        if( $comment == null ) {
            $message = __('message.not_found_entry', ['name' => __('message.comment') ]);
            $status = false;
        } else {
            $comment->delete();
        }

        return response()->json(['status' => $status, 'event' => 'comment', 'id' => $id, 'message' => $message]);
    }

    public function deleteCommentReply($id)
    {
        $comment_reply = CommentReply::canBeDeletedBy()->where('id', $id)->first();

        if (! $comment_reply) {
            return response()->json([
                'status' => false,
                'event' => 'commentreply',
                'id' => $id,
                'message' => __('message.not_found_entry', ['name' => __('message.comment_reply') ]),
                'comment_id' => null,
                'comment_reply_count' => 0,
            ]);
        }

        $commentId = $comment_reply->comment_id;

        // Delete first, then count child replies
        $comment_reply->delete();

        $comment_reply_count = CommentReply::where('comment_id', $commentId)->count();
        
        return response()->json([
            'status' => true,
            'event' => 'commentreply',
            'id' => $id,
            'message' => null,
            'comment_id' => $commentId,
            'comment_reply_count' => $comment_reply_count,
        ]);
    }

    public function editCommentReply(Request $request,$id)
    {

        if ( request('type') == 'comment') {
            $comment = Comment::myComment()->where('id', $id)->first();
            if( $comment == null ) {
                $message = __('message.not_found_entry', ['name' => __('message.comment') ]);
                return response()->json([ 'status' => false, 'id' => $id, 'type' => 'comment' ]);
            }
        } else {
            $comment_reply = CommentReply::myCommentReply()->where('id', $id)->first();
            if( $comment_reply == null ) {
                $message = __('message.not_found_entry', ['name' => __('message.comment_reply') ]);
                return response()->json([ 'status' => false, 'id' => $id, 'type' => 'commentreply' ]);
            }
            $comment = Comment::where('id', $comment_reply->comment_id)->first();
        }

        if( $comment == null ) {
            $message = __('message.not_found_entry', ['name' => __('message.comment') ]);
        }
        $posting_id = $comment->posting_id;
        $comment_user_name = request('type') == 'comment' ?  __('message.edit_form_title', ['form' => __('message.comment') ]) : ( __('message.reply_to') . ' ' . optional($comment->user)->display_name ?? null );
        $data = [
            'type'      => request('type'),
            'comment_id'=> $comment_reply->comment_id ?? $comment->id,
            'comment'   => $comment_reply->comment ?? $comment->comment,
            'comment_reply_id' => $comment_reply->id ?? null,
            'comment_user_name' => $comment_user_name
        ];

        $html = view('community.comment.footer', with([ 'posting_id' => $posting_id, 'data' => $data ]))->render();

        return response()->json(['status' => true, 'data' => $html ]);

    }

    public function reportPosting(Request $request)
    {
        $data['posting_id'] = request('posting_id');
        $html = view('community.report',compact('data'))->render();

        return response()->json([
            'data' => $html,
        ]);
    }

    public function reportOnPosting(ReportPostingRequest $request)
    {
        if (!auth()->check()) {
            return response()->json([
                'success'  => false,
                'redirect' => route('frontend.signin')
            ]);
        }

        $user_id    = auth()->id();
        $posting_id = $request->posting_id;

        $result = ReportPosting::create([
            'user_id'    => $user_id,
            'posting_id' => $posting_id,
            'reason'     => $request->reason ?? null,
        ]);

        return response()->json([
            'status'     => true,
            'event'      => 'report',
            'message'    => __('message.report_on_post'),
            'id'         => $result->id,
            'posting_id' => $posting_id,
        ]);
    }

    public function postLikes(Request $request)
    {
        $perPage = 15;
        $data['posting_id'] = request('posting_id');
        $data['post_like'] = PostingLike::where('posting_id', request('posting_id'))->with('user:id,display_name')->orderBy('id', 'desc')->paginate($perPage);

        $data['pagination'] = json_pagination_response($data['post_like']);
        
        $view = 'community.postlike.index';
        if( request('type') == 'postlike' ) {
            $view = 'community.postlike.like-list';
        }
        $html = view($view, compact('data'))->render();
                
        return response()->json([
            'data' => $html,
            'pagination' => $data['pagination'],
        ]);
    }
}
