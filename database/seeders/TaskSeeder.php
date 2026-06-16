<?php

namespace Database\Seeders;

use App\Models\Task;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
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

        foreach ($tasksByType as $type => $taskNames) {
            foreach ($taskNames as $taskName) {
                \App\Models\Task::firstOrCreate(
                    [
                        'name' => $taskName,
                        'type' => $type,
                    ],
                    [
                        'is_default'   => true,
                        'instructions' => null,
                    ]
                );
            }
        }
    }
}
