<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserPostingRequest;
use App\Http\Resources\PostingResource;
use Illuminate\Http\Request;
use App\Models\Posting;
use App\Models\PostingLike;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Http\Resources\PostingUserResource;
use App\Models\PostingBookmark;

use App\Http\Resources\UserLikeResource;
use App\Http\Resources\UserBookmarkResource;
use App\Models\ReportPosting;

class PostingController extends Controller
{
    public function getPostList(Request $request)
    {
        $posting = Posting::published()->excludeReportedPost()->withCount(['comment', 'postingLike'])
            ->with(['user:id,first_name,last_name,display_name,email,username,phone_number,profile_image',
                'postingLike' => fn ($q) => $q->where('user_id', auth()->id()),
                'postingBookmark' => fn ($q) => $q->where('user_id', auth()->id()),
            ]);

        $posting->when(request('user_id'), function ($q) {
            return $q->where('user_id',request('user_id'));
        });

        $per_page = config('constant.PER_PAGE_LIMIT');
        if ($request->has('per_page') && !empty($request->per_page)) {
            if (is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            }
            if ($request->per_page == -1) {
                $per_page = $posting->count();
            }
        }

        $posting = $posting->orderBy('id', 'desc')->paginate($per_page);

        $items = PostingResource::collection($posting);

        $response = [
            'pagination' => json_pagination_response($items),
            'data' => $items,
        ];

        return json_custom_response($response);
    }

    public function savePostData(UserPostingRequest $request)
    {
        $data = $request->all();

        $user = auth()->user();
        $data['user_id'] = $user->id;
        $data['status'] = request('status') ?? 'published';
        $posting = Posting::create($data);

        if( $request->hasFile('posting_media')) {
            storeMediaFile($posting, $request->posting_media, 'posting_media');
        }

        $message = __('message.post_add_form');

        return json_message_response($message);
    }

    public function updatePostData(UserPostingRequest $request)
    {
        $data = $request->all();

        $posting = Posting::myPosting()->where('id', $data['id'])->first();

        $message = __('message.not_found_entry', ['name' => __('message.posting') ]);
        $status_code = 400;

        if( $posting != null )
        {
            $posting->fill($data)->update();

            if ($request->hasFile('posting_media')) {
                foreach ($request->posting_media as $key => $value){
                    $posting->addMedia($value)->toMediaCollection('posting_media');
                }
            }

            $message = __('message.post_update_form');
            $status_code = 200;
        }
        return json_message_response( $message, $status_code);
    }

    public function deletePostdata(Request $request)
    {
        $posting = Posting::myPosting()->where('id', $request->id)->first();

        $message = __('message.not_found_entry', ['name' => __('message.posting') ]);
        $status_code = 400;

        if( $posting != null )
        {
            $posting->delete();
            $status_code = 200;
            $message = __('message.post_delete_form');
        }

        return json_message_response( $message, $status_code);
    }

    public function removePostMedia(Request $request)
    {
        $posting = Posting::myPosting()->where('id', request('posting_id'))->first();

        $message = __('message.not_found_entry', ['name' => __('message.posting') ]);
        $status_code = 400;

        if( $posting != null ) {
            $message = __('message.not_found_entry', ['name' => __('message.post_media') ]);
            if( !is_null(request('ids')) && !empty(request('ids')) ) {
                Media::whereIn('id', request('ids'))->delete();
                $message = __('message.msg_removed', ['name' => __('message.post_media')]);
                $status_code = 200;
            }
        }
        return json_message_response( $message, $status_code);
    }

    public function userLikePost(Request $request)
    {
        $user_id = auth()->id();
        $posting_id = $request->posting_id;

        $posting = Posting::where('id', $posting_id )->first();

        if( $posting == null ) {
            return json_message_response( __('message.not_found_entry', [ 'name' => __('message.posting') ]) );
        }

        $post_like = PostingLike::where('user_id', $user_id)->where('posting_id', $posting_id)->first();
        $message = null;
        if( $post_like != null ) {
            $post_like->delete();
        } else {
            $data = [
                'user_id'   => $user_id,
                'posting_id'=> $posting_id,
            ];

            PostingLike::create($data);
            $message = null;
        }

        return json_message_response($message);
    }

