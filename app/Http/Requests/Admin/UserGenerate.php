<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UserGenerate extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'generate_count' => 'nullable|integer|max:500',
            'expired_at' => 'nullable|integer',
            'plan_id' => 'nullable|integer',
            'email_prefix' => 'nullable',
            'email_suffix' => 'required',
            'password' => 'nullable'
        ];
    }

    public function messages()
    {
        return [
            'generate_count.integer' => 'Generation count must be a number',
            'generate_count.max' => 'Generation count maximum is 500'
        ];
    }
}
