<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionRequest extends FormRequest
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
            'account_id' => 'sometimes|nullable|integer|exists:accounts,id',
            'amount' => 'sometimes|nullable|numeric|min:0|max:99999999',
            'paid_on' => 'sometimes|nullable|date',
            'name' => 'sometimes|required|string|max:255',
            'identifier' => 'sometimes|required|string|max:255',
            'url' => 'sometimes|nullable|url|max:2048',
            'expires_on' => 'sometimes|nullable|date',
            'payment_account' => 'sometimes|nullable|string|max:255',
            'note' => 'sometimes|nullable|string|max:2000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم المنصة مطلوب.',
            'identifier.required' => 'البريد أو رقم الهاتف أو اسم المستخدم مطلوب.',
            'url.url' => 'رابط المنصة غير صالح، ابدأه بـ https://',
            'amount.numeric' => 'القيمة يجب أن تكون رقماً.',
            'paid_on.date' => 'تاريخ الدفع غير صالح.',
            'expires_on.date' => 'تاريخ الانتهاء غير صالح.',
        ];
    }
}
