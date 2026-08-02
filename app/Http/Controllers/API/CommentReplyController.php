<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentReplyResource;
use Illuminate\Http\Request;
use App\Models\Comment;
use App\Models\CommentReply;

class CommentReplyController extends Controller
{
    public function getList(Request $request)
    {
        $comment = Comment::where('id', request('comment_id'))->first();
        
        $message = __('message.not_found_entry', ['name' => __('message.comment') ]);
        $status_code = 400;

        if( $comment != null ) {
            $comment_reply = CommentReply::where('comment_id', $comment->id);
    
            $per_page = config('constant.PER_PAGE_LIMIT');
            if ($request->has('per_page') && !empty($request->per_page)) {
                if (is_numeric($request->per_page)) {
                    $per_page = $request->per_page;
                }
                if ($request->per_page == -1) {
                    $per_page = $comment_reply->count();
                }
            }

            $comment_reply = $comment_reply->orderBy('id', 'asc')->paginate($per_page);

           
            $items = CommentReplyResource::collection($comment_reply);
        
            $response = [
                'pagination' => json_pagination_response($items),
                'data' => $items,
            ];
        } else {
            return json_message_response($message, $status_code);
        }
    
        return json_custom_response($response);
    }

    public function saveCommentReply(Request $request)
    {
        $comment = Comment::where('id', request('comment_id'))->first();

        $message = __('message.not_found_entry', ['name' => __('message.comment') ]);
        $status_code = 400;

        if( $comment != null ) {

            $data = $request->all();

            $user = auth()->user();
            $data['user_id'] = $user->id;

            if ($request->filled('id')) {
                $commentReply = CommentReply::where('id', $request->id)->first();
                $commentReply->fill($data)->update();
            } else {
                CommentReply::create($data);
            }

            // CommentReply::updateOrCreate(['id' => request('id') ], $data);
            $message = null;
            $status_code = 200;

        }

        return json_message_response( $message, $status_code);
    }

    public function deleteCommentReply(Request $request)
    {
        $comment_reply = CommentReply::canBeDeletedBy()->where('id', $request->id)->first();

        $message = __('message.not_found_entry', ['name' => __('message.comment_reply') ]);
        $status_code = 400;
        
        if( $comment_reply != null )
        {
            $comment_reply->delete();
            $status_code = 200;
            $message = __('message.delete_form', ['form' =>  __('message.comment_reply') ]);
        }
        
        return json_message_response( $message, $status_code);
    }

}