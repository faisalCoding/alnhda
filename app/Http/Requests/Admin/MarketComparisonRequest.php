<?php

namespace App\Http\Requests\Admin;

use App\Enums\ComparisonSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarketComparisonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'city' => 'nullable|string|max:80',
            'source' => ['nullable', 'string', Rule::in(ComparisonSource::values())],
        ];
    }

    public function source(): ComparisonSource
    {
        return ComparisonSource::fromRequest($this->validated('source'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source.in' => 'اختر مصدر المقارنة: بيانات وزارة العدل أو الوحدات المضافة يدويًا.',
        ];
    }
}
