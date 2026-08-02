<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReportPostingRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'posting_id' => [
                'required',
                Rule::unique('report_postings')->where('user_id', auth()->id())
            ],
            'reason' => 'required|string|min:1'
        ];
    }

    public function messages()
    {
        return [
            'posting_id.unique' => __('message.already_reported_this_post'),
        ];
    }

     /**
     * @param Validator $validator
     */
    protected function failedValidation(Validator $validator){
        $data = [
            'status' => false,
            'type' => 'report',
            'event' => 'validation',
            'posting_id' => request('posting_id'),
            'message' => $validator->errors()->first(),
            'all_message' =>  $validator->errors()
        ];

        if ( request()->is('api*') ){
           throw new HttpResponseException( response()->json($data,422) );
        }
        if ($this->ajax()) {
            throw new HttpResponseException(response()->json($data,200));
        } else {
            throw new HttpResponseException(redirect()->back()->withInput()->with('errors', $validator->errors()));
        }
    }
}