    public function getUserLikePostList(Request $request)
    {
        $posting_id = $request->posting_id;

        $posting = Posting::where('id', $posting_id )->first();

        if( $posting == null ) {
            return json_message_response( __('message.not_found_entry', [ 'name' => __('message.posting') ]) );
        }

        $post_like = PostingLike::where('posting_id', $posting_id);

        $per_page = config('constant.PER_PAGE_LIMIT');
        if ($request->has('per_page') && !empty($request->per_page)) {
            if (is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            }
            if ($request->per_page == -1) {
                $per_page = $post_like->count();
            }
        }

        $post_like = $post_like->orderBy('id', 'desc')->paginate($per_page);

        $items = UserLikeResource::collection($post_like);

        $response = [
            'pagination' => json_pagination_response($items),
            'data' => $items,
        ];

        return json_custom_response($response);
    }

    public function userBookmarkPost(Request $request)
    {
        $user_id = auth()->id();
        $posting_id = $request->posting_id;

        $posting = Posting::where('id', $posting_id )->first();

        if( $posting == null ) {
            return json_message_response( __('message.not_found_entry', [ 'name' => __('message.posting') ]) );
        }

        $post_bookmark = PostingBookmark::where('user_id', $user_id)->where('posting_id', $posting_id)->first();
        $message = null;
        if( $post_bookmark != null ) {
            $post_bookmark->delete();
        } else {
            $data = [
                'user_id'   => $user_id,
                'posting_id'=> $posting_id,
            ];

            PostingBookmark::create($data);
            $message = null;
        }

        return json_message_response($message);
    }

    public function getMyBookmarkPostList(Request $request)
    {
        $post_bookmark = PostingBookmark::myBookmark();

        $per_page = config('constant.PER_PAGE_LIMIT');
        if ($request->has('per_page') && !empty($request->per_page)) {
            if (is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            }
            if ($request->per_page == -1) {
                $per_page = $post_bookmark->count();
            }
        }

        $post_bookmark = $post_bookmark->orderBy('id', 'desc')->paginate($per_page);

        $items = UserBookmarkResource::collection($post_bookmark);

        $response = [
            'pagination' => json_pagination_response($items),
            'data' => $items,
        ];

        return json_custom_response($response);
    }

    public function getPostDetail(Request $request)
    {
        $id = $request->id;

        $posting = Posting::where('id', $id )->withCount(['comment', 'postingLike'])
            ->with(['user:id,first_name,last_name,display_name,email,username,phone_number,profile_image',
                'postingLike' => fn ($q) => $q->where('user_id', auth()->id()),
                'postingBookmark' => fn ($q) => $q->where('user_id', auth()->id()),
            ])->first();

        if( $posting == null ) {
            return json_message_response( __('message.not_found_entry', [ 'name' => __('message.posting') ]) );
        }

        $posting_data = new PostingResource($posting);
        $response = [
            'data' => $posting_data,
        ];

        return json_custom_response($response);
    }

    public function reportOnPosting(Request $request)
    {
        $user_id = auth()->id();
        $posting_id = $request->posting_id;

        $posting = Posting::where('id', $posting_id )->first();

        if( $posting == null ) {
            return json_message_response( __('message.not_found_entry', [ 'name' => __('message.posting') ]) );
        }
        $data = [
            'user_id'   => $user_id,
            'posting_id'=> $posting_id,
            'reason'    => $request->reason ?? null,
        ];

        $report_post = ReportPosting::create( $data );

        $message = __('message.report_on_post');

        return json_message_response($message);
    }

}
