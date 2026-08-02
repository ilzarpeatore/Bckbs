<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreShoppingListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shopping_list_id'  => 'nullable|integer|exists:shopping_lists,id',
            'daily_plan_id'     => 'nullable|integer|exists:daily_plans,id',
            'start_date'        => 'nullable|date',
            'end_date'          => 'nullable|date|after_or_equal:start_date',
            'meal_types'        => 'nullable|array',
            'meal_types.*'      => ['nullable', 'string', Rule::in(config('macro-nutrient.MEAL_TYPE'))],
            'is_complete_only'  => 'nullable|boolean',
            'title'             => 'nullable|string|max:255',
            'servings'          => 'nullable|numeric|min:0.1',
        ];
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
