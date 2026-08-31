<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreFaqEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The answer has a floor as well as a ceiling: a two-word answer is what
     * neither a reader nor a search engine can use, and this text is published
     * as a FAQPage where a stub answer costs more than no answer at all.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'question' => 'required|string|max:160',
            'answer' => 'required|string|min:40|max:1000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question.required' => 'السؤال مطلوب.',
            'question.max' => 'السؤال طويل جدًا.',
            'answer.required' => 'الجواب مطلوب.',
            'answer.min' => 'الجواب قصير جدًا — اكتب جوابًا مكتفيًا بذاته يفهمه القارئ دون ما حوله.',
            'answer.max' => 'الجواب طويل جدًا.',
        ];
    }
}
