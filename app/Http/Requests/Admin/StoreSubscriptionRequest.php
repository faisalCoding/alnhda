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
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|max:255',
            'url' => 'nullable|url|max:2048',
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
            'name.required' => 'اسم المنصة مطلوب.',
            'identifier.required' => 'البريد أو رقم الهاتف أو اسم المستخدم مطلوب.',
            'url.url' => 'رابط المنصة غير صالح، ابدأه بـ https://',
            'expires_on.date' => 'تاريخ الانتهاء غير صالح.',
        ];
    }
}
