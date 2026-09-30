<?php

namespace Database\Factories;

use App\Enums\CategoryIcon;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'icon' => fake()->randomElement(CategoryIcon::cases()),
        ];
    }
}
