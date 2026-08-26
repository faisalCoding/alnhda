<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadSeoImageRequest extends FormRequest
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
            'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:8192',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'اختر صورة أولًا.',
            'image.image' => 'الملف ليس صورة.',
            'image.mimes' => 'الصيغ المقبولة: JPG أو PNG أو WEBP.',
            'image.max' => 'حجم الصورة يتجاوز 8 ميجابايت.',
        ];
    }
}
