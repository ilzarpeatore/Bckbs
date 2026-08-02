<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChatgptFitBot;
use App\Http\Resources\ChatgptFitBotResource;

class ChatgptFitBotController extends Controller
{
    public function store(Request $request)
    {

        $request['user_id'] = auth()->id();
        ChatgptFitBot::create($request->all());
        
        return json_message_response(__('message.save_form', ['form' => __('message.data')]));
    }

    public function getList(Request $request)
    {
        $chatgpt_fit_bot = ChatgptFitBot::where('user_id',auth()->id())->orderBy('id', 'Desc')->limit(200)->get();
        $items = ChatgptFitBotResource::collection($chatgpt_fit_bot);
        
        return json_custom_response([ 'data' => $items ]);
    }

    public function destroy()
    {
        $auth_user = auth()->user();
        $message = __('message.not_found_entry', ['name' => __('message.data')]);

        if ( isset($auth_user) ) {
            $auth_user->chatgptFitBot()->delete();
            $message = __('message.delete_form', ['form' => __('message.data')]);
        }
        
        return json_message_response( $message );
    }
}
