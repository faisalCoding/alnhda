<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBacklinkRequest extends FormRequest
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
            'url' => 'required|url|max:2048',
            'target_url' => 'nullable|url|max:2048',
            'visits' => 'nullable|integer|min:0',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم المنصة مطلوب.',
            'url.required' => 'رابط المنصة مطلوب.',
            'url.url' => 'الرابط غير صالح، ابدأه بـ https://',
            'target_url.url' => 'رابط الصفحة المحال إليها غير صالح.',
            'visits.integer' => 'عدد الزوار يجب أن يكون رقماً.',
        ];
    }
}
