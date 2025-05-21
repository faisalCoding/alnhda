<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //
            'name' => fake()->sentence(),
            'image_url' => 'https://picsum.photos/seed/picsum/700/500',
            'location' => fake()->randomElement(['جدة حي المنار', 'جدة حي السامر','جدة مخطط التيسير']),
            'project_type' => fake()->randomElement(['فيلا', 'شقة','دور']),
            'description' => fake()->sentence(),
            'status' => 'جديد مكتمل',
        ];
    }
}
