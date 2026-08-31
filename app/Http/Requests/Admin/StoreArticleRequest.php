<?php

namespace App\Http\Requests\Admin;

use App\Models\Article;
use App\Services\LinkTargets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreArticleRequest extends FormRequest
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
            'title' => 'required|string',
            'content' => 'nullable|string',
            'cta_label' => 'nullable|string|max:60',
            'cta_target_type' => 'nullable|required_with:cta_target_id|string|in:'.implode(',', array_keys(LinkTargets::TYPES)),
            'cta_target_id' => 'nullable|required_with:cta_target_type|integer',
        ];
    }

    /**
     * The destination is checked here rather than with an `exists` rule because
     * which table to look in is only known once the type is read.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $type = $this->input('cta_target_type');
                $id = $this->input('cta_target_id');
                $model = LinkTargets::classFor(is_string($type) ? $type : null);

                if ($model === null || blank($id) || $validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! $model::query()->whereKey($id)->exists()) {
                    $validator->errors()->add('cta_target_id', 'الوجهة المختارة غير موجودة.');

                    return;
                }

                if ($model === Article::class && (int) $id === (int) $this->route('article')?->id) {
                    $validator->errors()->add('cta_target_id', 'لا يمكن أن يشير زر المقال إلى المقال نفسه.');
                }
            },
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
            'title.required' => 'عنوان المقال مطلوب.',
            'cta_label.max' => 'نص الزر طويل جدًا.',
            'cta_target_type.in' => 'نوع الوجهة غير معروف.',
            'cta_target_type.required_with' => 'اختر نوع الوجهة التي يفتحها الزر.',
            'cta_target_id.required_with' => 'اختر الوجهة التي يفتحها الزر.',
        ];
    }
}
