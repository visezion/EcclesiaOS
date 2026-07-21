<?php

namespace Database\Factories;

use App\Models\Campus;
use App\Models\Church;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'campus_id' => Campus::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'status' => 'active',
            'joined_at' => fake()->dateTimeBetween('-5 years', 'now'),
        ];
    }
}
