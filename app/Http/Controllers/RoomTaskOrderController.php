<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Room;
use Illuminate\Http\Request;

/**
 * Controller to handle ordering of tasks within a room.
 * This allows the user to specify the order of tasks in a room.
 */
class RoomTaskOrderController extends Controller
{
    public function update(Request $request, Property $property, Room $room)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can reorder tasks.');
        
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:tasks,id'],
        ]);

        foreach ($data['order'] as $index => $taskId) {
            $room->tasks()->updateExistingPivot($taskId, ['sort_order' => $index + 1]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * PATCH /rooms/{room}/tasks
     * Handle task ordering for standalone rooms (not under properties)
     */
    public function updateForRoom(Request $request, Room $room)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can reorder tasks.');
        
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:tasks,id'],
        ]);

        foreach ($data['order'] as $index => $taskId) {
            $room->tasks()->updateExistingPivot($taskId, ['sort_order' => $index + 1]);
        }

        return response()->json(['status' => 'ok']);
    }
}
