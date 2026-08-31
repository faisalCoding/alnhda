<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CollectionPage>
 */
class CollectionPageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => 'صفحة-'.Str::random(6),
            'title' => 'شقق جاهزة للتسليم',
            'description' => 'مجموعة مختارة من الوحدات والمشاريع الجاهزة للتسليم الفوري في جدة.',
        ];
    }
}
