<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class MeasurementUnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules()
    {
        $method = strtolower($this->method());

        $rules = [];
        $titleUniqueRule = Rule::unique('measurement_units', 'title');
        $symbolUniqueRule = Rule::unique('measurement_units', 'symbol');

        switch ($method) {
            case 'post':
                $rules = [
                    'title' => [
                        'required',
                        $titleUniqueRule,
                    ],

                    'symbol' => [
                        'required',
                        $symbolUniqueRule,
                    ],
                    'unit_type' => 'required',
                    'base_conversion_factor' => 'nullable|numeric',
                    'is_standard' => 'nullable|boolean',
                ];
                break;

            case 'patch':
                $id = $this->id ?? request()->route('measurementunit');
                $rules = [
                    'title' => [
                        'required',
                        $titleUniqueRule->ignore($id),
                    ],

                    'symbol' => [
                        'required',
                        $symbolUniqueRule->ignore($id),
                    ],
                    'unit_type' => 'required',
                    'base_conversion_factor' => 'nullable|numeric',
                    'is_standard' => 'nullable|boolean',
                ];
                break;
        }

        return $rules;
    }

    public function messages()
    {
        return [];
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

        if (request()->is('api*')) {
            throw new HttpResponseException(response()->json($data, 422));
        }

        if ($this->ajax()) {
            throw new HttpResponseException(response()->json($data, 422));
        } else {
            throw new HttpResponseException(
                redirect()->back()->withInput()->with('errors', $validator->errors())
            );
        }
    }
}
