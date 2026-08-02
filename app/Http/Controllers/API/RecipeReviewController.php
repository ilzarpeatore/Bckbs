<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecipeReviewRequest;
use App\Http\Resources\RecipeReviewResource;
use Illuminate\Http\Request;
use App\Models\RecipeReview;

class RecipeReviewController extends Controller
{
    public function saveRecipeReview(RecipeReviewRequest $request)
    {
        $auth_user = auth()->user();
        
        $data = $request->all();
        $data['user_id'] = $auth_user->id;

        $review = RecipeReview::updateOrCreate([ 'user_id' => $auth_user->id, 'recipe_id' => $request->recipe_id ], $data);
        
        $message = __('message.save_form',['form' => __('message.review') ]);

        $response = [
            'message' => $message,
            'data' => new RecipeReviewResource($review)
        ];

        return json_custom_response($response);
    }

    public function getReviewDetail(Request $request)
    {
        $review = RecipeReview::where('recipe_id', request('recipe_id'));
        
        $per_page = config('constant.PER_PAGE_LIMIT');
        if ($request->has('per_page') && !empty($request->per_page)) {
            if (is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            }
            if ($request->per_page == -1) {
                $per_page = $review->count();
            }
        }

        $review = $review->orderBy('id', 'desc')->paginate($per_page);

        $items = RecipeReviewResource::collection($review);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];
        
        return json_custom_response($response);
    }
}
