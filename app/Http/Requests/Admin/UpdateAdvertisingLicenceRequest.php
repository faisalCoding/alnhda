<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdvertisingLicenceRequest extends FormRequest
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
            'properties_id' => 'sometimes|nullable|integer|exists:properties,id',
            'unit_name' => 'sometimes|nullable|required_without:properties_id|string|max:255',
            'licence_number' => 'sometimes|required|string|max:100',
            'expires_on' => 'sometimes|nullable|date',
            'note' => 'sometimes|nullable|string|max:2000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_name.required_without' => 'اختر وحدة من القائمة أو اكتب اسمها يدوياً.',
            'licence_number.required' => 'رقم الترخيص مطلوب.',
            'expires_on.date' => 'تاريخ الانتهاء غير صالح.',
        ];
    }
}
