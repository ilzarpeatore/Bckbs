<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class PushNotificationRequest extends FormRequest
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
     */
    public function rules()
    {
        $method = strtolower($this->method());
        $rules = [];

        switch ($method) {
            case 'post':
                $rules = [
                    'title' => 'required|string',
                    'message' => 'required|string',
                    'user' => 'required|array|min:1',
                    'user.*' => 'exists:users,id',
                    'notify_type' => 'nullable|string|in:new,resend',
                    'notification_image' => 'nullable|image|mimes:jpg,jpeg,png,gif',
                ];
                break;

            case 'patch':
                $rules = [
                    'title' => 'sometimes|required|string',
                    'message' => 'sometimes|required|string',
                    'user' => 'sometimes|required|array|min:1',
                    'user.*' => 'exists:users,id',
                    'notify_type' => 'nullable|string|in:new,resend',
                    'notification_image' => 'nullable|image|mimes:jpg,jpeg,png,gif',
                ];
                break;
        }

        return $rules;
    }

    /**
     * Custom validation messages.
     */
    public function messages()
    {
        return [ ];
    }

    /**
     * Handle validation failure for API, AJAX, and Web requests.
     */
    protected function failedValidation(Validator $validator)
    {
        $data = [
            'status' => false,
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
            redirect()->back()->withInput()->with('errors', $validator->errors())
        );
    }
}
