<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\ChecklistItem> */
class ChecklistItemFactory extends Factory
{
    protected $model = \App\Models\ChecklistItem::class;

    public function definition(): array
    {
        $checked = fake()->boolean(70);

        return [
            'session_id' => 1, // override
            'room_id'    => 1, // override
            'task_id'    => 1, // override
            'user_id'    => null, // set in seeder
            'checked'    => $checked,
            'note'       => $checked ? null : fake()->optional(0.3)->sentence(8),
            'checked_at' => $checked ? now() : null,
        ];
    }
}
