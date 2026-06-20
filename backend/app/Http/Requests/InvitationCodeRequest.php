<?php

namespace App\Http\Requests;

use App\Exceptions\InvitationCodeException;
use App\Models\InvitationCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InvitationCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invitationCode = $this->route('invitationCode');

        if ($invitationCode instanceof InvitationCode) {
            return Gate::allows('update', $invitationCode);
        }

        return Gate::allows('create', InvitationCode::class);
    }

    protected function failedAuthorization(): void
    {
        $action = $this->route('invitationCode') ? '更新' : '创建';
        throw InvitationCodeException::unauthorized($action);
    }

    public function rules(): array
    {
        $invitationCodeId = optional($this->route('invitationCode'))->id;

        return [
            'customer_group_id' => 'required|exists:customer_groups,id,deleted_at,NULL',
            'code' => [
                'nullable',
                'string',
                'max:20',
                'min:4',
                'regex:/^[A-Z0-9]+$/',
                "unique:invitation_codes,code,{$invitationCodeId},id,deleted_at,NULL",
            ],
            'description' => 'nullable|string|max:255',
            'max_uses' => 'required|integer|min:0|max:1000000',
            'expires_at' => 'nullable|date|after:now',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_group_id.required' => '请选择客户分组',
            'customer_group_id.exists' => '客户分组不存在或已删除',
            'code.min' => '邀请码长度不能少于 4 个字符',
            'code.max' => '邀请码长度不能超过 20 个字符',
            'code.regex' => '邀请码只能包含大写字母和数字',
            'code.unique' => '邀请码已存在',
            'max_uses.required' => '请填写最大使用次数',
            'max_uses.min' => '最大使用次数不能为负数',
            'max_uses.max' => '最大使用次数不能超过 1,000,000',
            'expires_at.after' => '过期时间必须晚于当前时间',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException($validator, response()->json([
            'message' => '数据验证失败',
            'error_code' => InvitationCodeException::VALIDATION_ERROR,
            'errors' => $validator->errors(),
        ], 422));
    }

    public function validatedData(): array
    {
        $data = $this->validated();

        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        return $data;
    }
}
