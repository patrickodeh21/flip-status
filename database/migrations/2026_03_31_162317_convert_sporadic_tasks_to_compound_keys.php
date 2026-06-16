<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fetch all sessions that have sporadic tasks defined
        $sessions = DB::table('cleaning_sessions')->whereNotNull('sporadic_tasks')->get();

        foreach ($sessions as $session) {
            $tasks = json_decode($session->sporadic_tasks, true);
            if (!is_array($tasks) || empty($tasks)) continue;
            
            // Check if it's already using compound keys (contains '_')
            if (is_string(reset($tasks)) && str_contains(reset($tasks), '_')) {
                continue;
            }

            $newSporadics = [];
            // For historical integer arrays like [3, 46], figure out where they belonged on that property
            foreach ($tasks as $taskId) {
                // Find all rooms this task is bound to on this property
                $roomIds = DB::table('property_room')
                    ->join('room_task', 'property_room.room_id', '=', 'room_task.room_id')
                    ->where('property_room.property_id', $session->property_id)
                    ->where('room_task.task_id', $taskId)
                    ->pluck('room_task.room_id')
                    ->unique();

                // If found in multiple rooms, the old system activated it in all of them!
                if ($roomIds->isNotEmpty()) {
                    foreach ($roomIds as $roomId) {
                        $newSporadics[] = $taskId . '_' . $roomId;
                    }
                } else {
                    // Check if it's a global property task
                    $isGlobal = DB::table('property_tasks')
                        ->where('property_id', $session->property_id)
                        ->where('task_id', $taskId)
                        ->exists();

                    if ($isGlobal) {
                        $newSporadics[] = $taskId . '_global';
                    } else {
                        // Fallback (e.g. task was deleted but ID remains in session history)
                        $newSporadics[] = $taskId . '_missing';
                    }
                }
            }

            // Save the unique new string array
            DB::table('cleaning_sessions')
                ->where('id', $session->id)
                ->update(['sporadic_tasks' => json_encode(array_values(array_unique($newSporadics)))]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $sessions = DB::table('cleaning_sessions')->whereNotNull('sporadic_tasks')->get();

        foreach ($sessions as $session) {
            $tasks = json_decode($session->sporadic_tasks, true);
            if (!is_array($tasks) || empty($tasks)) continue;

            $oldSporadics = [];
            foreach ($tasks as $compoundKey) {
                if (is_string($compoundKey)) {
                    $parts = explode('_', $compoundKey);
                    if (count($parts) > 0 && is_numeric($parts[0])) {
                        $oldSporadics[] = (int) $parts[0];
                    }
                }
            }

            DB::table('cleaning_sessions')
                ->where('id', $session->id)
                ->update(['sporadic_tasks' => json_encode(array_values(array_unique($oldSporadics)))]);
        }
    }
};
