<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\CleaningSession> */
class CleaningSessionFactory extends Factory
{
    protected $model = \App\Models\CleaningSession::class;

    public function definition(): array
    {
        $status = fake()->randomElement(['pending', 'in_progress', 'completed']);
        $start  = $status === 'pending' ? null : fake()->dateTimeBetween('-7 days', '+7 days');
        $end    = $status === 'completed' ? (clone $start)?->modify('+2 hours') : null;

        return [
            'property_id'       => 1,  // override
            'owner_id'          => 1,  // override
            'housekeeper_id'    => 1,  // override
            'scheduled_date'    => fake()->dateTimeBetween('-10 days', '+20 days')->format('Y-m-d'),
            'status'            => $status,
            'started_at'        => $start,
            'ended_at'          => $end,
            'gps_confirmed_at'  => $start,
            'start_latitude'    => null, // set in seeder if property has lat/lng
            'start_longitude'   => null,
        ];
    }
}
