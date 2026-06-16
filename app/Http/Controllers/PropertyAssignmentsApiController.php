<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;

class PropertyAssignmentsApiController extends Controller
{
    private function ensureCanView(Request $request, Property $property): void
    {
        $user = $request->user();

        abort_unless($user && $user->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can access properties.');

        // Admins can access all properties (even if they also have the "owner" role).
        // Owners (non-admin) can only access their own properties.
        if (! $user->hasRole('admin') && $user->hasAnyRole(['owner', 'company']) && $property->owner_id !== $user->id) {
            abort(403, 'You cannot access properties you do not own.');
        }
    }

    public function rooms(Request $request, Property $property)
    {
        $this->ensureCanView($request, $property);

        $rooms = $property->rooms()
            ->select('rooms.id', 'rooms.name')
            ->withCount('tasks')
            ->orderBy('property_room.sort_order')
            ->get()
            ->map(fn ($room) => [
                'id' => $room->id,
                'name' => $room->name,
                'tasks_count' => (int) ($room->tasks_count ?? 0),
                'sort_order' => (int) ($room->pivot->sort_order ?? 0),
            ])
            ->values();

        return response()->json([
            'rooms' => $rooms,
        ]);
    }

    public function propertyTasks(Request $request, Property $property)
    {
        $this->ensureCanView($request, $property);

        $tasks = $property->propertyTasks()
            ->select('tasks.id', 'tasks.name', 'tasks.phase')
            ->orderBy('property_tasks.sort_order')
            ->get()
            ->map(fn ($task) => [
                'id' => $task->id,
                'name' => $task->name,
                'phase' => $task->phase,
                'sort_order' => (int) ($task->pivot->sort_order ?? 0),
                'visible_to_owner' => (bool) ($task->pivot->visible_to_owner ?? true),
                'visible_to_housekeeper' => (bool) ($task->pivot->visible_to_housekeeper ?? true),
            ])
            ->values();

        return response()->json([
            'tasks' => $tasks,
        ]);
    }

    public function assignedRooms(Request $request, Property $property)
    {
        // Alias for rooms() method - used by property edit page
        return $this->rooms($request, $property);
    }

    public function assignedPropertyTasks(Request $request, Property $property)
    {
        // Alias for propertyTasks() method - used by property edit page
        return $this->propertyTasks($request, $property);
    }
}

