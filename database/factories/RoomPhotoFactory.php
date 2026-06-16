<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\RoomPhoto> */
class RoomPhotoFactory extends Factory
{
    protected $model = \App\Models\RoomPhoto::class;

    public function definition(): array
    {
        return [
            'session_id'            => 1, // override
            'room_id'               => 1, // override
            'path'                  => 'seed/photos/' . fake()->uuid() . '.jpg',
            'captured_at'           => now(),
            'has_timestamp_overlay' => true,
        ];
    }
}
