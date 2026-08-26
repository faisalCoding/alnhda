<?php

namespace App\Http\Requests\Admin;

use App\Services\SeoRecordDefaults;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSeoDefaultsRequest extends FormRequest
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
            'seo_default_title' => 'nullable|string|max:120',
            'seo_default_description' => 'nullable|string|max:'.(SeoRecordDefaults::DESCRIPTION_LIMIT * 2),
            'seo_default_image_path' => 'nullable|string|max:255',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'seo_default_title.max' => 'العنوان الافتراضي طويل جدًا.',
            'seo_default_description.max' => 'الوصف الافتراضي طويل جدًا — جوجل يقصّ ما بعد ١٥٥ حرفًا تقريبًا.',
        ];
    }
}
