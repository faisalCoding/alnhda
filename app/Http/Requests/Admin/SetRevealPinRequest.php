<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SetRevealPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pin' => 'required|digits:4|confirmed',
            'current_password' => 'required|current_password:admin',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pin.required' => 'الرمز مطلوب.',
            'pin.digits' => 'الرمز يجب أن يكون أربعة أرقام.',
            'pin.confirmed' => 'الرمزان غير متطابقين.',
            'current_password.current_password' => 'كلمة مرور حسابك غير صحيحة.',
        ];
    }
}
