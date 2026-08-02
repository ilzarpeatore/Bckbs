<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;


class RecipeRequest extends FormRequest
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
                    'preparation_time' => 'required',
                    'type' => 'required',
                    'meal_type' => 'required',
                    'recipe_image' => 'nullable|image|mimes:jpg,jpeg,png', 
                    'ingredients.*.ingredient_id'   => 'required',
                    'ingredients.*.quantity_grams' => 'required|numeric|min:0.01',                   
                    'ingredients.*.quantity'        => 'required|numeric|min:0.01',
                    'ingredients.*.amount'          => 'nullable|numeric|min:0',
                ];
                break;
            case 'patch':
                $rules = [
                    'title' => 'required',
                    'preparation_time' => 'required',
                    'type' => 'required',
                    'meal_type' => 'required',
                    'recipe_image' => 'nullable|image|mimes:jpg,jpeg,png',   
                    'ingredients.*.ingredient_id'   => 'required',
                    'ingredients.*.quantity_grams' => 'required|numeric|min:0.01',                
                    'ingredients.*.quantity'        => 'required|numeric|min:0.01',
                    'ingredients.*.amount'          => 'nullable|numeric|min:0',
                ];
                break;
        }

        return $rules;
    }

    public function messages()
    {
        return [ 
            'ingredients.*.ingredient_id.required' => 'Please select an ingredient.',
            'ingredients.*.quantity.required' => 'Please enter quantity.',
            'ingredients.*.amount.required' => 'Please enter weight per unit.',
            'ingredients.*.quantity_grams.required' => 'Total grams calculation is missing.',
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
