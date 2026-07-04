<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadPropertyImagesRequest extends FormRequest
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
            'photos' => 'required|array|min:1',
            'photos.*' => 'image|max:40960|mimes:jpg,jpeg,png,webp,bmp,gif',
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
            'photos.required' => 'يجب اختيار صورة واحدة على الأقل.',
            'photos.*.image' => 'كل الملفات يجب أن تكون صورًا.',
            'photos.*.max' => 'حجم كل صورة يجب ألا يتجاوز 40 ميجابايت.',
            'photos.*.mimes' => 'صيغة الصورة غير مدعومة.',
        ];
    }
}
