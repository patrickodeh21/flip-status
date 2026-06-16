<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Room> */
class RoomFactory extends Factory
{
    protected $model = \App\Models\Room::class;

    public function definition(): array
    {
        return [
            'name'        => fake()->randomElement(['Bedroom', 'Kitchen', 'Bathroom', 'Living Room', 'Dining']),
            'is_default'  => false,
        ];
    }
}
