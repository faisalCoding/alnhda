<?php

namespace App\Http\Requests\Admin;

use App\Services\Market\MarketPlaces;
use App\Services\Market\PlaceName;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3',
            'description' => 'required|string|min:10',
            'location' => 'nullable|string',
            'market_city_key' => 'nullable|string|max:80',
            'market_district_key' => [
                'nullable',
                'string',
                'max:120',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $districts = app(MarketPlaces::class)->districts(PlaceName::key((string) $this->input('market_city_key')));

                    if (! in_array(PlaceName::key((string) $value), array_column($districts, 'key'), true)) {
                        $fail('هذا الحي ليس من أحياء المدينة المختارة في بيانات السوق.');
                    }
                },
            ],
            'status' => 'required|string',
            'project_type' => 'required|string',
            'map_url' => 'nullable|url',
            'guarantees' => 'nullable|array',
            'guarantees.*' => 'nullable|string',
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
            'name.required' => 'اسم المشروع مطلوب.',
            'name.min' => 'اسم المشروع يجب أن يكون 3 أحرف على الأقل.',
            'description.required' => 'وصف المشروع مطلوب.',
            'description.min' => 'وصف المشروع يجب أن يكون 10 أحرف على الأقل.',
            'status.required' => 'حالة المشروع مطلوبة.',
            'project_type.required' => 'نوع المشروع مطلوب.',
            'map_url.url' => 'رابط الخريطة غير صالح.',
            'market_city_key.max' => 'اسم المدينة أطول من المقبول.',
            'market_district_key.max' => 'اسم الحي أطول من المقبول.',
        ];
    }
}
