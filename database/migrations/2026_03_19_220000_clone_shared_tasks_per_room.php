<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time migration: Clone shared tasks so each room gets its own independent copy.
     * If task #5 "Dusting" is attached to 3 rooms, the first room keeps #5,
     * rooms 2 and 3 get brand-new clones (#100, #101) with the same name/type.
     */
    public function up(): void
    {
        // Find all tasks attached to MORE than one room
        $sharedTaskIds = DB::table('room_task')
            ->select('task_id')
            ->groupBy('task_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('task_id');

        foreach ($sharedTaskIds as $taskId) {
            $task = DB::table('tasks')->where('id', $taskId)->first();
            if (!$task) continue;

            // Get all room_task pivots for this shared task
            $pivots = DB::table('room_task')
                ->where('task_id', $taskId)
                ->orderBy('id')
                ->get();

            // Skip the first pivot (it keeps the original task)
            foreach ($pivots->skip(1) as $pivot) {
                // Clone the task record
                $newTaskId = DB::table('tasks')->insertGetId([
                    'name'        => $task->name,
                    'is_default'  => false,  // clones are never defaults
                    'type'        => $task->type,
                    'instructions' => $task->instructions,
                    'phase'       => $task->phase ?? null,
                    'is_sporadic' => $task->is_sporadic ?? false,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                // Update the pivot to point to the new clone
                DB::table('room_task')
                    ->where('id', $pivot->id)
                    ->update(['task_id' => $newTaskId]);
            }
        }

        // Also handle property_tasks pivot if it exists
        if (\Illuminate\Support\Facades\Schema::hasTable('property_tasks')) {
            $sharedPropertyTaskIds = DB::table('property_tasks')
                ->select('task_id')
                ->groupBy('task_id')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('task_id');

            foreach ($sharedPropertyTaskIds as $taskId) {
                $task = DB::table('tasks')->where('id', $taskId)->first();
                if (!$task) continue;

                $pivots = DB::table('property_tasks')
                    ->where('task_id', $taskId)
                    ->orderBy('id')
                    ->get();

                foreach ($pivots->skip(1) as $pivot) {
                    $newTaskId = DB::table('tasks')->insertGetId([
                        'name'        => $task->name,
                        'is_default'  => false,
                        'type'        => $task->type,
                        'instructions' => $task->instructions,
                        'phase'       => $task->phase ?? null,
                        'is_sporadic' => $task->is_sporadic ?? false,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);

                    DB::table('property_tasks')
                        ->where('id', $pivot->id)
                        ->update(['task_id' => $newTaskId]);
                }
            }
        }
    }

    /**
     * Reverse is not practical for this data migration.
     */
    public function down(): void
    {
        // Cannot reliably reverse cloned data
    }
};
