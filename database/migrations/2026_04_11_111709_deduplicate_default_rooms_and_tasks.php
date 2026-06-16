<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Room;
use App\Models\Task;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Deduplicate Rooms
        $rooms = Room::where('is_default', true)->get();
        $groupedRooms = $rooms->groupBy(function ($room) {
            return mb_strtolower(trim($room->name));
        });

        foreach ($groupedRooms as $name => $duplicates) {
            if ($duplicates->count() > 1) {
                // Sort by ID to ensure the oldest one is kept
                $duplicates = $duplicates->sortBy('id')->values();
                $canonical = $duplicates->shift(); // Keep the first (oldest) one

                foreach ($duplicates as $duplicate) {
                    $hasProperties = DB::table('property_room')
                        ->where('room_id', $duplicate->id)
                        ->exists();

                    if ($hasProperties) {
                        // Inherited from cross-contamination bug, strip the global visibility
                        $duplicate->update(['is_default' => false]);
                    } else {
                        // Completely disconnected duplicate from Seeder overlapping
                        $duplicate->delete();
                    }
                }
            }
        }

        // 2. Deduplicate Tasks
        $tasks = Task::where('is_default', true)->get();
        // Tasks have type as well, so group by name + type
        $groupedTasks = $tasks->groupBy(function ($task) {
            return mb_strtolower(trim($task->name)) . '|' . $task->type;
        });

        foreach ($groupedTasks as $key => $duplicates) {
            if ($duplicates->count() > 1) {
                $duplicates = $duplicates->sortBy('id')->values();
                $canonical = $duplicates->shift();

                foreach ($duplicates as $duplicate) {
                    $hasRooms = DB::table('room_task')
                        ->where('task_id', $duplicate->id)
                        ->exists();

                    if ($hasRooms) {
                        $duplicate->update(['is_default' => false]);
                    } else {
                        $duplicate->delete();
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // One-way data cleanup migration
    }
};
