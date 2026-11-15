<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What the hand-entered units are narrowed and ordered by when they are
 * downloaded: the same district, kind and order the table on the screen is
 * showing.
 */
class ExportManualMarketUnitsRequest extends FormRequest
{
    /**
     * The orders the table can be put in; «seen» is the newest seen first.
     *
     * @var list<string>
     */
    public const SORTS = ['seen', 'price_asc', 'price_desc', 'area_asc', 'area_desc'];

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
            'district' => 'nullable|string|max:120',
            'kind' => 'nullable|string|max:30',
            'sort' => 'nullable|in:'.implode(',', self::SORTS),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sort.in' => 'ترتيب غير معروف.',
            'district.max' => 'اسم الحي أطول من المعتاد.',
            'kind.max' => 'نوع العقار أطول من المعتاد.',
        ];
    }
}
