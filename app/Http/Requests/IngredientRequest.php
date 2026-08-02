<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;


class IngredientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $method = strtolower($this->method());

        switch ($method) {
            case 'post':
                return [
                   'title' => 'required',
                    'calories_per_gram' => 'required',
                    'protein_per_gram' => 'nullable|numeric',
                    'fat_per_gram'     => 'nullable|numeric',
                    'carbs_per_gram'   => 'nullable|numeric',
                    'density'          => 'nullable|numeric',
                ];

            case 'patch':
                return [
                    'title' => 'required',
                    'calories_per_gram' => 'required',
                    'protein_per_gram' => 'nullable|numeric',
                    'fat_per_gram'     => 'nullable|numeric',
                    'carbs_per_gram'   => 'nullable|numeric',
                ];
        }

        return [];
    }


    public function messages()
    {
        return [ ];
    }
    /**
     * @param Validator $validator
     */
    protected function failedValidation(Validator $validator)
    {
        $data = [
            'status' => true,
            'message' => $validator->errors()->first(),
            'all_message' =>  $validator->errors()
        ];

        if ( request()->is('api*')){
           throw new HttpResponseException( response()->json($data,422) );
        }

        if ($this->ajax()) {
            throw new HttpResponseException(response()->json($data,422));
        } else {
            throw new HttpResponseException(redirect()->back()->withInput()->with('errors', $validator->errors()));
        }
    }
}
