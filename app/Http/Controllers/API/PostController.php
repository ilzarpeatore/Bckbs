<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post;
use App\Http\Resources\PostResource;
use App\Http\Resources\PostDetailResource;

class PostController extends Controller
{
    public function getList(Request $request)
    {
        $post = Post::where('status', 'publish');

        $post->when(request('title'), function ($q) {
            return $q->where('title', 'LIKE', '%' . request('title') . '%');
        });

        $post->when(request('search'), function ($q) {
            $search = request('search');
            return $q->where(function ($query) use ($search) {
                $query->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        });

        $post->when(request('is_featured'), function ($q) {
            return $q->where('is_featured', request('is_featured'));
        });

        $post->when(request('tags_id'), function ($q) {
            return $q->whereJsonContains('tags_id', request('tags_id'));
        });

        $post->when(request('category_ids'), function ($q) {
            return $q->whereJsonContains('category_ids', request('category_ids'));
        });

        $post->when(request('blog_category_id'), function ($q) {
            return $q->where('blog_category_id', request('blog_category_id'));
        });

        $orderBy = request('order_by', 'datetime');
        $orderDir = request('order_dir', 'desc');

        if (!in_array($orderBy, ['title', 'datetime', 'created_at', 'updated_at'])) {
            $orderBy = 'datetime';
        }
        if (!in_array($orderDir, ['asc', 'desc'])) {
            $orderDir = 'desc';
        }

        $post = $post->orderBy($orderBy, $orderDir);

        $per_page = config('constant.PER_PAGE_LIMIT');
        if ($request->has('per_page') && !empty($request->per_page)) {
            if (is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            }
            if ($request->per_page == -1) {
                $per_page = $post->count();
            }
        }

        $post = $post->paginate($per_page);

        $items = PostResource::collection($post)->resolve($request);

        $response = [
            'pagination' => json_pagination_response($post),
            'data'       => $items,
        ];

        return json_custom_response($response);
    }

    public function getDetail(Request $request)
    {
        $post = Post::where('id', request('id'))->first();

        if ($post == null) {
            return json_message_response(__('message.not_found_entry', ['name' => __('message.post')]));
        }

        $post_data = new PostDetailResource($post);
        $response = [
            'data' => $post_data,
        ];

        return json_custom_response($response);
    }
}
