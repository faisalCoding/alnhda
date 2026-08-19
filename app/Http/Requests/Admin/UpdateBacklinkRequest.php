<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBacklinkRequest extends FormRequest
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
            'url' => 'sometimes|required|url|max:2048',
            'target_url' => 'sometimes|nullable|url|max:2048',
            'visits' => 'sometimes|nullable|integer|min:0',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم المنصة مطلوب.',
            'url.url' => 'الرابط غير صالح، ابدأه بـ https://',
            'target_url.url' => 'رابط الصفحة المحال إليها غير صالح.',
        ];
    }
}
