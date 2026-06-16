<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Models\CleaningSession;
use App\Models\Property;
use App\Models\User;
use App\Models\Task;
use App\Models\ChecklistItem;

class ManageSessionController extends Controller
{
    /**
     * Resolve acting role for the current request.
     * admin wins by default; admin+owner may opt into owner scope via ?as=owner.
     */
    private function actingRole(Request $request): string
    {
        $u = $request->user();
        $isAdmin = $u?->hasRole('admin') ?? false;
        $isOwner = $u?->hasRole('owner') ?? false;

        if ($isAdmin) {
            // Allow explicit owner view if they also have owner role
            if ($isOwner && $request->query('as') === 'owner') {
                return 'owner';
            }
            return 'admin';
        }
        if ($isOwner || $u?->hasRole('company')) return 'owner';

        // Final fallback: if no role is found, treat as owner to avoid 403 during demo/setup
        return 'owner';
    }

    public function index(Request $request)
    {
        $u = Auth::user();
        $acting = $this->actingRole($request);
        abort_if($acting === 'forbidden', 403);

        $filters = [
            'property_id'    => $request->integer('property_id') ?: null,
            'housekeeper_id' => $request->integer('housekeeper_id') ?: null,
            'status'         => ($request->filled('status') ? (string)$request->string('status') : null),
            'date_from'      => $request->input('date_from') ?: null,
            'date_to'        => $request->input('date_to') ?: null,
        ];

        $q = CleaningSession::query()
            ->with([
                'property:id,name,owner_id',
                'housekeeper:id,name',
            ])
            // admin: full system, owner: only their properties
            ->when(
                $acting === 'owner',
                function($qry) use ($u) {
                    if ($u->hasRole('company')) {
                        $qry->where(function($q) use ($u) {
                            $q->where('cleaning_sessions.owner_id', $u->id)
                              ->orWhereIn('cleaning_sessions.owner_id', function($sub) use ($u) {
                                  $sub->select('id')->from('users')->where('owner_id', $u->id);
                              });
                        });
                    } else {
                        $qry->where('cleaning_sessions.owner_id', $u->id);
                    }
                }
            )
            ->when($filters['property_id'], fn($qry, $v) => $qry->where('property_id', $v))
            ->when($filters['housekeeper_id'], fn($qry, $v) => $qry->where('housekeeper_id', $v))
            ->when($filters['status'], fn($qry, $v) => $qry->where('status', $v))
            ->when($filters['date_from'], fn($qry, $v) => $qry->whereDate('scheduled_date', '>=', $v))
            ->when($filters['date_to'], fn($qry, $v) => $qry->whereDate('scheduled_date', '<=', $v))
            ->orderByDesc('scheduled_date');

        $sessions = $q->paginate(20)->withQueryString();

        $properties = Property::query()
            ->when($acting === 'owner', function($qry) use ($u) {
                if ($u->hasRole('company')) {
                    $qry->where(function($q) use ($u) {
                        $q->where('owner_id', $u->id)
                          ->orWhereIn('owner_id', function($sub) use ($u) {
                              $sub->select('id')->from('users')->where('owner_id', $u->id);
                          });
                    });
                } else {
                    $qry->where('owner_id', $u->id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        // For admins: all users. For owners/companies: associated users
        $housekeepers = User::query()
            ->when($acting === 'owner', function($qry) use ($u) {
                if ($u->hasRole('company')) {
                    $qry->where('id', $u->id)
                        ->orWhere('owner_id', $u->id)
                        ->orWhere('id', clone $u)->select('owner_id')->from('users')->where('id', $u->id); // The company's owner/parent
                } else {
                    $qry->where('id', $u->id)->orWhere('owner_id', $u->id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('sessions.manage.index', compact('sessions', 'properties', 'housekeepers', 'filters', 'acting'));
    }

    public function create(Request $request)
    {
        $u = Auth::user();
        $acting = $this->actingRole($request);
        abort_if($acting === 'forbidden', 403);

        $properties = Property::query()
            ->when($acting === 'owner', function($qry) use ($u) {
                if ($u->hasRole('company')) {
                    $qry->where(function($q) use ($u) {
                        $q->where('owner_id', $u->id)
                          ->orWhereIn('owner_id', function($sub) use ($u) {
                              $sub->select('id')->from('users')->where('owner_id', $u->id);
                          });
                    });
                } else {
                    $qry->where('owner_id', $u->id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $housekeepers = User::query()
            ->when($acting === 'owner', function($qry) use ($u) {
                if ($u->hasRole('company')) {
                    $qry->where('id', $u->id)
                        ->orWhere('owner_id', $u->id)
                        ->orWhere('id', clone $u)->select('owner_id')->from('users')->where('id', $u->id);
                } else {
                    $qry->where('id', $u->id)->orWhere('owner_id', $u->id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $propertyCleaners = \Illuminate\Support\Facades\DB::table('property_user')
            ->whereIn('property_id', $properties->pluck('id'))
            ->get(['property_id', 'user_id'])
            ->groupBy('property_id')
            ->map(fn($rows) => $rows->pluck('user_id')->all())
            ->toArray();

        // Pre-fill from query params (for unscheduled checkouts)
        $preselect = [
            'property_id' => $request->query('property_id'),
            'date' => $request->query('date'),
        ];

        return view('sessions.manage.create', compact('properties', 'housekeepers', 'propertyCleaners', 'acting', 'preselect'));
    }

    public function store(Request $request)
    {
        $u = Auth::user();
        $acting = $this->actingRole($request);
        abort_if($acting === 'forbidden', 403);

        $data = $request->validate([
            'property_id'    => ['required', 'integer', 'exists:properties,id'],
            'housekeeper_id' => ['required', 'integer', 'exists:users,id'],
            'scheduled_date' => ['required', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'status'         => ['nullable', Rule::in(['pending', 'in_progress', 'completed'])],
            'checkout_id'    => ['nullable', 'integer', 'exists:property_checkouts,id'],
            'sporadic_tasks' => ['nullable', 'array'],
            'sporadic_tasks.*'=> ['string'],
        ]);


        $property = Property::with(['rooms.tasks', 'propertyTasks'])->find($data['property_id']);
        $taskCount = ($property?->rooms->flatMap->tasks->count() ?? 0) + ($property?->propertyTasks->count() ?? 0);
        if ($taskCount < 1) {
            return back()
                ->withErrors(['property_id' => 'The selected property has no tasks defined. Please define rooms or property tasks before scheduling a session.'])
                ->withInput();
        }


        // owner may create only for own properties or their owners' properties
        if ($acting === 'owner') {
            $isAuthorized = ($property->owner_id === $u->id);
            if (!$isAuthorized && $u->hasRole('company')) {
                $isAuthorized = User::where('id', $property->owner_id)->where('owner_id', $u->id)->exists();
            }
            abort_unless($isAuthorized, 403, 'You can schedule only for your properties or properties of your owners.');
        }

        // assignee must be explicitly assigned to the property
        abort_unless(
            \Illuminate\Support\Facades\DB::table('property_user')
                ->where('property_id', $data['property_id'])
                ->where('user_id', $data['housekeeper_id'])
                ->exists(),
            422,
            'Assignee must be explicitly assigned to this property.'
        );

        // prevent duplicates (also enforced by unique index)
        $dup = CleaningSession::query()
            ->where('property_id', $data['property_id'])
            ->where('housekeeper_id', $data['housekeeper_id'])
            ->whereDate('scheduled_date', $data['scheduled_date'])
            ->exists();
        if ($dup) {
            return back()
                ->withErrors(['scheduled_date' => 'Duplicate assignment for this housekeeper/property/date.'])
                ->withInput();
        }

        // Use Property's owner_id so that the actual property owner can see the session
        // (If Admin creates, they also use property's owner_id)
        $ownerId = $property->owner_id;

        CleaningSession::create([
            'property_id'    => $data['property_id'],
            'owner_id'       => $ownerId,
            'housekeeper_id' => $data['housekeeper_id'],
            'scheduled_date' => $data['scheduled_date'],
            'scheduled_time' => $data['scheduled_time'] ?? null,
            'status'         => $data['status'] ?? 'pending',
            'checkout_id'    => $data['checkout_id'] ?? null,
            'sporadic_tasks' => array_values(array_unique($data['sporadic_tasks'] ?? [])),
        ]);

        return redirect()->route('calendar.index', [
            'month' => \Carbon\Carbon::parse($data['scheduled_date'])->format('Y-m'),
            'day'   => \Carbon\Carbon::parse($data['scheduled_date'])->toDateString(),
        ])->with('ok', 'Assignment created.');
    }

    public function edit(Request $request, CleaningSession $session)
    {
        $u = Auth::user();
        $acting = $this->actingRole($request);
        abort_if($acting === 'forbidden', 403);

        if ($acting === 'owner') {
            $isAuthorized = ($session->property->owner_id === $u->id);
            if (!$isAuthorized && $u->hasRole('company')) {
                $isAuthorized = User::where('id', $session->property->owner_id)->where('owner_id', $u->id)->exists();
            }
            abort_unless($isAuthorized, 403);
        }

        $properties = Property::query()
            ->when($acting === 'owner', function($qry) use ($u) {
                if ($u->hasRole('company')) {
                    $qry->where(function($q) use ($u) {
                        $q->where('owner_id', $u->id)
                          ->orWhereIn('owner_id', function($sub) use ($u) {
                              $sub->select('id')->from('users')->where('owner_id', $u->id);
                          });
                    });
                } else {
                    $qry->where('owner_id', $u->id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $housekeepers = User::query()
            ->when($acting === 'owner', function($qry) use ($u) {
                if ($u->hasRole('company')) {
                    $qry->where('id', $u->id)
                        ->orWhere('owner_id', $u->id)
                        ->orWhere('id', clone $u)->select('owner_id')->from('users')->where('id', $u->id);
                } else {
                    $qry->where('id', $u->id)->orWhere('owner_id', $u->id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $propertyCleaners = \Illuminate\Support\Facades\DB::table('property_user')
            ->whereIn('property_id', $properties->pluck('id'))
            ->get(['property_id', 'user_id'])
            ->groupBy('property_id')
            ->map(fn($rows) => $rows->pluck('user_id')->all())
            ->toArray();

        return view('sessions.manage.edit', compact('session', 'properties', 'housekeepers', 'propertyCleaners', 'acting'));
    }

    public function update(Request $request, CleaningSession $session)
    {
        $u = Auth::user();
        $acting = $this->actingRole($request);
        abort_if($acting === 'forbidden', 403);

        // Check view/edit permission
        if ($acting === 'owner') {
            $isAuthorized = ($session->property->owner_id === $u->id);
            if (!$isAuthorized && $u->hasRole('company')) {
                $isAuthorized = User::where('id', $session->property->owner_id)->where('owner_id', $u->id)->exists();
            }
            abort_unless($isAuthorized, 403);
        }

        $data = $request->validate([
            'property_id'    => ['required', 'integer', 'exists:properties,id'],
            'housekeeper_id' => ['required', 'integer', 'exists:users,id'],
            'scheduled_date' => ['required', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'status'         => ['required', Rule::in(['pending', 'in_progress', 'completed'])],
            'sporadic_tasks' => ['nullable', 'array'],
            'sporadic_tasks.*'=> ['string'],
        ]);

        $newProperty = Property::find($data['property_id']);

        // Check if user is allowed to assign this new property
        if ($acting === 'owner') {
            $isAuthorized = ($newProperty->owner_id === $u->id);
            if (!$isAuthorized && $u->hasRole('company')) {
                 $isAuthorized = User::where('id', $newProperty->owner_id)->where('owner_id', $u->id)->exists();
            }
            abort_unless($isAuthorized, 403, 'You do not have permission for this property.');
        }

        // assignee must be explicitly assigned to the property
        abort_unless(
            \Illuminate\Support\Facades\DB::table('property_user')
                ->where('property_id', $data['property_id'])
                ->where('user_id', $data['housekeeper_id'])
                ->exists(),
            422,
            'Assignee must be explicitly assigned to this property.'
        );

        $dup = CleaningSession::query()
            ->where('property_id', $data['property_id'])
            ->where('housekeeper_id', $data['housekeeper_id'])
            ->whereDate('scheduled_date', $data['scheduled_date'])
            ->where('id', '<>', $session->id)
            ->exists();
        if ($dup) {
            return back()
                ->withErrors(['scheduled_date' => 'Duplicate assignment for this housekeeper/property/date.'])
                ->withInput();
        }

        // Always sync the owner_id to the property's owner_id to maintain consistency
        $data['owner_id'] = $newProperty->owner_id;
        $data['sporadic_tasks'] = array_values(array_unique($data['sporadic_tasks'] ?? []));

        $session->update($data);

        return redirect()->route('manage.sessions.index')->with('ok', 'Assignment updated.');
    }

    public function destroy(Request $request, CleaningSession $session)
    {
        $u = Auth::user();
        $acting = $this->actingRole($request);
        abort_if($acting === 'forbidden', 403);

        if ($acting === 'owner') {
             $isAuthorized = ($session->property->owner_id === $u->id);
            if (!$isAuthorized && $u->hasRole('company')) {
                $isAuthorized = User::where('id', $session->property->owner_id)->where('owner_id', $u->id)->exists();
            }
            abort_unless($isAuthorized, 403);
        }

        $session->delete();

        return redirect()->route('manage.sessions.index')->with('ok', 'Assignment deleted.');
    }

    public function getSporadicTasks(Request $request, Property $property)
    {
        $u = Auth::user();
        $acting = $this->actingRole($request);
        abort_if($acting === 'forbidden', 403);

        // 1. Get Room IDs for this property as a primitive array to prevent in_array TypeErrors
        $roomIds = $property->rooms()->pluck('rooms.id')->toArray();

        // 2. Find ALL tasks marked sporadic that are attached to those rooms
        //    Include the room name so user knows which room each task belongs to
        $roomTasks = Task::where('is_sporadic', true)
            ->whereHas('rooms', function($q) use ($roomIds) {
                $q->whereIn('rooms.id', $roomIds);
            })
            ->with(['rooms' => function($q) use ($roomIds) {
                $q->whereIn('rooms.id', $roomIds);
            }])
            ->get();

        // 3. Find ALL property-level tasks marked sporadic for this property
        $propertyTasks = $property->propertyTasks()->where('is_sporadic', true)->get();

        // 4. Helper: find when a task was last completed for this property
        $propertySessionIds = CleaningSession::where('property_id', $property->id)
            ->pluck('id');

        $formatted = [];
        
        // Formulate uniquely by Task ID and Room ID
        foreach ($roomTasks as $task) {
            foreach ($task->rooms as $room) {
                // Ensure the room is actually part of the requested session/property room list
                if (!in_array($room->id, $roomIds)) continue;

                $lastDone = ChecklistItem::where('task_id', $task->id)
                    ->where('room_id', $room->id)
                    ->where('checked', true)
                    ->whereIn('session_id', $propertySessionIds)
                    ->whereNotNull('checked_at')
                    ->orderByDesc('checked_at')
                    ->value('checked_at');

                $compoundKey = $task->id . '_' . $room->id;

                $formatted[$compoundKey] = [
                    'id' => $compoundKey,
                    'name' => $task->name . ' (' . $room->name . ')',
                    'last_done' => $lastDone ? \Carbon\Carbon::parse($lastDone)->diffForHumans() : null,
                ];
            }
        }

        foreach ($propertyTasks as $task) {
            $lastDone = ChecklistItem::where('task_id', $task->id)
                ->where('checked', true)
                ->whereNull('room_id')
                ->whereIn('session_id', $propertySessionIds)
                ->whereNotNull('checked_at')
                ->orderByDesc('checked_at')
                ->value('checked_at');

            $compoundKey = $task->id . '_global';

            $cleanName = trim(str_ireplace(['[Property Wide]', '(Property Wide)'], '', $task->name));
            if (stripos($cleanName, 'Property Wide') === 0) {
                $cleanName = trim(substr($cleanName, 13), " -");
            }

            $formatted[$compoundKey] = [
                'id' => $compoundKey,
                'name' => $cleanName,
                'last_done' => $lastDone ? \Carbon\Carbon::parse($lastDone)->diffForHumans() : null,
            ];
        }

        return response()->json(array_values($formatted));
    }
}

