<?php

namespace App\Http\Requests\Admin;

use App\Models\AccountCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountCategoryRequest extends FormRequest
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
            'name' => 'sometimes|required|string|max:255',
            'color' => ['sometimes|required', Rule::in(AccountCategory::COLORS)],
            'sort_order' => 'sometimes|integer|min:0',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم التصنيف مطلوب.',
            'color.in' => 'اللون المختار غير متاح.',
        ];
    }
}
