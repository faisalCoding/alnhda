<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadFaviconRequest extends FormRequest
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
            // No SVG: the icon is rasterised into fixed sizes, and a vector
            // would have to be flattened here anyway with worse results.
            'favicon' => 'required|image|mimes:jpeg,jpg,png,webp|max:4096',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'favicon.required' => 'اختر صورة أولًا.',
            'favicon.image' => 'الملف ليس صورة.',
            'favicon.mimes' => 'الصيغ المقبولة: PNG أو JPG أو WEBP.',
            'favicon.max' => 'حجم الصورة يتجاوز 4 ميجابايت.',
        ];
    }
}
