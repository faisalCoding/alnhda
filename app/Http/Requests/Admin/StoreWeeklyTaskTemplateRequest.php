<?php

namespace App\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreWeeklyTaskTemplateRequest extends FormRequest
{
    /**
     * Pasting a whole week in at once is the point, so this is deliberately
     * generous — the per-task limit is checked line by line instead.
     */
    private const MAX_TASKS = 50;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:8000', $this->linesAreUsable()],
            'employee_id' => 'nullable|integer|exists:employees,id',
            'weekly_task_category_id' => 'nullable|integer|exists:weekly_task_categories,id',
        ];
    }

    /**
     * One task per line, so a list can be pasted in whole rather than typed a
     * task at a time. Leading list markers are dropped, since text copied out
     * of a document almost always carries them.
     *
     * @return list<string>
     */
    public function titles(): array
    {
        return collect(preg_split('/\R/u', (string) $this->input('title')) ?: [])
            ->map(fn (string $line): string => trim((string) preg_replace('/^\s*(?:[-–—•*]|\d+[.)])\s+/u', '', $line)))
            ->filter(fn (string $line): bool => $line !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function linesAreUsable(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $titles = $this->titles();

            if ($titles === []) {
                $fail('نص المهمة مطلوب.');

                return;
            }

            if (count($titles) > self::MAX_TASKS) {
                $fail('أضف '.self::MAX_TASKS.' مهمة كحد أقصى في المرة الواحدة.');

                return;
            }

            foreach ($titles as $title) {
                if (mb_strlen($title) > 255) {
                    $fail('كل مهمة يجب ألا تتجاوز ٢٥٥ حرفاً.');

                    return;
                }
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'نص المهمة مطلوب.',
        ];
    }
}
