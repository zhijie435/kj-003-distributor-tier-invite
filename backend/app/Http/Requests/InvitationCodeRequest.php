<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvitationCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_group_id' => 'required|exists:customer_groups,id',
            'code' => 'nullable|string|max:20|unique:invitation_codes,code,'.optional($this->route('invitationCode'))->id,
            'description' => 'nullable|string|max:255',
            'max_uses' => 'integer|min:0',
            'expires_at' => 'nullable|date|after:now',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_group_id.required' => '请选择客户分组',
            'customer_group_id.exists' => '客户分组不存在',
            'code.unique' => '邀请码已存在',
            'expires_at.after' => '过期时间必须晚于当前时间',
        ];
    }
}
