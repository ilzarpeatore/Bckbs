<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;


class ExerciseRequest extends FormRequest
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

        $rules = [];
        switch ($method) {
            case 'post':
                $rules = [
                    'title' => 'required',
                    'level_id' => 'required',
                    'exercise_image' => 'nullable|image|mimes:jpg,jpeg,png',
                    'seconds_per_rep' => 'nullable|numeric|min:0',
                ];
                break;
            case 'patch':
                $rules = [
                    'title' => 'required',
                    'level_id' => 'required',
                    'exercise_image' => 'nullable|image|mimes:jpg,jpeg,png',
                    'seconds_per_rep' => 'nullable|numeric|min:0',
                ];
                break;
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'equipment_id.required' => __('validation.required', ['attribute' => __('message.equipment')]),
            'level_id.required' => __('validation.required', ['attribute' => __('message.level')])
        ];
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
