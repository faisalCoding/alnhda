<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3',
            'project_id' => 'required|integer|exists:projects,id',
            'price' => 'required|numeric',
            'offer' => 'nullable|numeric',
            'status' => 'required|string',
            'rooms' => 'required|integer',
            'bathrooms' => 'required|integer',
            'living_rooms' => 'required|integer',
            'mainds_room' => 'required|integer',
            'area' => 'required|numeric',
            'doors' => 'required|integer',
            'type' => 'required|string',
            'parkings' => 'required|integer',
            'driver_room' => 'required|integer',
            'facade' => 'required|string',
            'furniture' => 'required|boolean',
            'unit_youtube' => 'nullable|string',
            'stages_building_youtube' => 'nullable|string',
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
            'name.required' => 'اسم الوحدة مطلوب.',
            'name.min' => 'اسم الوحدة يجب أن يكون 3 أحرف على الأقل.',
            'project_id.required' => 'يجب اختيار المشروع التابع للوحدة.',
            'project_id.exists' => 'المشروع المحدد غير موجود.',
            'price.required' => 'سعر الوحدة مطلوب.',
            'price.numeric' => 'سعر الوحدة يجب أن يكون رقمًا.',
            'offer.numeric' => 'قيمة العرض يجب أن تكون رقمًا.',
        ];
    }
}
