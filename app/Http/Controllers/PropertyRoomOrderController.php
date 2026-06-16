<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;

class PropertyRoomOrderController extends Controller
{
    public function update(Request $request, Property $property)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can reorder rooms.');
        
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:rooms,id'],
        ]);

        foreach ($data['order'] as $i => $roomId) {
            $property->rooms()->updateExistingPivot($roomId, ['sort_order' => $i + 1]);
        }

        return response()->json(['status' => 'ok']);
    }
}
