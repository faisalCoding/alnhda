<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMarketCatalogueRequest extends FormRequest
{
    /**
     * The token and its ability are checked by the route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A download link ends up as a link in the panel, so it has to point at
     * the portal: a token is not a licence to put any address in front of an
     * admin.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $file = [
            'year' => 'required|integer|between:2000,2100',
            'quarter' => 'required|integer|between:1,4',
            'dataset_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'download_url' => 'nullable|string|max:600|starts_with:https://open.data.gov.sa/',
            'file_updated_at' => 'nullable|date',
        ];

        return [
            'quarters' => 'required|array|min:1|max:200',
            'indicator_files' => 'present|array|max:500',
            ...collect($file)->mapWithKeys(fn ($rule, string $field): array => ['quarters.*.'.$field => $rule])->all(),
            ...collect($file)->mapWithKeys(fn ($rule, string $field): array => ['indicator_files.*.'.$field => $rule])->all(),
            'indicator_files.*.region' => 'required|string|max:60',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quarters.required' => 'قائمة أرباع الصفقات مطلوبة.',
            'quarters.min' => 'قائمة أرباع الصفقات فارغة.',
            'indicator_files.present' => 'قائمة ملفات المؤشرات مطلوبة ولو كانت فارغة.',
            '*.*.download_url.starts_with' => 'رابط التنزيل يجب أن يكون على منصة البيانات المفتوحة.',
            '*.*.dataset_id.regex' => 'معرّف مجموعة البيانات غير صالح.',
            '*.*.year.between' => 'السنة خارج المدى المقبول.',
            '*.*.quarter.between' => 'الربع يجب أن يكون بين 1 و4.',
        ];
    }
}
