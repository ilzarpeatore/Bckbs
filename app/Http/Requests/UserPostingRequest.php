<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UserPostingRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $allowed = 'jpg,jpeg,png,gif,webp,bmp,tiff,mp4,mpeg,mov,avi,wmv,flv,ogg,webm';

        return [
            'posting_media' => 'nullable|array',
            'posting_media.*' => 'nullable|file|mimes:'.$allowed,
        ];
    }

    public function messages()
    {
        return [
            'posting_media.*.file' => __('frontend::message.posting_media_file'),
            'posting_media.*.mimes' => __('frontend::message.posting_media_mimes'),
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => false,
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors(),
        ], 422));
    }

    public function wantsJson()
    {
        return true;
    }
}
