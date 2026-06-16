<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Room;
use App\Models\Property;

return new class extends Migration
{
    /**
     * One-time migration: Clone shared rooms so each property gets its own unique Room record.
     * This also clones the tasks for each new Room to ensure full isolation.
     */
    public function up(): void
    {
        // 1. Find all rooms attached to MORE than one property
        $sharedRoomIds = DB::table('property_room')
            ->select('room_id')
            ->groupBy('room_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('room_id');

        foreach ($sharedRoomIds as $roomId) {
            $originalRoom = Room::find($roomId);
            if (!$originalRoom) continue;

            // Get all property_room pivots for this shared room
            $pivots = DB::table('property_room')
                ->where('room_id', $roomId)
                ->orderBy('id')
                ->get();

            // Skip the first property (it keeps the original room)
            // For every other property, we create a CLONE of the room and its tasks
            foreach ($pivots->skip(1) as $pivot) {
                $property = Property::find($pivot->property_id);
                if (!$property) continue;

                // Use the existing Room::cloneForProperty method which handles:
                // - Creating new room record
                // - Setting is_default = false
                // - Cloning all tasks attached to the original room
                // - Attaching to the new property
                $originalRoom->cloneForProperty($property, $pivot->sort_order);

                // Delete the old shared pivot record
                DB::table('property_room')->where('id', $pivot->id)->delete();
            }
        }
    }

    public function down(): void
    {
        // Irreversible data transformation
    }
};
