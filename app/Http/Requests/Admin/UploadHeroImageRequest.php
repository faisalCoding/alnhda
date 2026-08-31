<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadHeroImageRequest extends FormRequest
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
            'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:12288',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'اختر صورة أولًا.',
            'image.image' => 'الملف المرفوع ليس صورة.',
            'image.mimes' => 'الصيغ المقبولة: JPG أو PNG أو WEBP.',
            'image.max' => 'حجم الصورة أكبر من ١٢ ميجابايت.',
        ];
    }
}
