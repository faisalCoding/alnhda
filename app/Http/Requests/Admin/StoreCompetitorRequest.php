<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompetitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('note') === '') {
            $this->merge(['note' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'manual_market_unit_id' => 'required|integer|exists:manual_market_units,id',
            'note' => 'nullable|string|max:500',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'manual_market_unit_id.required' => 'اختر الوحدة المنافسة.',
            'manual_market_unit_id.exists' => 'هذه الوحدة لم تعد في وحدات السوق.',
            'note.max' => 'الملاحظة أطول من المقبول.',
        ];
    }
}
