<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;

class TaskSuggestionController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->query('q', ''));
        if ($q === '') return response()->json([]);

        $tasks = Task::query()
            ->where('is_default', true)
            ->where('name', 'like', "%$q%")
            ->limit(100)
            ->get(['id', 'name', 'type', 'is_default'])
            ->unique(function ($item) {
                return strtolower(trim($item->name));
            })
            ->take(10)
            ->values();

        return response()->json($tasks);
    }
}
