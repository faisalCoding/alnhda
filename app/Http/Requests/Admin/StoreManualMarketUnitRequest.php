<?php

namespace App\Http\Requests\Admin;

use App\Services\Market\PlaceName;
use App\Services\Market\ProjectMarketComparison;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualMarketUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('project_name') === '') {
            $this->merge(['project_name' => null]);
        }
    }

    /**
     * The same things a comparison reads: a name, a price, a size, a kind,
     * and where the unit sits.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $kinds = app(ProjectMarketComparison::class)->kinds(PlaceName::key((string) $this->input('city')));

        return [
            'name' => 'required|string|max:255',
            'project_name' => 'nullable|string|max:255',
            'city' => 'required|string|max:80',
            'district' => 'required|string|max:120',
            'kind' => ['required', 'string', 'max:30', Rule::in($kinds)],
            'area' => 'required|numeric|min:1|max:1000000',
            'price' => 'required|integer|min:1',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم الوحدة مطلوب.',
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
        ];
    }
}
