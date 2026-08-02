<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\LanguageWithKeyword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LanguageKeywordController extends BaseController
{
    protected function getModelClass(): string
    {
        return LanguageWithKeyword::class;
    }

    protected function getResourceClass(): string
    {
        return \App\Http\Resources\LanguageTableResource::class;
    }

    public function index(Request $request)
    {
        $query = LanguageWithKeyword::with(['language', 'keyword', 'screen']);

        if ($request->filled('language_id')) {
            $query->where('language_id', $request->language_id);
        }

        if ($request->filled('screen_id')) {
            $query->where('screen_id', $request->screen_id);
        }

        $perPage = $request->get('per_page', config('constant.PER_PAGE_LIMIT', 10));
        $items = $query->orderBy('id', 'desc')->paginate($perPage);

        $response = [
            'pagination' => json_pagination_response($items),
            'data'       => $items,
        ];

        return json_custom_response($response);
    }

    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'keywords'   => 'required|array',
            'keywords.*' => 'required|array',
        ]);

        DB::beginTransaction();

        try {
            foreach ($request->keywords as $keyword) {
                LanguageWithKeyword::updateOrCreate(
                    [
                        'language_id' => $keyword['language_id'],
                        'keyword_id'  => $keyword['keyword_id'],
                        'screen_id'   => $keyword['screen_id'],
                    ],
                    ['keyword_value' => $keyword['keyword_value']]
                );
            }

            DB::commit();

            return json_message_response('Keywords updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return json_message_response('Failed to update keywords.', 500);
        }
    }
}
