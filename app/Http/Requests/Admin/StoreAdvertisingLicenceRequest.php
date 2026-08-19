<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdvertisingLicenceRequest extends FormRequest
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
            'properties_id' => 'nullable|integer|exists:properties,id',
            'unit_name' => 'nullable|required_without:properties_id|string|max:255',
            'licence_number' => 'required|string|max:100',
            'expires_on' => 'nullable|date',
            'note' => 'nullable|string|max:2000',
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
