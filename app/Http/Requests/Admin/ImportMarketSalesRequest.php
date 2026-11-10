<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportMarketSalesRequest extends FormRequest
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
            'file' => 'required|file|extensions:csv|max:102400',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'اختر ملف الصفقات أولًا.',
            'file.file' => 'تعذّر رفع الملف، حاول مرة أخرى.',
            'file.extensions' => 'ارفع ملف CSV كما نزّلته من منصة البيانات المفتوحة، لا ملف Excel.',
            'file.max' => 'حجم الملف أكبر من 100 ميجابايت.',
        ];
    }
}
