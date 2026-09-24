<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Support\FuzzySearch;
use App\Models\BlogCategory;

class BlogCategoryController extends Controller
{
    public function getList(Request $request)
    {
        $query = BlogCategory::where('status', 'active');

        if ($request->filled('search')) {
            FuzzySearch::apply($query, ['title'], $request->search);
        }

        $categories = $query->orderBy('title', 'asc')->get();

        $response = [
            'data' => $categories->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'post_count' => $item->posts()->where('status', 'publish')->count(),
                ];
            }),
        ];

        return json_custom_response($response);
    }
}
