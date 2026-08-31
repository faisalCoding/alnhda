<?php

namespace App\Http\Requests\Admin;

use App\Services\HomeFacts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHomeContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every field may be emptied, and emptying one is not a blank on the page —
     * it is a request to go back to the text the site ships with.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'hero_eyebrow' => 'nullable|string|max:120',
            'hero_title' => 'nullable|string|max:120',
            'hero_subtitle' => 'nullable|string|max:300',
            'hero_primary_label' => 'nullable|string|max:40',
            'hero_secondary_label' => 'nullable|string|max:40',
            'home_guarantees' => 'nullable|array|max:12',
            'home_guarantees.*' => 'nullable|string|max:160',
            'hidden_home_sections' => 'nullable|array',
            'hidden_home_sections.*' => ['string', Rule::in(array_keys(HomeFacts::SECTIONS))],
            'hero_image_path' => 'nullable|string|max:255',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hero_title.max' => 'العنوان الرئيسي طويل جدًا — جوجل وواجهة الجوال يقصّانه.',
            'hero_primary_label.max' => 'نص الزر طويل جدًا.',
            'hero_secondary_label.max' => 'نص الزر طويل جدًا.',
            'home_guarantees.max' => 'اكتفِ باثني عشر ضمانًا على الأكثر.',
            'home_guarantees.*.max' => 'نص الضمان طويل جدًا.',
            'hidden_home_sections.*.in' => 'قسم غير معروف في الصفحة الرئيسية.',
        ];
    }
}
