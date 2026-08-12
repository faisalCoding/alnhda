<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\WhatsappMessage;
use App\Models\WhatsappMessageRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappMessageRecipient>
 */
class WhatsappMessageRecipientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'whatsapp_message_id' => WhatsappMessage::factory(),
            'lead_id' => Lead::factory(),
            'name' => fake()->name(),
            'phone' => '05'.fake()->numerify('########'),
            'status' => WhatsappMessageRecipient::STATUS_QUEUED,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => WhatsappMessageRecipient::STATUS_SENT,
            'provider_message_id' => 'true_966500000000@c.us_'.fake()->uuid(),
            'sent_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => WhatsappMessageRecipient::STATUS_FAILED,
            'error' => 'تعذر الإرسال.',
        ]);
    }
}
