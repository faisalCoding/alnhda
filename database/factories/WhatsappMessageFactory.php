<?php

namespace Database\Factories;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WhatsappMessage>
 */
class WhatsappMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_id' => Admin::factory(),
            'body' => 'مرحباً {الاسم}، لدينا عرض جديد.',
            'recipients_count' => 0,
            'skipped_count' => 0,
        ];
    }
}
