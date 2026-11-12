<?php

namespace App\Http\Requests\Admin;

use App\Services\Market\MarketPlaces;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportMarketListingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('kind') === '') {
            $this->merge(['kind' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => 'required|file|extensions:csv|max:20480',
            // For a listing whose title does not say what kind of property it is.
            'kind' => ['nullable', 'string', Rule::in([...app(MarketPlaces::class)->kinds(), ...MarketPlaces::USUAL_KINDS])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'اختر ملف الإعلانات أولًا.',
            'file.extensions' => 'ارفع ملف CSV.',
            'file.max' => 'حجم الملف أكبر من 20 ميجابايت.',
            'kind.in' => 'هذا النوع ليس من الأنواع التي تُسعَّر بها السوق.',
        ];
    }
}
