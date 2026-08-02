<?php

namespace App\Http\Controllers;

use App\DataTables\DefaultKeywordDataTable;
use App\Http\Requests\DefaultKeywordRequest;
use App\Models\DefaultKeyword;
use App\Models\LanguageList;
use App\Models\LanguageWithKeyword;
use App\Models\Screen;
use App\Helpers\AuthHelper;

class DefaultkeywordController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(DefaultKeywordDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.default_keyword')] );
        $auth_user = AuthHelper::authSession();
        if (!auth()->user()->can('defaultkeyword-list')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['datatable'];
        $screen = request('screen') ?? null;
        if ($screen != null) {
            $screen = Screen::where('screenId',$screen)->first();
        }
        $button = $auth_user->can('defaultkeyword-add') ? '<a href="javascript:void(0)" class="float-right btn btn-sm btn-primary" href="javascript:void(0)" data-modal-form="form" data-size="small" data--href="'.route('defaultkeyword.create').'" data-placement="top" data-app-title="'.__('message.add_form_title',['form' => __('message.keyword')]).'">'.__('message.add_form_title',['form' => __('message.keyword')]).'</a>' : '';
        return $dataTable->render('global.defaultkeyword-datatable', compact('pageTitle','button','auth_user','screen'));

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!auth()->user()->can('defaultkeyword-add')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $lastKeyword = DefaultKeyword::selectRaw('MAX(CAST(keyword_id AS UNSIGNED)) as max_keyword_id')->value('max_keyword_id');
        $lastKeywordId = $lastKeyword ? $lastKeyword + 1 : 1;
    
        $pageTitle = __('message.add_form_title',[ 'form' => __('message.keyword')]);
        
        $view = view('app-language-setting.defaultkeyword.form', compact('pageTitle','lastKeywordId'))->render();

        return response()->json([ 'data' => $view, 'status' => true ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(DefaultKeywordRequest $request)
    {
        if (!auth()->user()->can('defaultkeyword-add')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $requestData = $request->all();
        $requestData['keyword_name'] = str_replace(' ', '_', $requestData['keyword_name']);
        $keywordData = DefaultKeyword::create($requestData);
    
        $language = LanguageList::all();
        if(count($language) > 0){
            foreach($language as $value){
                $languagedata = [
                    'id' => null,
                    'keyword_id' => $keywordData->keyword_id,
                    'screen_id' => $keywordData->screen_id,
                    'language_id' => $value->id,
                    'keyword_value' => $keywordData->keyword_value,
                ];
                LanguageWithKeyword::create($languagedata);
            }
        }
        updateLanguageVersion();
        $message = __('message.save_form', ['form' => __('message.default_keyword')]);
        return response()->json(['status' => true, 'event' => 'submited','message' => $message]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
   
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!auth()->user()->can('defaultkeyword-edit')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.keyword')]);
        $data = DefaultKeyword::findOrFail($id);
        $view = view('app-language-setting.defaultkeyword.form', compact('data', 'pageTitle', 'id'))->render();

        return response()->json([ 'data' => $view, 'status' => true ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(DefaultKeywordRequest $request, $id)
    {
        if (!auth()->user()->can('defaultkeyword-edit')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $keys = DefaultKeyword::find($id);
        
        $message = __('message.not_found_entry', ['name' => __('message.default_keyword')]);
        if($keys == null) {
            return response()->json(['status' => false, 'message' => $message ]);
        }
        // keys_table data...
        $requestData = $request->all();
        $requestData['keyword_name'] = $keys->keyword_name;

        $keys->fill($requestData)->update();
        
        updateLanguageVersion();
        $message = __('message.update_form',['form' => __('message.default_keyword')]);

        if(auth()->check()){
            return response()->json(['status' => true, 'event' => 'submited','message'=> $message]);
            
        }
        return response()->json(['status' => true, 'event' => 'submited', 'message'=> $message]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {

    }
}
