<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;

class RoomSuggestionController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $rooms = Room::query()
            ->where('is_default', true)
            ->where('name', 'like', "%$q%")
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'is_default']);

        return response()->json($rooms);
    }
}
