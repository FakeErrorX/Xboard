<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UserFetch extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'filter.*.key' => 'required|in:id,email,transfer_enable,d,expired_at,uuid,token,invite_by_email,invite_user_id,plan_id,banned,remarks,is_admin',
            'filter.*.condition' => 'required|in:>,<,=,>=,<=,like,!=',
            'filter.*.value' => 'required'
        ];
    }

    public function messages()
    {
        return [
            'filter.*.key.required' => 'Filter key cannot be empty',
            'filter.*.key.in' => 'Filter key parameter is invalid',
            'filter.*.condition.required' => 'Filter condition cannot be empty',
            'filter.*.condition.in' => 'Filter condition parameter is invalid',
            'filter.*.value.required' => 'Filter value cannot be empty'
        ];
    }
}
