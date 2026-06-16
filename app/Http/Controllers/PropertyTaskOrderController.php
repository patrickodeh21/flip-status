<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;

class PropertyTaskOrderController extends Controller
{
    public function update(Request $request, Property $property)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners and companies can reorder tasks.');
        
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:tasks,id'],
        ]);

        foreach ($data['order'] as $i => $taskId) {
            $property->propertyTasks()->updateExistingPivot($taskId, ['sort_order' => $i + 1]);
        }

        return response()->json(['status' => 'ok']);
    }
}
