<?php

namespace App\Services;

use App\Models\ChecklistItem;
use App\Models\CleaningSession;
use Illuminate\Support\Collection;

class SessionStageService
{
    /**
     * Determine the current stage of a cleaning session based on completion status.
     * Flow: pre_cleaning → rooms → during_cleaning → post_cleaning → inventory → photos
     */
    public function determineStage(CleaningSession $session, Collection $rooms, Collection $propertyTasks): string
    {
        if ($session->status === 'completed') {
            return 'summary';
        }

        // Separate property-level tasks by phase
        $preCleaningTasks = $propertyTasks->where('phase', 'pre_cleaning');
        $duringCleaningTasks = $propertyTasks->where('phase', 'during_cleaning');
        $postCleaningTasks = $propertyTasks->where('phase', 'post_cleaning');

        // Count property-level tasks
        $preCleaningCount = $preCleaningTasks->count();
        $duringCleaningCount = $duringCleaningTasks->count();
        $postCleaningCount = $postCleaningTasks->count();

        // Count checked property-level tasks
        $checkedPreCleaningCount = $this->countCheckedPropertyTasks($session, $preCleaningTasks);
        $checkedDuringCleaningCount = $this->countCheckedPropertyTasks($session, $duringCleaningTasks);
        $checkedPostCleaningCount = $this->countCheckedPropertyTasks($session, $postCleaningTasks);

        // Count room tasks
        $allRoomTasksCount = $rooms->flatMap->tasks->where('type', 'room')->count();
        $checkedRoomTasksCount = ChecklistItem::where('session_id', $session->id)
            ->whereHas('task', fn($q) => $q->where('type', 'room'))
            ->whereNotNull('room_id')
            ->where('checked', true)
            ->count();

        // Count inventory tasks
        $allInventoryTasksCount = $rooms->flatMap->tasks->where('type', 'inventory')->count();
        $checkedInventoryTasksCount = ChecklistItem::where('session_id', $session->id)
            ->whereHas('task', fn($q) => $q->where('type', 'inventory'))
            ->whereNotNull('room_id')
            ->where('checked', true)
            ->count();

        // Determine stage based on completion status
        if ($preCleaningCount > 0 && $checkedPreCleaningCount < $preCleaningCount) {
            return 'pre_cleaning';
        }
        if ($allRoomTasksCount > 0 && $checkedRoomTasksCount < $allRoomTasksCount) {
            return 'rooms';
        }
        if ($duringCleaningCount > 0 && $checkedDuringCleaningCount < $duringCleaningCount) {
            return 'during_cleaning';
        }
        if ($postCleaningCount > 0 && $checkedPostCleaningCount < $postCleaningCount) {
            return 'post_cleaning';
        }
        if ($allInventoryTasksCount > 0 && $checkedInventoryTasksCount < $allInventoryTasksCount) {
            return 'inventory';
        }

        return 'photos';
    }

    /**
     * Count checked property-level tasks for a given collection of tasks.
     */
    private function countCheckedPropertyTasks(CleaningSession $session, Collection $tasks): int
    {
        if ($tasks->isEmpty()) {
            return 0;
        }

        return ChecklistItem::where('session_id', $session->id)
            ->whereNull('room_id')
            ->whereIn('task_id', $tasks->pluck('id'))
            ->where('checked', true)
            ->count();
    }

    /**
     * Get task counts by room for a session.
     */
    public function getRoomTaskCounts(CleaningSession $session, Collection $rooms): array
    {
        $counts = [];

        foreach ($rooms as $room) {
            $roomTasks = $room->tasks->where('type', 'room');
            $inventoryTasks = $room->tasks->where('type', 'inventory');

            $counts[$room->id] = [
                'room' => [
                    'total' => $roomTasks->count(),
                    'checked' => ChecklistItem::where('session_id', $session->id)
                        ->where('room_id', $room->id)
                        ->whereIn('task_id', $roomTasks->pluck('id'))
                        ->where('checked', true)
                        ->count(),
                ],
                'inventory' => [
                    'total' => $inventoryTasks->count(),
                    'checked' => ChecklistItem::where('session_id', $session->id)
                        ->where('room_id', $room->id)
                        ->whereIn('task_id', $inventoryTasks->pluck('id'))
                        ->where('checked', true)
                        ->count(),
                ],
            ];
        }

        return $counts;
    }

    /**
     * Find the first incomplete room index for room tasks.
     */
    public function findFirstIncompleteRoomIndex(CleaningSession $session, Collection $rooms): ?int
    {
        foreach ($rooms as $index => $room) {
            $roomTasks = $room->tasks->where('type', 'room');
            $checkedCount = ChecklistItem::where('session_id', $session->id)
                ->where('room_id', $room->id)
                ->whereIn('task_id', $roomTasks->pluck('id'))
                ->where('checked', true)
                ->count();
            $totalCount = $roomTasks->count();

            if ($checkedCount < $totalCount) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Find the first incomplete room index for inventory tasks.
     */
    public function findFirstIncompleteInventoryIndex(CleaningSession $session, Collection $rooms): ?int
    {
        foreach ($rooms as $index => $room) {
            $inventoryTasks = $room->tasks->where('type', 'inventory');
            $checkedCount = ChecklistItem::where('session_id', $session->id)
                ->where('room_id', $room->id)
                ->whereIn('task_id', $inventoryTasks->pluck('id'))
                ->where('checked', true)
                ->count();
            $totalCount = $inventoryTasks->count();

            if ($checkedCount < $totalCount) {
                return $index;
            }
        }

        return null;
    }
}
