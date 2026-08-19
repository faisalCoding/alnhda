<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWeeklyReportSettingsRequest extends FormRequest
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
            'whatsapp_group_id' => 'nullable|string|max:255',
            'whatsapp_group_name' => 'nullable|string|max:255',
            'weekly_reports_enabled' => 'sometimes|boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'whatsapp_group_id.max' => 'معرّف المجموعة طويل أكثر من اللازم.',
        ];
    }
}
