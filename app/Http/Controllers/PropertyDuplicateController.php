<?php

namespace App\Http\Controllers;

use App\Http\Requests\PropertyDuplicateRequest;
use App\Models\Property;
use Illuminate\Support\Facades\DB;

class PropertyDuplicateController extends Controller
{
    public function store(PropertyDuplicateRequest $request, Property $property)
    {
        $user = $request->user();
        abort_unless($user && $user->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can duplicate properties.');

        // Owners can only duplicate their own properties (defense-in-depth).
        // If a user has BOTH roles (admin + owner), treat them as admin here.
        if ($user->hasAnyRole(['owner', 'company']) && ! $user->hasRole('admin') && $property->owner_id !== $user->id) {
            abort(403, 'You cannot duplicate properties you do not own.');
        }

        $property->load(['rooms.tasks', 'propertyTasks']);

        $data = $request->validated();

        $newProperty = DB::transaction(function () use ($property, $data) {
            // Duplicate the property record (keep same owner)
            $newData = $property->only($property->getFillable());
            $newData['name'] = trim($data['name']);
            $newData['owner_id'] = $property->owner_id;

            /** @var Property $clone */
            $clone = Property::create($newData);

            // Copy selected rooms (preserve parent's relative order)
            // Deep-clone selected rooms (and their tasks) so each property has
            // completely independent DB rows — no shared references.
            $roomIds = array_values(array_unique($data['room_ids'] ?? []));
            if (!empty($roomIds)) {
                $rooms = $property->rooms
                    ->whereIn('id', $roomIds)
                    ->sortBy(fn($r) => (int) ($r->pivot->sort_order ?? 0))
                    ->values();

                $order = 1;
                foreach ($rooms as $room) {
                    // cloneForProperty replicates the Room row + each Task row
                    // with is_default = false, then attaches the clone to $clone.
                    $room->cloneForProperty($clone, $order++, false);
                }
            }

            // Deep-clone selected property-level tasks so each property owns
            // its own independent task records (prevents cross-property bleed).
            $taskIds = array_values(array_unique($data['task_ids'] ?? []));
            if (!empty($taskIds)) {
                $tasks = $property->propertyTasks
                    ->whereIn('id', $taskIds)
                    ->sortBy(fn($t) => (int) ($t->pivot->sort_order ?? 0))
                    ->values();

                $order = 1;
                foreach ($tasks as $task) {
                    $clonedTask = $task->replicate();
                    $clonedTask->is_default = false;
                    $clonedTask->save();

                    $clone->propertyTasks()->attach($clonedTask->id, [
                        'sort_order' => $order++,
                        'instructions' => $task->pivot->instructions ?? null,
                        'visible_to_owner' => (bool) ($task->pivot->visible_to_owner ?? true),
                        'visible_to_housekeeper' => (bool) ($task->pivot->visible_to_housekeeper ?? true),
                    ]);
                }
            }

            // Sync property assignments (Housekeepers/Companies assigned via pivot)
            $userIds = $property->users()->pluck('users.id')->toArray();
            if (!empty($userIds)) {
                $clone->users()->sync($userIds);
            }

            return $clone;
        });

        return redirect()
            ->route('properties.edit', $newProperty)
            ->with('ok', 'Property duplicated successfully.');
    }
}
