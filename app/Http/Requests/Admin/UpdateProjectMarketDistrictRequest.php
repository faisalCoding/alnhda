<?php

namespace App\Http\Requests\Admin;

use App\Services\Market\MarketPlaces;
use App\Services\Market\PlaceName;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectMarketDistrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A project can only be placed in a district the market figures know,
     * since a district they do not know has nothing to compare the project
     * with. These are the same districts the project form offers.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'city' => 'required|string|max:80',
            'district' => [
                'present',
                'nullable',
                'string',
                'max:120',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $known = array_column(
                        app(MarketPlaces::class)->districts(PlaceName::key((string) $this->input('city'))),
                        'key',
                    );

                    if (filled($value) && ! in_array(PlaceName::key((string) $value), $known, true)) {
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
            'district.present' => 'اختر حيًّا أو أرسله فارغًا لإلغاء الربط.',
        ];
    }
}
