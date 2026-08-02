<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class BannerSliderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $method = strtolower($this->method());
        $rules = [];

       switch ($method) {
        case 'post':
            $rules = [
                'title' => 'required|string|max:255',
                'type'  => 'required|in:workout,url',
                'workout_id' => 'required_if:type,workout|nullable|integer|exists:workouts,id',
                'url'        => 'required_if:type,url|nullable|url',
                'bannerslider_image' => 'nullable|image|mimes:jpg,jpeg,png,webp',
            ];
            break;

        case 'patch':
            $rules = [
                'title' => 'required|string|max:255',
                'type'  => 'required|in:workout,url',
                'workout_id' => 'required_if:type,workout|nullable|integer|exists:workouts,id',
                'url'        => 'required_if:type,url|nullable|url',
                'bannerslider_image' => 'nullable|image|mimes:jpg,jpeg,png,webp',
            ];
            break;
        }


        return $rules;
    }

    public function messages()
    {
        return [
            'workout_id.required_if' => __('validation.required_if', ['attribute' => __('message.workout'), 'other' => __('message.type'), 'value' => __('message.workout'), ]),
            'url.required_if' => __('validation.required_if', ['attribute' => __('message.url'), 'other' => __('message.type'), 'value' => __('message.url'), ])
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $data = [
            'status' => true,
            'message' => $validator->errors()->first(),
            'all_message' => $validator->errors(),
        ];

        if (request()->is('api*')) {
            throw new HttpResponseException(response()->json($data, 422));
        }

        if ($this->ajax()) {
            throw new HttpResponseException(response()->json($data, 422));
        }

        throw new HttpResponseException(
            redirect()->back()->withInput()->withErrors($validator->errors())
        );
    }
}
