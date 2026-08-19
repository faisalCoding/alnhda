<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
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
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:account_categories,id',
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|max:255',
            'url' => 'nullable|url|max:2048',
            'password' => 'nullable|string|max:500',
            'apply_templates' => 'sometimes|boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم المنصة مطلوب.',
            'url.url' => 'رابط المنصة غير صالح، ابدأه بـ https://',
            'identifier.required' => 'اسم المستخدم أو البريد أو رقم الهاتف مطلوب.',
        ];
    }
}
