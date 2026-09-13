<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\LanguageWithKeyword;
use App\Exports\LanguageWithKeywordExport;
use App\Imports\ImportLanguageWithKeyword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

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
        // FIX (auditoría 2026-09-13): 'language'/'keyword' no existen como
        // relaciones en LanguageWithKeyword (se llaman 'languagelist'/
        // 'defaultkeyword') -- con()/with() con un nombre inválido lanza
        // RelationNotFoundException, este endpoint devolvía 500 siempre.
        $query = LanguageWithKeyword::with(['languagelist', 'defaultkeyword', 'screen']);

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

    /**
     * Import/export de traducciones (item 8, auditoria de migracion
     * 2026-09-11) -- mismas clases Export/Import que ya usa
     * LanguageWithKeywordListController (Blade), solo expuestas via /admin.
     */
    public function export(Request $request)
    {
        return Excel::download(new LanguageWithKeywordExport, 'language-with-keyword-' . date('Ymd_H_i_s') . '.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function import(Request $request)
    {
        $request->validate(['language_with_keyword' => 'required|file|mimes:csv,txt']);

        $path = $request->file('language_with_keyword')->store('files');
        Excel::import(new ImportLanguageWithKeyword, $path);

        return json_message_response('Traducciones importadas.');
    }
}
