<?php

namespace App\Http\Requests\Admin;

use App\Models\CollectionPage;
use App\Services\LinkTargets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCollectionPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Items arrive as an ordered list of `type:id` strings — the order of the
     * array is the order of the page, so nothing carries a position of its own.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[\pL\pN]+(?:-[\pL\pN]+)*$/u',
                Rule::unique('collection_pages', 'slug')->ignore($this->editing()?->id),
            ],
            'title' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'items' => 'array',
            'items.*' => 'string|distinct',
        ];
    }

    /**
     * The page being edited, if this is an update.
     */
    protected function editing(): ?CollectionPage
    {
        $record = $this->route('collectionPage');

        return $record instanceof CollectionPage ? $record : null;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach ((array) $this->input('items', []) as $index => $entry) {
                    [$type, $id] = array_pad(explode(':', (string) $entry, 2), 2, null);
                    $model = in_array($type, LinkTargets::ITEM_TYPES, true)
                        ? LinkTargets::classFor($type)
                        : null;

                    if ($model === null) {
                        $validator->errors()->add('items.'.$index, 'نوع المحتوى غير معروف.');

                        continue;
                    }

                    if (! $model::query()->whereKey($id)->exists()) {
                        $validator->errors()->add('items.'.$index, 'أحد العناصر المختارة لم يعد موجودًا.');
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.required' => 'رابط الصفحة مطلوب.',
            'slug.unique' => 'هذا الرابط مستخدم في صفحة أخرى.',
            'slug.regex' => 'الرابط يقبل الحروف والأرقام والشرطة (-) فقط، بلا مسافات.',
            'title.required' => 'عنوان الصفحة مطلوب.',
            'items.*.distinct' => 'تكرر عنصر في الصفحة.',
        ];
    }
}
