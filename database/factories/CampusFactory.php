<?php

namespace Database\Factories;

use App\Models\Campus;
use App\Models\Church;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Campus>
 */
class CampusFactory extends Factory
{
    protected $model = Campus::class;

    public function definition(): array
    {
        $name = fake()->city().' Campus';

        return [
            'church_id' => Church::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'status' => 'active',
        ];
    }
}
