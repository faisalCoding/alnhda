<?php

namespace App\Http\Requests\Admin;

use App\Enums\ComparisonSource;
use App\Models\MarketDeed;
use App\Services\Market\PlaceName;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectMarketDistrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A project can only be placed in a district the deeds know, since a
     * district they do not know has nothing to compare the project with.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'city' => 'required|string|max:80',
            'source' => ['nullable', 'string', Rule::in(ComparisonSource::values())],
            'district' => [
                'present',
                'nullable',
                'string',
                'max:120',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $known = MarketDeed::query()
                        ->where('city_key', PlaceName::key((string) $this->input('city')))
                        ->where('district_key', PlaceName::key((string) $value))
                        ->exists();

                    if (filled($value) && ! $known) {
                        $fail('هذا الحي غير موجود في صفقات المدينة المرفوعة.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'city.required' => 'المدينة مطلوبة.',
            'source.in' => 'اختر مصدر المقارنة: بيانات وزارة العدل أو الوحدات المضافة يدويًا.',
            'district.present' => 'اختر حيًّا أو أرسله فارغًا لإلغاء الربط.',
        ];
    }
}
