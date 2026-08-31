<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FaqEntry>
 */
class FaqEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question' => 'هل تقبلون التمويل العقاري؟',
            'answer' => 'نعم، تتعامل الشركة مع عدد من البنوك وجهات التمويل المعتمدة في المملكة.',
            'sort_order' => 0,
        ];
    }
}
