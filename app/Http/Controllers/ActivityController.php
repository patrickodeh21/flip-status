<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q'            => ['nullable', 'string', 'max:255'],
            'event'        => ['nullable', 'string', 'max:100'],
            'causer_id'    => ['nullable', 'integer'],
            'subject_type' => ['nullable', 'string', 'max:255'], // e.g. "CleaningSession" or full class
            'subject_id'   => ['nullable', 'integer'],
            'from'         => ['nullable', 'date'],
            'to'           => ['nullable', 'date'],
        ]);

        $query = Activity::query()
            ->with(['causer:id,name', 'subject']) // subject is morphTo
            ->orderByDesc('created_at');

        if (!empty($data['q'])) {
            $q = $data['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('description', 'like', "%{$q}%")
                    ->orWhere('log_name', 'like', "%{$q}%")
                    ->orWhere('properties', 'like', "%{$q}%");
            });
        }

        if (!empty($data['event'])) {
            $query->where('event', $data['event']);
        }

        if (!empty($data['causer_id'])) {
            $query->where('causer_id', $data['causer_id']);
        }

        if (!empty($data['subject_id'])) {
            $query->where('subject_id', $data['subject_id']);
        }

        if (!empty($data['subject_type'])) {
            // Allow short model names or FQCN
            $st = $data['subject_type'];
            if (!str_contains($st, '\\')) {
                // map common short names
                $map = [
                    'Property'        => \App\Models\Property::class,
                    'Room'            => \App\Models\Room::class,
                    'Task'            => \App\Models\Task::class,
                    'CleaningSession' => \App\Models\CleaningSession::class,
                    'ChecklistItem'   => \App\Models\ChecklistItem::class,
                ];
                $st = $map[$st] ?? $st;
            }
            $query->where('subject_type', $st);
        }

        if (!empty($data['from'])) {
            $query->whereDate('created_at', '>=', $data['from']);
        }
        if (!empty($data['to'])) {
            $query->whereDate('created_at', '<=', $data['to']);
        }

        $activities = $query->paginate(50)->withQueryString();

        // Helpful dropdown data
        $distinctEvents = Activity::query()->select('event')->whereNotNull('event')->distinct()->orderBy('event')->pluck('event');
        $causers        = \App\Models\User::query()->select('id', 'name')->orderBy('name')->get();

        return view('activity.index', compact('activities', 'distinctEvents', 'causers'));
    }
}
