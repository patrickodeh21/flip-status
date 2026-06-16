<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Task> */
class TaskFactory extends Factory
{
    protected $model = \App\Models\Task::class;

    public function definition(): array
    {
        $tasksByType = [
            'room' => [
                'Sweep floor',
                'Mop floor',
                'Dust surfaces',
                'Empty trash',
                'Make bed',
                'Wipe counters',
                'Clean mirror',
            ],
            'inventory' => [
                'Restock soap',
                'Restock toilet paper',
                'Restock coffee/tea',
                'Replace towels',
            ],
        ];

        $type = $this->faker->randomElement(array_keys($tasksByType));
        $name = $this->faker->randomElement($tasksByType[$type]);

        return [
            'name'         => $name,
            'type'         => $type,
            'is_default'   => $this->faker->boolean(30),
            'instructions' => $this->faker->boolean(40) ? $this->faker->sentence(10) : null,
        ];
    }

    // Optional: convenient state for default tasks
    public function defaultTask(): static
    {
        return $this->state(fn() => [
            'is_default' => true,
        ]);
    }
}
