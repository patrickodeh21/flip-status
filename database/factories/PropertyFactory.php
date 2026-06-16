<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Property> */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        $baseLat = 40.741;
        $baseLng = -73.989;
        return [
            'owner_id'     => 1, // will be overridden in seeder
            'name'         => fake()->unique()->streetName() . ' Suites',
            'beds'         => fake()->numberBetween(1, 6),
            'baths'        => fake()->numberBetween(1, 4),
            'address'      => fake()->streetAddress(),  
            'latitude'     => $baseLat + fake()->randomFloat(6, -0.08, 0.08),
            'longitude'    => $baseLng + fake()->randomFloat(6, -0.08, 0.08),
            'geo_radius_m' => fake()->numberBetween(100, 250),
        ];
    }
}
