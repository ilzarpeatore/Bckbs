<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;


class SubAdminRequest extends FormRequest
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
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        if ($this->has('phone_number')) {
            $this->merge([
                'phone_number' => str_replace('+', '', $this->phone_number),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [];

        $method = strtolower($this->method());
        $user_id = $this->route('subadmin');
        if (is_object($user_id)) {
            $user_id = $user_id->id;
        }

        switch ($method) {
            case 'post':
                $rules = [
                    'first_name' => 'required|string',
                    'last_name' => 'required|string',
                    'email' => 'required|max:191|email|unique:users',
                    'username' => 'required|unique:users,username',
                    'phone_number' => 'nullable|max:20|unique:users,phone_number',
                    'profile_image' => 'nullable|image|mimes:jpg,jpeg,png',
                    'password' => 'required|string|min:6',
                ];
            break;
            case 'patch':
            case 'put':
                $rules = [
                    'first_name' => 'required|string',
                    'last_name' => 'required|string',
                    'email' => 'required|max:191|email|unique:users,email,'.$user_id,
                    'username' => 'required|unique:users,username,'.$user_id,
                    'phone_number' => 'nullable|max:20|unique:users,phone_number,'.$user_id,
                    'profile_image' => 'nullable|image|mimes:jpg,jpeg,png',
                ];
            break;
        }

        return $rules;
    }

    public function messages()
    {
        return [

        ];
    }

     /**
     * @param Validator $validator
     */
    protected function failedValidation(Validator $validator){
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
