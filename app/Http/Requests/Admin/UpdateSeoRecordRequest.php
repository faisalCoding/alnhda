<?php

namespace App\Http\Requests\Admin;

use App\Services\SeoRecordDefaults;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSeoRecordRequest extends FormRequest
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
            'title' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:'.(SeoRecordDefaults::DESCRIPTION_LIMIT * 2),
            'image_path' => 'nullable|string|max:255',
            'noindex' => 'boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.max' => 'العنوان طويل جدًا.',
            'description.max' => 'الوصف طويل جدًا — جوجل يقصّ ما بعد ١٥٥ حرفًا تقريبًا.',
        ];
    }
}
