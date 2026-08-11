<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '05'.fake()->numerify('########'),
            'property' => fake()->randomElement(['فيلا', 'شقة', 'دور', 'أرض']),
            'lead_date' => fake()->dateTimeBetween('-6 months')->format('Y-m-d'),
            'classification' => fake()->randomElement(['مهتم', 'تم التواصل', 'جديد', 'مؤجل']),
        ];
    }
}
