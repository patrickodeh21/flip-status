<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartSessionRequest;
use App\Models\ChecklistItem;
use App\Models\ChecklistReport;
use App\Models\CleaningSession;
use App\Services\GpsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Traits\HasRoles;

class SessionController extends Controller
{
    use AuthorizesRequests, HasRoles;
    public function index()
    {
        $today = now()->toDateString();
        $sessions = CleaningSession::query()
            ->where('housekeeper_id', Auth::id())
            ->where(function ($q) use ($today) {
                // All pending or in-progress jobs (past, present, future)
                $q->whereIn('status', ['pending', 'in_progress'])
                  // OR completed jobs scheduled for today or the future
                  ->orWhere(function ($sub) use ($today) {
                      $sub->where('status', 'completed')
                          ->whereDate('scheduled_date', '>=', $today);
                  })
                  // OR completed jobs that were actually completed today
                  ->orWhere(function ($sub) use ($today) {
                      $sub->where('status', 'completed')
                          ->whereDate('ended_at', $today);
                  });
            })
            ->orderBy('scheduled_date', 'asc')
            ->paginate(20);

        return view('sessions.index', compact('sessions'));
    }


    /**
     * Get session data as JSON for API requests
     */
    public function getData(CleaningSession $session)
    {
        // Ensure report token exists for shareable report URLs (safe if method is missing)
        if (method_exists($session, 'ensureReportToken')) {
            $session->ensureReportToken();
        }

        $data = $this->prepareSessionData($session);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Helper to eagerly load rooms with their tasks, and filter the tasks based on compound IDs.
     */
    private function loadFilteredRoomsAndTasks($property, array $sporadics)
    {
        $sporadicTaskIds = collect($sporadics)->map(fn($item) => (int) explode('_', $item)[0])->filter()->unique()->toArray();

        $rooms = $property->rooms()
            ->with([
                'tasks' => function($q) use ($sporadicTaskIds) {
                    $q->where(function($query) use ($sporadicTaskIds) {
                        $query->where('is_sporadic', false)
                              ->orWhereNull('is_sporadic')
                              ->orWhereIn('tasks.id', $sporadicTaskIds);
                    })->orderBy('room_task.sort_order')->orderBy('tasks.name');
                },
                'tasks.media',
            ])
            ->orderBy('property_room.sort_order')
            ->get();

        foreach ($rooms as $room) {
            if ($room->tasks) {
                $room->setRelation('tasks', $room->tasks->filter(function($task) use ($sporadics, $room) {
                    if (!$task->is_sporadic) return true;
                    return in_array($task->id . '_' . $room->id, $sporadics);
                })->values());
            }
        }

        return $rooms;
    }

    /**
     * Helper to load property level tasks and filter.
     */
    private function loadFilteredPropertyTasks($property, array $sporadics)
    {
        $sporadicTaskIds = collect($sporadics)->map(fn($item) => (int) explode('_', $item)[0])->filter()->unique()->toArray();

        return $property->propertyTasks()
            ->where(function($query) use ($sporadicTaskIds) {
                $query->where('is_sporadic', false)
                      ->orWhereNull('is_sporadic')
                      ->orWhereIn('tasks.id', $sporadicTaskIds);
            })
            ->orderBy('property_tasks.sort_order')
            ->get()
            ->filter(function($task) use ($sporadics) {
                 if (!$task->is_sporadic) return true;
                 return in_array($task->id . '_global', $sporadics);
            })->values();
    }

    /**
     * Prepare session data (shared between show and getData methods)
     */
    private function prepareSessionData(CleaningSession $session): array
    {
        $session->loadMissing(['checklistItems.photos', 'property.propertyTasks.media']);
        $sporadics = is_array($session->sporadic_tasks) ? $session->sporadic_tasks : [];

        // Order rooms & tasks by their pivot sort_order (no visual design change, just consistency)
        $rooms = $this->loadFilteredRoomsAndTasks($session->property, $sporadics);

        // Ensure existing checklist items (same as before, but already correct with room context)
        foreach ($rooms as $room) {
            foreach ($room->tasks as $task) {
                \App\Models\ChecklistItem::firstOrCreate(
                    [
                        'session_id' => $session->id,
                        'room_id'    => $room->id,
                        'task_id'    => $task->id,
                    ],
                    [
                        'user_id' => auth()->id(),
                        'checked' => false,
                    ]
                );
            }
        }

        // Eager-load checklist items to avoid N+1 when the view scans them
        $session->load('checklistItems');

        // Load property-level tasks
        $property = $session->property;
        $propertyTasks = $this->loadFilteredPropertyTasks($property, $sporadics);

        // Ensure property-level checklist items exist
        foreach ($propertyTasks as $task) {
            \App\Models\ChecklistItem::firstOrCreate(
                [
                    'session_id' => $session->id,
                    'room_id'    => null, // Property-level tasks have no room
                    'task_id'    => $task->id,
                ],
                [
                    'user_id' => auth()->id(),
                    'checked' => false,
                ]
            );
        }

        // Separate property-level tasks by phase
        $preCleaningTasks = $propertyTasks->where('phase', 'pre_cleaning');
        $duringCleaningTasks = $propertyTasks->where('phase', 'during_cleaning');
        $postCleaningTasks = $propertyTasks->where('phase', 'post_cleaning');

        // Count property-level tasks
        $preCleaningCount = $preCleaningTasks->count();
        $duringCleaningCount = $duringCleaningTasks->count();
        $postCleaningCount = $postCleaningTasks->count();

        // Count checked property-level tasks
        $checkedPreCleaningCount = ChecklistItem::where('session_id', $session->id)
            ->whereNull('room_id')
            ->whereIn('task_id', $preCleaningTasks->pluck('id'))
            ->where('checked', true)
            ->count();
        $checkedDuringCleaningCount = ChecklistItem::where('session_id', $session->id)
            ->whereNull('room_id')
            ->whereIn('task_id', $duringCleaningTasks->pluck('id'))
            ->where('checked', true)
            ->count();
        $checkedPostCleaningCount = ChecklistItem::where('session_id', $session->id)
            ->whereNull('room_id')
            ->whereIn('task_id', $postCleaningTasks->pluck('id'))
            ->where('checked', true)
            ->count();

        // Compute photo counts early so room-skip logic can evaluate them
        $photosByRoom = $session->photos()->latest()->get()->groupBy('room_id');

        // Calculate photo counts per room
        $photoCounts = $rooms->mapWithKeys(function ($room) use ($photosByRoom) {
            return [$room->id => $photosByRoom->get($room->id)?->count() ?? 0];
        });

        // Separate tasks by type and find first incomplete room indices (unchanged)
        $roomTasksByRoom      = [];
        $firstIncompleteRoomIndex      = null;

        foreach ($rooms as $index => $room) {
            $roomTasks      = $room->tasks; // Include all task types in the room
            $roomTasksByRoom[$room->id]      = $roomTasks;

            $checkableTasks = $roomTasks->where('type', '!=', 'instructions');

            $checkedCount = ChecklistItem::where('session_id', $session->id)
                ->where('room_id', $room->id)
                ->whereIn('task_id', $checkableTasks->pluck('id'))
                ->where('checked', true)
                ->count();
            $totalCount = $checkableTasks->count();
            $roomPhotoCount = $photoCounts[$room->id] ?? 0;
            $minPhotos = $room->min_photos ?? 2;

            // A room is incomplete if tasks are unchecked OR photos are below minimum
            if ($firstIncompleteRoomIndex === null && ($checkedCount < $totalCount || $roomPhotoCount < $minPhotos)) {
                $firstIncompleteRoomIndex = $index;
            }
        }

        // Counts & stages (updated to include property-level tasks)
        $allRoomTasksCount          = $rooms->flatMap->tasks->where('type', '!=', 'instructions')->count();
        $checkedRoomTasksCount      = ChecklistItem::where('session_id', $session->id)
            ->whereHas('task', fn($q) => $q->where('type', '!=', 'instructions'))
            ->whereNotNull('room_id')
            ->where('checked', true)
            ->count();
        $allInventoryTasksCount     = 0; // Legacy
        $checkedInventoryTasksCount = 0; // Legacy

        // Determine current stage.
        // Prefer the persisted database stage (advanced via advanceStage()), but fall back to
        // auto-detection when stage is missing to stay backward-compatible.
        $stage = 'rooms'; // Default fallback

        // Map legacy stage names
        $legacyMap = ['rooms_first_half' => 'rooms', 'rooms_second_half' => 'rooms', 'inventory' => 'rooms'];

        if ($session->status === 'completed') {
            $stage = 'summary';
        } elseif (!empty($session->stage)) {
            $stage = $legacyMap[$session->stage] ?? $session->stage;
        } else {
            // Auto-detect stage based on remaining work (legacy behavior)
            $pendingPreCleaning = $preCleaningCount - $checkedPreCleaningCount;
            $pendingRoomTasks = $allRoomTasksCount - $checkedRoomTasksCount;
            $pendingDuringCleaning = $duringCleaningCount - $checkedDuringCleaningCount;
            $pendingPostCleaning = $postCleaningCount - $checkedPostCleaningCount;

            if ($pendingPreCleaning > 0) {
                $stage = 'pre_cleaning';
            } elseif ($pendingRoomTasks > 0) {
                $stage = 'rooms';
            } elseif ($pendingDuringCleaning > 0) {
                $stage = 'during_cleaning';
            } elseif ($pendingPostCleaning > 0) {
                $stage = 'post_cleaning';
            } else {
                $stage = 'photos';
            }
        }

        // For housekeepers: determine if they can edit (must be current date and at property location)
        // Location check will be done via JavaScript when they try to start, but we check date here
        $canEdit = true;
        $isViewOnly = false;

        if (auth()->check() && auth()->user()->hasRole('housekeeper') && !auth()->user()->hasAnyRole(['admin', 'owner', 'company'])) {
            $propertyTz = $session->property->timezone ?? config('app.timezone');
            $isCurrentDate = \Carbon\Carbon::parse($session->scheduled_date)->format('Y-m-d') === now($propertyTz)->format('Y-m-d');
            $isInProgressOrCompleted = in_array($session->status, ['in_progress', 'completed']);

            // Can edit if: it's the current date AND (session is pending OR already in progress/completed)
            // OR if session is already in progress/completed (they can continue working)
            if (!$isCurrentDate && $session->status === 'pending') {
                $canEdit = false;
                $isViewOnly = true;
            }
        }

        // Split rooms into first half and second half
        $roomsArray = $rooms->values();
        $totalRooms = $roomsArray->count();
        $halfPoint = (int) ceil($totalRooms / 2);
        $firstHalfRooms = $roomsArray->slice(0, $halfPoint)->values();
        $secondHalfRooms = $roomsArray->slice($halfPoint)->values();

        $mapRoom = function ($room) use ($session, $roomTasksByRoom) {
            $roomTasks = $roomTasksByRoom[$room->id] ?? collect();

            return [
                'id' => $room->id,
                'name' => $room->name,
                'min_photos' => $room->min_photos ?? 2,
                'tasks' => $room->tasks->map(function ($task) use ($session, $room) {
                    $item = $session->checklistItems->first(
                        fn($ci) => (int) $ci->room_id === (int) $room->id && (int) $ci->task_id === (int) $task->id,
                    );

                    return [
                        'id' => $task->id,
                        'name' => $task->name,
                        'type' => $task->type,
                        'instructions' => $task->pivot->instructions ?? $task->instructions,
                        'media' => $task->media->map(fn($m) => [
                            'id' => $m->id,
                            'type' => $m->type,
                            'url' => $m->url,
                            'thumbnail' => $m->thumbnail,
                            'caption' => $m->caption,
                        ])->values()->toArray(),
                        'checklist_item' => $item ? [
                            'id' => $item->id,
                            'checked' => $item->checked,
                            'note' => $item->note,
                            'checked_at' => $item->checked_at?->toIso8601String(),
                            'photos' => $item->photos->map(fn($p) => [
                                'id' => $p->id,
                                'url' => $p->url,
                                'note' => $p->note,
                            ])->values()->toArray(),
                        ] : null,
                    ];
                })->values()->toArray(),
                'room_tasks' => $roomTasks->pluck('id')->toArray(),
                'inventory_tasks' => [],
            ];
        };

        return [
            'session' => [
                'id' => $session->id,
                'status' => $session->status,
                'stage' => $stage,
                'scheduled_date' => \Carbon\Carbon::parse($session->scheduled_date)->toDateString(),
                'started_at' => $session->started_at?->toIso8601String(),
                'ended_at' => $session->ended_at?->toIso8601String(),
                'gps_confirmed_at' => $session->gps_confirmed_at?->toIso8601String(),
                'skipped_rooms' => $session->skipped_rooms ?? [],
            ],
            'property' => [
                'id' => $session->property->id,
                'name' => $session->property->name,
            ],
            'rooms' => $rooms->map($mapRoom)->values()->toArray(),
            'rooms_first_half' => $firstHalfRooms->map($mapRoom)->values()->toArray(),
            'rooms_second_half' => $secondHalfRooms->map($mapRoom)->values()->toArray(),
            'property_tasks' => [
                'pre_cleaning' => $preCleaningTasks->map(function ($task) use ($session) {
                    $item = $session->checklistItems->first(
                        fn($ci) => $ci->room_id === null && (int) $ci->task_id === (int) $task->id,
                    );

                    return [
                        'id' => $task->id,
                        'name' => $task->name,
                        'phase' => $task->phase,
                        'instructions' => $task->pivot->instructions ?? $task->instructions,
                        'media' => $task->media->map(fn($m) => [
                            'id' => $m->id,
                            'type' => $m->type,
                            'url' => $m->url,
                            'thumbnail' => $m->thumbnail,
                            'caption' => $m->caption,
                        ])->values()->toArray(),
                        'checklist_item' => $item ? [
                            'id' => $item->id,
                            'checked' => $item->checked,
                            'note' => $item->note,
                            'checked_at' => $item->checked_at?->toIso8601String(),
                            'photos' => $item->photos->map(fn($p) => [
                                'id' => $p->id,
                                'url' => $p->url,
                                'note' => $p->note,
                            ])->values()->toArray(),
                        ] : null,
                    ];
                })->values()->toArray(),
                'during_cleaning' => $duringCleaningTasks->map(function ($task) use ($session) {
                    $item = $session->checklistItems->first(
                        fn($ci) => $ci->room_id === null && (int) $ci->task_id === (int) $task->id,
                    );

                    return [
                        'id' => $task->id,
                        'name' => $task->name,
                        'phase' => $task->phase,
                        'instructions' => $task->pivot->instructions ?? $task->instructions,
                        'media' => $task->media->map(fn($m) => [
                            'id' => $m->id,
                            'type' => $m->type,
                            'url' => $m->url,
                            'thumbnail' => $m->thumbnail,
                            'caption' => $m->caption,
                        ])->values()->toArray(),
                        'checklist_item' => $item ? [
                            'id' => $item->id,
                            'checked' => $item->checked,
                            'note' => $item->note,
                            'checked_at' => $item->checked_at?->toIso8601String(),
                            'photos' => $item->photos->map(fn($p) => [
                                'id' => $p->id,
                                'url' => $p->url,
                                'note' => $p->note,
                            ])->values()->toArray(),
                        ] : null,
                    ];
                })->values()->toArray(),
                'post_cleaning' => $postCleaningTasks->map(function ($task) use ($session) {
                    $item = $session->checklistItems->first(
                        fn($ci) => $ci->room_id === null && (int) $ci->task_id === (int) $task->id,
                    );

                    return [
                        'id' => $task->id,
                        'name' => $task->name,
                        'phase' => $task->phase,
                        'instructions' => $task->pivot->instructions ?? $task->instructions,
                        'media' => $task->media->map(fn($m) => [
                            'id' => $m->id,
                            'type' => $m->type,
                            'url' => $m->url,
                            'thumbnail' => $m->thumbnail,
                            'caption' => $m->caption,
                        ])->values()->toArray(),
                        'checklist_item' => $item ? [
                            'id' => $item->id,
                            'checked' => $item->checked,
                            'note' => $item->note,
                            'checked_at' => $item->checked_at?->toIso8601String(),
                            'photos' => $item->photos->map(fn($p) => [
                                'id' => $p->id,
                                'url' => $p->url,
                                'note' => $p->note,
                            ])->values()->toArray(),
                        ] : null,
                    ];
                })->values()->toArray(),
            ],
            'stage' => $stage, // This now comes from database, not auto-calculated
            'counts' => [
                'pre_cleaning' => [
                    'total' => $preCleaningCount,
                    'checked' => $checkedPreCleaningCount,
                ],
                'during_cleaning' => [
                    'total' => $duringCleaningCount,
                    'checked' => $checkedDuringCleaningCount,
                ],
                'post_cleaning' => [
                    'total' => $postCleaningCount,
                    'checked' => $checkedPostCleaningCount,
                ],
                'room_tasks' => [
                    'total' => $allRoomTasksCount,
                    'checked' => $checkedRoomTasksCount,
                ],
                'inventory_tasks' => [
                    'total' => $allInventoryTasksCount,
                    'checked' => $checkedInventoryTasksCount,
                ],
            ],
            'photo_counts' => $photoCounts->toArray(),
            'photos_by_room' => $photosByRoom->map(function ($photos) {
                return $photos->map(fn($photo) => [
                    'id' => $photo->id,
                    'url' => $photo->url, // Uses the accessor from RoomPhoto model
                    'captured_at' => $photo->captured_at?->toIso8601String(),
                ]);
            })->toArray(),
            'first_incomplete_room_index' => $firstIncompleteRoomIndex,
            'first_incomplete_inventory_index' => null,
            'can_edit' => $canEdit,
            'is_view_only' => $isViewOnly,
        ];
    }

    public function show(CleaningSession $session)
    {
        // Ensure report token exists for shareable report URLs (safe if method is missing)
        if (method_exists($session, 'ensureReportToken')) {
            $session->ensureReportToken();
        }

        // Use the same data preparation logic
        $data = $this->prepareSessionData($session);

        // But convert back to Eloquent models for the view
        // Order rooms & tasks by their pivot sort_order
        $sporadics = is_array($session->sporadic_tasks) ? $session->sporadic_tasks : [];
        $sporadicTaskIds = collect($sporadics)->map(fn($item) => (int) explode('_', $item)[0])->unique()->filter()->toArray();

        $rooms = $session->property->rooms()
            ->with([
                'tasks' => function($q) use ($sporadics) {
                    $q->where(function($query) use ($sporadics) {
                        $query->where('is_sporadic', false)
                              ->orWhereNull('is_sporadic')
                              ->orWhereIn('tasks.id', $sporadics);
                    })->orderBy('room_task.sort_order')->orderBy('tasks.name');
                },
                'tasks.media',
            ])
            ->orderBy('property_room.sort_order')
            ->get();

        // Eager-load checklist items
        $session->load('checklistItems');

        // Load property-level tasks
        $property = $session->property;
        $propertyTasks = $property->propertyTasks()
            ->where(function($query) use ($sporadics) {
                $query->where('is_sporadic', false)
                      ->orWhereNull('is_sporadic')
                      ->orWhereIn('tasks.id', $sporadics);
            })
            ->orderBy('property_tasks.sort_order')
            ->get();

        // Separate property-level tasks by phase
        $preCleaningTasks = $propertyTasks->where('phase', 'pre_cleaning');
        $duringCleaningTasks = $propertyTasks->where('phase', 'during_cleaning');
        $postCleaningTasks = $propertyTasks->where('phase', 'post_cleaning');

        // Separate tasks by type
        $roomTasksByRoom = [];
        $inventoryTasksByRoom = [];
        foreach ($rooms as $room) {
            $roomTasksByRoom[$room->id] = $room->tasks; // Include all tasks
            $inventoryTasksByRoom[$room->id] = collect(); // Legacy empty collection
        }

        $photosByRoom = $session->photos()->latest()->get()->groupBy('room_id');

        return view('sessions.show', [
            'session' => $session,
            'rooms' => $rooms,
            'stage' => $data['stage'],
            'photoCounts' => $data['photo_counts'],
            'hasMinPhotos' => $rooms->every(fn($room) => ($data['photo_counts'][$room->id] ?? 0) >= 8),
            'roomTasksByRoom' => $roomTasksByRoom,
            'inventoryTasksByRoom' => $inventoryTasksByRoom,
            'firstIncompleteRoomIndex' => $data['first_incomplete_room_index'],
            'firstIncompleteInventoryIndex' => $data['first_incomplete_inventory_index'],
            'photosByRoom' => $photosByRoom,
            'preCleaningTasks' => $preCleaningTasks,
            'duringCleaningTasks' => $duringCleaningTasks,
            'postCleaningTasks' => $postCleaningTasks,
            'preCleaningCount' => $data['counts']['pre_cleaning']['total'],
            'duringCleaningCount' => $data['counts']['during_cleaning']['total'],
            'postCleaningCount' => $data['counts']['post_cleaning']['total'],
            'checkedPreCleaningCount' => $data['counts']['pre_cleaning']['checked'],
            'checkedDuringCleaningCount' => $data['counts']['during_cleaning']['checked'],
            'checkedPostCleaningCount' => $data['counts']['post_cleaning']['checked'],
            'canEdit' => $data['can_edit'],
            'isViewOnly' => $data['is_view_only'],
        ]);
    }

    public function start(StartSessionRequest $request, CleaningSession $session)
    {
        $lat = (float) $request->validated('latitude');
        $lng = (float) $request->validated('longitude');

        $property = $session->property;

        // Geofence (only if property has coordinates)
        if ($property->latitude !== null && $property->longitude !== null) {
            $distance = GpsService::distanceMeters(
                $lat,
                $lng,
                (float) $property->latitude,
                (float) $property->longitude
            );

            if ($distance > (float) $property->geo_radius_m) {
                // If you want to hard-block, uncomment:
                // return back()->withErrors(['gps' => 'You are too far from the property to start.']);
            }
        }

        $session->update([
            'status'           => 'in_progress',
            'started_at'       => now(),
            'gps_confirmed_at' => now(),
            'start_latitude'   => $lat,
            'start_longitude'  => $lng,
            'stage'            => 'pre_cleaning',
        ]);

        // Fast-forward past any initial empty stages so user doesn't land on a blank page
        $stages = [
            'pre_cleaning',
            'rooms',
            'during_cleaning',
            'photos',
            'post_cleaning',
            'summary'
        ];

        foreach ($stages as $stage) {
            if ($this->stageHasContent($session, $stage)) {
                if ($session->stage !== $stage) {
                    $session->stage = $stage;
                    $session->save();
                }
                break;
            }
        }

        activity()->performedOn($session)->event('started')->log('Session started');

        // ---- Bootstrap checklist items from Property -> Rooms (pivot) -> Tasks (pivot)
        // We must use the room from the property-room pivot, then each task attached to that room.
        $sporadics = is_array($session->sporadic_tasks) ? $session->sporadic_tasks : [];
        $sporadicTaskIds = collect($sporadics)->map(fn($item) => (int) explode('_', $item)[0])->unique()->filter()->toArray();

        $rooms = $this->loadFilteredRoomsAndTasks($property, $sporadics);

        // Load property-level tasks
        $propertyTasks = $this->loadFilteredPropertyTasks($property, $sporadics);

        // Build rows for bulk insert; avoid per-row queries
        $rows = [];
        $now  = now();
        $uid  = Auth::id();

        // Add room-level tasks
        foreach ($rooms as $room) {
            foreach ($room->tasks as $task) {
                $rows[] = [
                    'session_id' => $session->id,
                    'room_id'    => $room->id,   // << from the property-room context
                    'task_id'    => $task->id,
                    'user_id'    => $uid,
                    'checked'    => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Add property-level tasks (room_id is null)
        foreach ($propertyTasks as $task) {
            $rows[] = [
                'session_id' => $session->id,
                'room_id'    => null,  // Property-level tasks have no room
                'task_id'    => $task->id,
                'user_id'    => $uid,
                'checked'    => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($rows)) {
            // Insert only those that don't already exist (unique by session_id+room_id+task_id)
            // If you have a DB unique index on these three, insertOrIgnore is perfect.
            ChecklistItem::insertOrIgnore($rows);
        }

        return redirect()->route('sessions.show', $session);
    }

    public function complete(Request $request, CleaningSession $session)
    {

        $rooms = $session->property->rooms()->with('tasks')->get();
        foreach ($rooms as $room) {
            $count = $session->photos()->where('room_id', $room->id)->count();
            $minPhotos = $room->min_photos ?? 2;
            if ($count < $minPhotos) {
                $message = "Room {$room->name} needs at least {$minPhotos} summary photos.";
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $message], 422);
                }
                return back()->withErrors(['photos' => $message]);
            }
        }

        $updateData = [
            'status' => 'completed',
            'stage' => 'summary'
        ];
        if (!$session->ended_at) {
            $updateData['ended_at'] = now();
        }
        $session->update($updateData);
        activity()->performedOn($session)->event('completed')->log('Session completed');

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'redirect' => route('sessions.index')]);
        }
        return redirect()->route('sessions.index')->with('ok', 'Checklist submitted.');
    }

    /**
     * Advance the session to the next stage
     */
    public function advanceStage(Request $request, CleaningSession $session)
    {
        try {
            // Simple authorization check
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to advance this session.'
                ], 403);
            }

            if ($session->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Session must be in progress to advance stages.'
                ], 400);
            }

            $stages = [
                'pre_cleaning',
                'rooms',
                'during_cleaning',
                'photos',
                'post_cleaning',
                'summary'
            ];

            $currentIndex = array_search($session->stage, $stages);
            $requestedStage = $request->input('current_stage');

            if ($requestedStage && $requestedStage !== $session->stage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session stage has changed. Please refresh the page.',
                    'current_stage' => $session->stage
                ], 409);
            }

            if ($currentIndex !== false && $currentIndex < count($stages) - 1) {
                $nextStage = $stages[$currentIndex + 1];

                // Check if the next stage has any tasks or content
                $hasContent = $this->stageHasContent($session, $nextStage);

                if (!$hasContent) {
                    // Skip empty stage and move to the next one
                    return $this->skipToNextNonEmptyStage($session, $currentIndex + 1, $stages, $request);
                }

                $session->stage = $nextStage;
                if ($nextStage === 'summary') {
                    $session->status = 'completed';
                    $session->ended_at = now();
                }
                $session->save();

                // Session stays 'in_progress' until the user explicitly
                // clicks "Submit Checklist" on the summary page, which
                // calls the /complete endpoint.

                activity()
                    ->performedOn($session)
                    ->event('stage_advanced')
                    ->log("Session advanced from {$stages[$currentIndex]} to {$nextStage}");

                $sessionData = $this->prepareSessionData($session);

                return response()->json([
                    'success' => true,
                    'message' => "Advanced to " . str_replace('_', ' ', $nextStage),
                    'stage' => $nextStage,
                    'data' => $sessionData
                ]);
            }

            if ($currentIndex === count($stages) - 1) {
                return $this->complete($request, $session);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to advance stage'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to advance stage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Go back to the previous stage
     */
    public function goBackStage(Request $request, CleaningSession $session)
    {
        try {
            if (!Auth::check()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            if ($session->status !== 'in_progress') {
                return response()->json(['success' => false, 'message' => 'Session must be in progress.'], 400);
            }

            $stages = [
                'pre_cleaning',
                'rooms',
                'during_cleaning',
                'photos',
                'post_cleaning',
                'summary'
            ];

            $currentIndex = array_search($session->stage, $stages);
            
            if ($currentIndex !== false && $currentIndex > 0) {
                // Find PREVIOUS non-empty stage
                for ($i = $currentIndex - 1; $i >= 0; $i--) {
                    $prevStage = $stages[$i];
                    if ($this->stageHasContent($session, $prevStage)) {
                        $session->stage = $prevStage;
                        $session->save();
                        
                        $sessionData = $this->prepareSessionData($session);
                        return response()->json([
                            'success' => true,
                            'message' => "Returned to " . str_replace('_', ' ', $prevStage),
                            'stage' => $prevStage,
                            'data' => $sessionData
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Already at the first stage.'
            ], 400);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to go back: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if a stage has any content (tasks or photos)
     */
    private function stageHasContent(CleaningSession $session, string $stage): bool
    {
        $sporadics = is_array($session->sporadic_tasks) ? $session->sporadic_tasks : [];
        $sporadicTaskIds = collect($sporadics)->map(fn($item) => (int) explode('_', $item)[0])->unique()->filter()->toArray();

        switch ($stage) {
            case 'pre_cleaning':
                // Check if there are any pre_cleaning property tasks
                $count = $session->property->propertyTasks()
                    ->where('phase', 'pre_cleaning')
                    ->where(function($query) use ($sporadicTaskIds) {
                        $query->where('is_sporadic', false)
                              ->orWhereNull('is_sporadic')
                              ->orWhereIn('tasks.id', $sporadicTaskIds);
                    })
                    ->get()
                    ->filter(function($task) use ($sporadics) {
                         if (!$task->is_sporadic) return true;
                         return in_array($task->id . '_global', $sporadics);
                    })
                    ->count();
                return $count > 0;

            case 'rooms':
            case 'rooms_first_half':
            case 'rooms_second_half':
                // Check if there are any room tasks
                $rooms = $this->loadFilteredRoomsAndTasks($session->property, $sporadics);

                foreach ($rooms as $room) {
                    if ($room->tasks->count() > 0) {
                        return true;
                    }
                }
                return false;

            case 'during_cleaning':
                // Check if there are any during_cleaning property tasks
                $count = $session->property->propertyTasks()
                    ->where('phase', 'during_cleaning')
                    ->where(function($query) use ($sporadicTaskIds) {
                        $query->where('is_sporadic', false)
                              ->orWhereNull('is_sporadic')
                              ->orWhereIn('tasks.id', $sporadicTaskIds);
                    })
                    ->get()
                    ->filter(function($task) use ($sporadics) {
                         if (!$task->is_sporadic) return true;
                         return in_array($task->id . '_global', $sporadics);
                    })
                    ->count();
                return $count > 0;

            case 'post_cleaning':
                // Check if there are any post_cleaning tasks
                $count = $session->property->propertyTasks()
                    ->where('phase', 'post_cleaning')
                    ->where(function($query) use ($sporadicTaskIds) {
                        $query->where('is_sporadic', false)
                              ->orWhereNull('is_sporadic')
                              ->orWhereIn('tasks.id', $sporadicTaskIds);
                    })
                    ->get()
                    ->filter(function($task) use ($sporadics) {
                         if (!$task->is_sporadic) return true;
                         return in_array($task->id . '_global', $sporadics);
                    })
                    ->count();
                return $count > 0;

            case 'photos':
                // Photos stage always has content (photo upload UI)
                return true;

            case 'summary':
                // Summary stage always has content
                return true;

            default:
                return false;
        }
    }

    /**
     * Skip to the next non-empty stage
     */
    private function skipToNextNonEmptyStage(CleaningSession $session, int $startIndex, array $stages, Request $request)
    {
        for ($i = $startIndex; $i < count($stages); $i++) {
            $stage = $stages[$i];

            if ($this->stageHasContent($session, $stage)) {
                $session->stage = $stage;
                if ($stage === 'summary') {
                    $session->status = 'completed';
                    if (!$session->ended_at) {
                        $session->ended_at = now();
                    }
                }
                $session->save();

                activity()
                    ->performedOn($session)
                    ->event('stage_advanced')
                    ->log("Session advanced to {$stage} (skipped empty stages)");

                $sessionData = $this->prepareSessionData($session);

                $message = $i > $startIndex
                    ? "Advanced to " . str_replace('_', ' ', $stage) . " (skipped empty stages)"
                    : "Advanced to " . str_replace('_', ' ', $stage);

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'stage' => $stage,
                    'data' => $sessionData
                ]);
            }
        }

        // If all remaining stages are empty, go to photos (which always has content)
        $session->stage = 'photos';
        $session->save();

        $sessionData = $this->prepareSessionData($session);

        return response()->json([
            'success' => true,
            'message' => "Advanced to photos",
            'stage' => 'photos',
            'data' => $sessionData
        ]);
    }

    /**
     * Validate that all tasks in the given stage are completed
     */
    private function validateStageCompletion(CleaningSession $session, string $stage): bool
    {
        $session->loadMissing(['checklistItems']);

        switch ($stage) {
            case 'pre_cleaning':
                $propertyTasks = $session->property->propertyTasks()
                    ->where('phase', 'pre_cleaning')
                    ->pluck('id');

                $completedCount = $session->checklistItems
                    ->whereNull('room_id')
                    ->whereIn('task_id', $propertyTasks)
                    ->where('checked', true)
                    ->count();

                return $completedCount === $propertyTasks->count();

            case 'rooms_first_half':
            case 'rooms_second_half':
                $rooms = $session->property->rooms()
                    ->with(['tasks' => fn($q) => $q->where('type', '!=', 'instructions')])
                    ->orderBy('property_room.sort_order')
                    ->get();

                $totalRooms = $rooms->count();
                $halfPoint = (int) ceil($totalRooms / 2);
                $subset = $stage === 'rooms_first_half'
                    ? $rooms->slice(0, $halfPoint)
                    : $rooms->slice($halfPoint);

                foreach ($subset as $room) {
                    $taskIds = $room->tasks->pluck('id');
                    $completedCount = $session->checklistItems
                        ->where('room_id', $room->id)
                        ->whereIn('task_id', $taskIds)
                        ->where('checked', true)
                        ->count();

                    if ($completedCount < $taskIds->count()) {
                        return false;
                    }
                }
                return true;

            case 'during_cleaning':
                $propertyTasks = $session->property->propertyTasks()
                    ->where('phase', 'during_cleaning')
                    ->pluck('id');

                $completedCount = $session->checklistItems
                    ->whereNull('room_id')
                    ->whereIn('task_id', $propertyTasks)
                    ->where('checked', true)
                    ->count();

                return $completedCount === $propertyTasks->count();

            case 'post_cleaning':
                $propertyTasks = $session->property->propertyTasks()
                    ->where('phase', 'post_cleaning')
                    ->pluck('id');

                $completedCount = $session->checklistItems
                    ->whereNull('room_id')
                    ->whereIn('task_id', $propertyTasks)
                    ->where('checked', true)
                    ->count();

                return $completedCount === $propertyTasks->count();

            case 'photos':
                // Validate that each room has its minimum required photos
                $rooms = $session->property->rooms;
                foreach ($rooms as $room) {
                    $photoCount = $session->photos()->where('room_id', $room->id)->count();
                    $minPhotos = $room->min_photos ?? 2;
                    if ($photoCount < $minPhotos) {
                        return false;
                    }
                }
                return true;

            default:
                return true;
        }
    }

    /**
     * Save a note from the summary stage
     */
    public function saveNote(Request $request, CleaningSession $session)
    {
        try {
            // Authorize - only the assigned housekeeper can save notes
            // if ($session->housekeeper_id !== auth()->id()) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You are not authorized to save notes for this session.'
            //     ], 403);
            // }

            $validated = $request->validate([
                'note' => 'required|string|max:2000'
            ]);

            // Create a report using ChecklistReport model
            $report = ChecklistReport::create([
                'session_id' => $session->id,
                'reported_by' => auth()->id(),
                'type' => 'note',
                'location' => 'summary',
                'description' => $validated['note'],
                'priority' => 'low',
                'status' => 'pending'
            ]);

            // Log the activity
            activity()
                ->performedOn($session)
                ->causedBy(auth()->user())
                ->withProperties(['report_id' => $report->id])
                ->event('note_added')
                ->log('Completion note added');

            return response()->json([
                'success' => true,
                'message' => 'Note saved successfully',
                'report' => $report,
                'redirect' => route('sessions.index')
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save note: ' . $e->getMessage()
            ], 500);
        }
    }
}


