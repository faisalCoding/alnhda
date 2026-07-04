<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Properties>
 */
class PropertiesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['شقة فاخرة', 'فيلا مودرن', 'دور أرضي']),
            'project_id' => Project::factory(),
            'price' => fake()->numberBetween(300000, 2000000),
            'offer' => null,
            'status' => 'جديد',
            'rooms' => fake()->numberBetween(2, 6),
            'bathrooms' => fake()->numberBetween(1, 4),
            'living_rooms' => fake()->numberBetween(1, 2),
            'mainds_room' => fake()->numberBetween(0, 1),
            'area' => fake()->numberBetween(90, 400),
            'doors' => fake()->numberBetween(1, 3),
            'type' => fake()->randomElement(['شقة', 'فيلا', 'دور']),
            'parkings' => fake()->numberBetween(1, 2),
            'driver_room' => fake()->numberBetween(0, 1),
            'facade' => 'شرقية جنوبية',
            'furniture' => fake()->boolean(),
        ];
    }
}
