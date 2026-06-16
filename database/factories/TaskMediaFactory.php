<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskMediaFactory extends Factory
{
    protected $model = TaskMedia::class;

    public function definition(): array
    {
        $isVideo = $this->faker->boolean(35);

        return [
            'task_id'   => Task::factory(),
            'type'      => $isVideo ? 'video' : 'image',
            // Use your storage paths in real app; placeholders are fine for dev.
            'url'       => $isVideo
                ? 'https://videos.pexels.com/video-files/855190/855190-hd_1280_720_24fps.mp4'
                : 'https://picsum.photos/seed/'.uniqid().'/640/360',
            'thumbnail' => $isVideo ? 'https://picsum.photos/seed/'.uniqid().'/320/180' : null,
            'caption'   => $this->faker->sentence(4),
            'sort_order'=> $this->faker->numberBetween(1, 3),
        ];
    }
}
