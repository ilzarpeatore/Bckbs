<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserGraphRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        $type = $this->type;
        switch ($type) {
            case 'weight':
                $valueRule = 'required|numeric|min:0';
                break;
            case 'heart-rate':
            case 'push-up-min':
                $valueRule = 'required|integer|min:0';
                break;
            default:
                $valueRule = 'required';
                break;            
        }

        return [
            'type'  => 'required|in:weight,heart-rate,push-up-min',
            'value' => $valueRule,
            'date'  => 'required|date'
        ];
    }
    public function messages()
    {
        return [
            'value.required' => __('message.value_required'),
            'value.numeric'  => __('message.value_numeric'),
            'value.integer'  => __('message.value_integer'),
            'value.min'      => __('message.value_positive'),
            'date.required'  => __('message.date_required'),
            'date.date'      => __('message.invalid_date'),
        ];
    }
}
