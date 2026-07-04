<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadProjectImageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => 'required|image|max:40960|dimensions:max_width=3000,max_height=3000|mimes:jpg,jpeg,png,webp,bmp,gif',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'الصورة مطلوبة.',
            'image.image' => 'الملف يجب أن يكون صورة.',
            'image.max' => 'حجم الصورة يجب ألا يتجاوز 40 ميجابايت.',
            'image.dimensions' => 'أبعاد الصورة يجب ألا تتجاوز 3000×3000 بكسل.',
            'image.mimes' => 'صيغة الصورة غير مدعومة.',
        ];
    }
}
