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
            'name' => 'sometimes|required|string|max:255',
            'identifier' => 'sometimes|required|string|max:255',
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
            'expires_on.date' => 'تاريخ الانتهاء غير صالح.',
        ];
    }
}
