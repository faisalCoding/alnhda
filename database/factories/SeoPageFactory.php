<?php

namespace Database\Factories;

use App\Services\SeoPageDefaults;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\SeoPage>
 */
class SeoPageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'route_name' => fake()->randomElement(array_keys(SeoPageDefaults::PAGES)),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(12),
            'image_path' => null,
            'og_type' => 'website',
            'noindex' => false,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => ['noindex' => true]);
    }
}
