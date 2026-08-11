<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SendWhatsappRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => 'required|string|max:4000',
            'lead_ids' => 'required|array|min:1|max:500',
            'lead_ids.*' => 'integer|exists:leads,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'نص الرسالة مطلوب.',
            'lead_ids.required' => 'اختر عميلًا واحدًا على الأقل.',
            'lead_ids.max' => 'الحد الأقصى 500 عميل في الإرسال الواحد.',
        ];
    }
}
