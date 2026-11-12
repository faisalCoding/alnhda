<?php

namespace App\Http\Requests\Admin;

use App\Services\Market\PlaceName;
use App\Services\Market\ProjectMarketComparison;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitMarketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A unit can only be given a kind the market figures are kept by: any
     * other word would compare it with nothing.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $kinds = app(ProjectMarketComparison::class)->kinds(PlaceName::key((string) $this->input('city')));

        return [
            'city' => 'required|string|max:80',
            'kind' => ['present', 'nullable', 'string', 'max:30', Rule::in($kinds)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'city.required' => 'المدينة مطلوبة.',
            'kind.present' => 'اختر نوعًا أو أرسله فارغًا للعودة إلى التخمين.',
            'kind.in' => 'هذا النوع ليس من الأنواع التي تُسعَّر بها السوق.',
        ];
    }
}
