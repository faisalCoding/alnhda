<?php

namespace App\Http\Requests\Admin;

use App\Services\Market\MarketPlaces;
use App\Services\Market\PlaceName;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualMarketUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An empty text field means nothing was entered, not an empty value.
     */
    protected function prepareForValidation(): void
    {
        $optional = [
            'name', 'project_name', 'source_site', 'listing_id', 'rooms', 'bedrooms', 'bathrooms', 'street_width',
            'facade', 'property_age', 'property_age_years', 'posted_on', 'listing_updated_on', 'observed_on',
            'source_url', 'duplicates',
        ];

        foreach ($optional as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        if (filled($this->input('source_site'))) {
            $this->merge(['source_site' => strtolower(trim((string) $this->input('source_site')))]);
        }
    }

    /**
     * What a comparison reads off a unit — a place, a kind, a size and a
     * price — in the market's own words, so the unit lines up with the
     * company's units and with the deeds; and, all optional, the rest of what
     * a listing says, in the same fields a listings file has.
     *
     * A listing already entered — the same number on the same site — cannot
     * be entered again; it is corrected instead.
     *
     * The district has to be one the deeds know for that city: a district
     * typed another way would be compared with nothing. A city the deeds have
     * not been uploaded for has no list to check against, and any district is
     * taken.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $places = app(MarketPlaces::class);

        $editing = $this->route('manualMarketUnit');

        return [
            'name' => 'nullable|string|max:255',
            'project_name' => 'nullable|string|max:255',
            'source_site' => 'nullable|string|max:40|regex:/^[a-z0-9.-]+$/',
            'listing_id' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('manual_market_units', 'listing_id')
                    ->where('source_site', $this->input('source_site'))
                    ->ignore($editing?->id),
            ],
            'rooms' => 'nullable|integer|min:0|max:200',
            'bedrooms' => 'nullable|integer|min:0|max:200',
            'bathrooms' => 'nullable|integer|min:0|max:200',
            'street_width' => 'nullable|string|max:40',
            'facade' => 'nullable|string|max:60',
            'property_age' => 'nullable|string|max:120',
            'property_age_years' => 'nullable|integer|min:0|max:200',
            'posted_on' => 'nullable|date|before_or_equal:today',
            'listing_updated_on' => 'nullable|date|before_or_equal:today',
            'duplicates' => 'nullable|string|max:500',
            'city' => 'required|string|max:80',
            'district' => [
                'required',
                'string',
                'max:120',
                function (string $attribute, mixed $value, Closure $fail) use ($places): void {
                    $known = array_column($places->districts(PlaceName::key((string) $this->input('city'))), 'key');

                    if ($known !== [] && ! in_array(PlaceName::key((string) $value), $known, true)) {
                        $fail('هذا الحي ليس من أحياء المدينة في بيانات السوق. اختره من القائمة.');
                    }
                },
            ],
            'kind' => ['required', 'string', 'max:30', Rule::in($places->kinds())],
            'area' => 'required|numeric|min:1|max:1000000',
            'price' => 'required|integer|min:1',
            'observed_on' => 'nullable|date|before_or_equal:today',
            'source_url' => 'nullable|url|max:2048',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'city.required' => 'المدينة مطلوبة.',
            'district.required' => 'الحي مطلوب.',
            'kind.required' => 'نوع الوحدة مطلوب.',
            'kind.in' => 'هذا النوع ليس من الأنواع التي تُسعَّر بها السوق.',
            'area.required' => 'المساحة مطلوبة.',
            'area.numeric' => 'المساحة يجب أن تكون رقمًا.',
            'area.min' => 'المساحة يجب أن تكون أكبر من صفر.',
            'price.required' => 'السعر مطلوب.',
            'price.integer' => 'السعر يجب أن يكون رقمًا صحيحًا.',
            'price.min' => 'السعر يجب أن يكون أكبر من صفر.',
            'observed_on.date' => 'تاريخ الرصد غير صالح.',
            'observed_on.before_or_equal' => 'تاريخ الرصد لا يكون في المستقبل.',
            'source_url.url' => 'رابط الإعلان غير صالح، ابدأه بـ https://',
            'source_site.regex' => 'اكتب الموقع بحروف إنجليزية صغيرة: aqar أو bayut أو haraj.',
            'listing_id.unique' => 'هذا الإعلان مضاف من قبل على نفس الموقع. عدّله بدل إضافته مرة أخرى.',
            '*.integer' => 'اكتب رقمًا صحيحًا.',
            'posted_on.before_or_equal' => 'تاريخ النشر لا يكون في المستقبل.',
            'listing_updated_on.before_or_equal' => 'تاريخ التحديث لا يكون في المستقبل.',
        ];
    }
}
