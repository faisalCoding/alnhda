<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
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
            'account_id' => 'nullable|integer|exists:accounts,id',
            'amount' => 'nullable|numeric|min:0|max:99999999',
            'paid_on' => 'nullable|date',
            'name' => 'nullable|required_without:account_id|string|max:255',
            'expires_on' => 'nullable|date',
            'payment_account' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:2000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required_without' => 'اختر حساباً مرتبطاً أو اكتب اسماً للسجل.',
            'amount.numeric' => 'القيمة يجب أن تكون رقماً.',
            'paid_on.date' => 'تاريخ الدفع غير صالح.',
            'expires_on.date' => 'تاريخ الانتهاء غير صالح.',
        ];
    }
}
