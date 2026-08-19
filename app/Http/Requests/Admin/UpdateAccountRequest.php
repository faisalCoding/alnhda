<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
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
            'account_category_id' => 'sometimes|nullable|integer|exists:account_categories,id',
            'name' => 'sometimes|required|string|max:255',
            'identifier' => 'sometimes|required|string|max:255',
            'password' => 'sometimes|nullable|string|max:500',
            'sort_order' => 'sometimes|integer|min:0',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم المنصة مطلوب.',
            'identifier.required' => 'اسم المستخدم أو البريد أو رقم الهاتف مطلوب.',
        ];
    }
}
