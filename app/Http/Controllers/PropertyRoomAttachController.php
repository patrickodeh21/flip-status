<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyRoomAttachController extends Controller
{
    public function store(Request $request, Property $property)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can attach rooms to properties.');

        $data = $request->validate([
            'room_ids'   => ['array'],
            'room_ids.*' => ['integer', 'exists:rooms,id'],
            'room_names' => ['array'],
            'room_names.*' => ['string', 'max:255'],
        ]);

        $roomIds = $data['room_ids'] ?? [];
        $roomNames = array_filter($data['room_names'] ?? [], fn($v) => trim($v) !== '');

        if (empty($roomIds) && empty($roomNames)) {
            return back()->with('warn', 'No rooms selected.');
        }

        DB::transaction(function () use ($property, $roomIds, $roomNames) {
            $nextSort = (int) $property->rooms()->max('property_room.sort_order');

            // 1. Process explicit template IDs requested
            if (!empty($roomIds)) {
                // Fetch the templates without grouping to keep them available for iteration mapping
                $templates = Room::whereIn('id', $roomIds)->get()->keyBy('id');
                
                // Iterate directly over input `$roomIds` to preserve exact user selection counts
                foreach ($roomIds as $id) {
                    if ($template = $templates->get($id)) {
                        $template->cloneForProperty($property, ++$nextSort);
                    }
                }
            }

            // 2. Process newly typed free-text rooms directly
            if (!empty($roomNames)) {
                foreach ($roomNames as $name) {
                    $room = Room::create([
                        'name' => trim($name),
                        'is_default' => false, // Ensure strict isolation
                        'min_photos' => 2,     // Hardcoded fallback since there is no template available
                    ]);

                    // Attach directly without cloning since it is a brand new record
                    $property->rooms()->attach($room->id, ['sort_order' => ++$nextSort]);
                }
            }
        });

        return back()->with('ok', 'Rooms attached successfully.');
    }
}
