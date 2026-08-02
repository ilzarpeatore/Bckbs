<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkLanguageImportRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'language_with_keyword' => 'required|file|mimes:csv',
        ];
    }

    public function messages()
    {
        return [
        ];
    }
}
