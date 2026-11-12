<?php

namespace App\Http\Requests\Admin;

use App\Services\Market\DeedExplorer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarketDeedsRequest extends FormRequest
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
            'district' => 'nullable|string|max:120',
            'classification' => 'nullable|string|max:30',
            'area_min' => 'nullable|numeric|min:0',
            'area_max' => 'nullable|numeric|min:0',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'quarters' => ['nullable', 'integer', Rule::in(DeedExplorer::SPANS)],
            'sort' => 'nullable|in:date,price,area,rate',
            'direction' => 'nullable|in:asc,desc',
            'page' => 'nullable|integer|min:1',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'area_min.numeric' => 'أدنى مساحة يجب أن تكون رقمًا.',
            'area_max.numeric' => 'أعلى مساحة يجب أن تكون رقمًا.',
            'price_min.numeric' => 'أدنى سعر يجب أن يكون رقمًا.',
            'price_max.numeric' => 'أعلى سعر يجب أن يكون رقمًا.',
            '*.min' => 'القيمة لا تكون سالبة.',
            'quarters.in' => 'المدة المختارة غير متاحة.',
            'sort.in' => 'الترتيب المختار غير متاح.',
        ];
    }
}
