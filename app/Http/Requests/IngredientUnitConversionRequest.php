<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class IngredientUnitConversionRequest extends FormRequest
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

        $uniqueRule = Rule::unique('ingredient_unit_conversions')->where(function ($query) {
            return $query->where('ingredient_id', $this->ingredient_id);
        });

        switch ($method) {
            case 'post':
                $rules= [
                    'ingredient_id' => 'required|exists:ingredients,id',
                    'measurement_unit_id' => [
                        'required',
                        'exists:measurement_units,id',
                        $uniqueRule,
                    ],                    
                    'gram_equivalent' => 'required|numeric|min:0',
                ];
            break;

            case 'patch':
                $id = $this->id ?? request()->route('ingredient_unit_conversion');
                $rules = [
                    'ingredient_id' => 'required|exists:ingredients,id',
                    'measurement_unit_id' => [
                        'required',
                        'exists:measurement_units,id',
                        $uniqueRule->ignore($id),
                    ],                    
                    'gram_equivalent' => 'required|numeric|min:0',
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
