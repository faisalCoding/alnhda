<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AcknowledgeWhatsappRequest extends FormRequest
{
    /**
     * The shared-key middleware on the route is the authorization for this
     * endpoint; there is no admin session behind a gateway callback.
     */
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
            'acks' => 'required|array|min:1|max:500',
            'acks.*.id' => 'required|string|max:255',
            // whatsapp-web.js levels: -1 error … 4 played.
            'acks.*.ack' => 'required|integer|between:-5,10',
        ];
    }
}
