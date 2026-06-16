<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PropertyRoomController extends Controller
{
    public function store(Request $request, Property $property)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can add rooms to properties.');
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_default' => ['nullable', Rule::in(['0', '1'])],
            'min_photos' => ['required', 'integer', 'min:0', 'max:50'],
        ]);

        // 1) Find if a DEFAULT Room exists with this name to use as a template for metadata only
        $name = trim($validated['name']);
        $template = Room::where('is_default', true)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        // Always create a fresh isolated room — never clone tasks from another property
        $room = Room::create([
            'name'       => $name,
            'min_photos' => (int) ($validated['min_photos'] ?? ($template ? $template->min_photos : 2)),
            'is_default' => false, // Strictly isolate: Property-specific rooms can never be global defaults
        ]);

        $nextOrder = (int) $property->rooms()->max('property_room.sort_order') + 1;
        $property->rooms()->attach($room->id, [
            'sort_order' => $nextOrder,
        ]);

        $message = $template
            ? "Created room '{$room->name}' (default tasks applied)"
            : "Created & attached room: {$room->name}";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => $message,
                'room' => $room,
            ]);
        }

        return redirect()
            ->route('properties.rooms.index', $property)
            ->with('status', $message);
    }
}
