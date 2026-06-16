<?php

namespace App\Http\Controllers;

use App\Http\Requests\PropertyStoreRequest;
use App\Models\Property;
use App\Models\Room;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;


class PropertyController extends Controller
{


    public function index(Request $request)
    {
        $authenticatedUser = $request->user();
        $searchTerm        = (string) $request->query('q', '');

        $propertyQuery = Property::query();

        if ($authenticatedUser->hasRole('admin')) {
            // Admin can see all properties, no extra constraints
        } elseif ($authenticatedUser->hasRole('owner')) {
            $propertyQuery->where('owner_id', $authenticatedUser->id);
        } elseif ($authenticatedUser->hasRole('company')) {
            $propertyQuery->where(function ($q) use ($authenticatedUser) {
                $q->where('owner_id', $authenticatedUser->id)
                    ->orWhereIn('owner_id', function ($sub) use ($authenticatedUser) {
                        // Owners direct-child of company
                        $sub->select('id')->from('users')->where('owner_id', $authenticatedUser->id);
                    })
                    ->orWhereIn('owner_id', function ($sub) use ($authenticatedUser) {
                        // Owners assigned via pivot
                        $sub->select('owner_id')
                            ->from('housekeeper_owner')
                            ->where('housekeeper_id', $authenticatedUser->id);
                    });
            });
        } elseif ($authenticatedUser->hasRole('housekeeper')) {
            $propertyQuery->whereIn('id', function ($subQuery) use ($authenticatedUser) {
                $subQuery->select('property_id')
                    ->from('cleaning_sessions')
                    ->where('housekeeper_id', $authenticatedUser->id);
            });
        }

        $properties = $propertyQuery
            ->when($searchTerm !== '', fn($query) => $query->where('name', 'like', "%{$searchTerm}%"))
            ->when($request->owner_id, fn($query) => $query->where('owner_id', $request->owner_id))
            ->with(['owner.roles', 'rooms'])
            ->when(
                $authenticatedUser?->hasAnyRole(['admin', 'owner', 'company']),
                fn($query) => $query->with('propertyTasks')
            )
            ->withCount('rooms')
            ->orderBy('name')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $ownersResourceQuery = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['owner', 'company']);
        });

        if ($authenticatedUser->hasRole('company') && !$authenticatedUser->hasRole('admin')) {
            $ownersResourceQuery->where(function ($q) use ($authenticatedUser) {
                $q->where('id', $authenticatedUser->id)
                    ->orWhere('owner_id', $authenticatedUser->id)
                    ->orWhereIn('id', function ($sub) use ($authenticatedUser) {
                        $sub->select('owner_id')
                            ->from('housekeeper_owner')
                            ->where('housekeeper_id', $authenticatedUser->id);
                    });
            });
        } elseif (!$authenticatedUser->hasRole('admin')) {
            $ownersResourceQuery->where('id', $authenticatedUser->id);
        }

        $owners = $ownersResourceQuery->with('roles')->orderBy('name')->get();

        $rooms = Room::select('id', 'name', 'is_default')
            ->where('is_default', true)
            ->orderBy('name')
            ->get();

        return view('properties.index', compact('properties', 'owners', 'rooms'));
    }


    public function create(Request $request)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can create properties.');

        $user = $request->user();
        $ownerRoleScope = $user->hasRole('admin')
            ? ['admin', 'owner', 'company']
            : ['owner', 'company'];

        $ownerSelectQuery = User::role($ownerRoleScope);

        if ($user->hasRole('company') && !$user->hasRole('admin')) {
            $ownerSelectQuery->where(function ($q) use ($user) {
                $q->where('id', $user->id)
                    ->orWhere('owner_id', $user->id)
                    ->orWhereIn('id', function ($sub) use ($user) {
                        $sub->select('owner_id')
                            ->from('housekeeper_owner')
                            ->where('housekeeper_id', $user->id);
                    });
            });
        } elseif (!$user->hasRole('admin')) {
            $ownerSelectQuery->where('id', $user->id);
        }

        $owners = $ownerSelectQuery->orderBy('name')->pluck('name', 'id')->all();
        $defaultOwnerId = old('owner_id', $user->hasRole('admin') ? $user->id : null);

        return view('properties.create', [
            'owners' => $owners,
            'defaultOwnerId' => $defaultOwnerId,
            "rooms" => Room::where('is_default', true)->get()
        ]);
    }

    public function store(PropertyStoreRequest $request)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can create properties.');

        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('properties', 'public');
        }

        $attach = $request->input('attach', 'none');

        DB::transaction(function () use ($data, $attach) {
            /** @var Property $property */
            $property = Property::create($data);

            if ($attach !== 'rooms') return;

            // -------- Attach DEFAULT ROOMS (Isolated Clone) --------
            $defaultRooms = Room::with('tasks')
                ->where('is_default', true)
                ->orderBy('name')
                ->get();

            if ($defaultRooms->isNotEmpty()) {
                $nextOrder = (int) $property->rooms()->max('property_room.sort_order');
                $nextOrder = $nextOrder ? $nextOrder + 1 : 1;

                foreach ($defaultRooms as $r) {
                    $r->cloneForProperty($property, $nextOrder++);
                }
            }
        });

        return redirect()
            ->route('properties.index')
            ->with('ok', match ($attach) {
                'rooms'        => 'Property created and default rooms assigned.',
                default        => 'Property created successfully.',
            });
    }

    public function edit(Request $request, Property $property)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can edit properties.');

        $user = $request->user();
        $ownerRoleScope = $user->hasRole('admin')
            ? ['admin', 'owner', 'company']
            : ['owner', 'company'];

        $ownerSelectQuery = User::role($ownerRoleScope);

        if ($user->hasRole('company') && !$user->hasRole('admin')) {
            $ownerSelectQuery->where(function ($q) use ($user) {
                $q->where('id', $user->id)
                    ->orWhere('owner_id', $user->id);
            });
        } elseif (!$user->hasRole('admin')) {
            $ownerSelectQuery->where('id', $user->id);
        }

        return view('properties.edit', [
            'property' => $property,
            'owners' => $ownerSelectQuery->orderBy('name')->pluck('name', 'id')->all()
        ]);
    }

    public function update(Request $request, Property $property)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can update properties.');

        $user = $request->user();
        $isAdmin = $user->hasRole('admin');

        $rules = [
            'name'         => ['required', 'string', 'max:255'],
            'address'      => ['nullable', 'string', 'max:255'],
            'latitude'     => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'    => ['nullable', 'numeric', 'between:-180,180'],
            'geo_radius_m' => ['nullable', 'integer', 'min:50'],
            'photo'        => ['nullable', 'image', 'max:5120'],
            'ical_url'     => ['nullable', 'url', 'max:1000'],
            'airbnb_ical_url' => ['nullable', 'url', 'max:1000'],
            'vrbo_ical_url'   => ['nullable', 'url', 'max:1000'],
            'timezone'     => ['required', 'string', 'max:10'],
            'remove_photo' => ['sometimes', 'boolean'],
        ];

        if ($isAdmin) {
            // Admin can reassign owner
            $rules['owner_id'] = ['required', Rule::exists('users', 'id')];
        } elseif ($user->hasRole('company')) {
            // Company can reassign to themselves or managed owners
            $managedOwners = User::where('owner_id', $user->id)->pluck('id')->toArray();
            $allowedIds = array_merge([$user->id], $managedOwners);
            $rules['owner_id'] = ['required', 'integer', Rule::in($allowedIds)];
        } else {
            // Regular owners check
            // If the request contains owner_id, it must match strict ownership or be ignored/unset before valid.
            // But validation runs before we can unset.
            // If we want to allow the form to send unchanged owner_id, we can validate it matches auth->id
            $rules['owner_id'] = ['integer', Rule::in([$user->id])];
        }

        $data = $request->validate($rules);

        if (!$isAdmin && !$user->hasRole('company')) {
            // Guardrail: keep property with the same owner
            if ($property->owner_id !== $user->id) {
                abort(403, 'You cannot modify properties you do not own.');
            }
            // We validated it matches user->id, so it doesn't change ownership.
            // But let's unset it just to be safe and clean.
            unset($data['owner_id']);
        } elseif ($user->hasRole('company')) {
            // Company guardrail: check if property is currently owned by company or its managed owner
            $isAuthorized = ($property->owner_id === $user->id) ||
                User::where('id', $property->owner_id)->where('owner_id', $user->id)->exists();

            if (!$isAuthorized) {
                abort(403, 'You cannot modify properties you do not manage.');
            }
        }

        // Remove existing photo if requested
        if ($request->boolean('remove_photo') && $property->photo_path) {
            Storage::disk('public')->delete($property->photo_path);
            $data['photo_path'] = null;
        }

        // Replace with newly uploaded photo
        if ($request->hasFile('photo')) {
            if ($property->photo_path) {
                Storage::disk('public')->delete($property->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('properties', 'public');
        }

        $property->update($data);

        return redirect()
            ->route('properties.index')
            ->with('ok', 'Property updated successfully.');
    }

    public function destroy(Request $request, Property $property)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can delete properties.');

        $property->delete();

        return redirect()->route('properties.index')->with('ok', 'Property deleted.');
    }



    public function rooms(Property $property)
    {

        $rooms = $property->rooms()
            ->withCount('tasks')
            ->orderBy('property_room.sort_order')
            ->get();

        return view('properties.rooms.index', [
            'property'    => $property,
            'rooms'       => $rooms,
            'navProperty' => $property,
        ]);
    }



    public function updateRoom(Request $request, Property $property, Room $room)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can update rooms.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'min_photos' => ['required', 'integer', 'min:0', 'max:50'],
        ]);

        $newName = trim($data['name']);

        // Since the room is now exclusively cloned for this property, we just rename it directly.
        $room->name = $newName;
        $room->min_photos = (int) $data['min_photos'];
        
        if ($request->user()->hasRole('admin')) {
            $room->is_default = $request->boolean('is_default');
        }
        
        $room->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => "Updated room: {$room->name}",
                'room' => $room,
            ]);
        }

        return redirect()->route('properties.rooms.index', $property)
            ->with('status', "Updated room: {$room->name}");
    }

    public function destroyRoom(Request $request, Property $property, Room $room)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can detach rooms.');

        // Detach room from property
        $property->rooms()->detach($room->id);

        return redirect()->route('properties.rooms.index', $property)
            ->with('status', 'Room detached from property.');
    }




    public function tasks(Property $property, Room $room)
    {
        abort_unless($property->rooms()->where('rooms.id', $room->id)->exists(), 403, 'Room does not belong to the specified property.');

        $tasks = $room->tasks()
            ->with(['media' => fn($q) => $q->orderBy('sort_order')])
            ->orderBy('room_task.sort_order')
            ->get();


        return view('properties.tasks.index', [
            'property'    => $property,
            'room'        => $room,
            'tasks'       => $tasks,
            'navProperty' => $property,
        ]);
    }


    public function storeTask(Request $request, Property $property, Room $room)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can add tasks.');

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:160'],
            'type'         => ['required', Rule::in(['room', 'inventory', 'verify', 'instructions'])],
            'is_sporadic'  => ['nullable', 'boolean'],
            'is_default'   => ['nullable', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'visible_to_owner'       => ['nullable', 'boolean'],
            'visible_to_housekeeper' => ['nullable', 'boolean'],
            'media.*'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,avi', 'max:20480'], // 20MB
            'captions.*'   => ['nullable', 'string', 'max:255'],
        ]);

        // Always create a new independent task for this property's room
        $name = trim($validated['name']);
        $taskData = [
            'name'       => $name,
            'type'       => $validated['type'],
            'is_sporadic' => (bool)($validated['is_sporadic'] ?? false),
            'is_default'  => false, // Strictly isolate: Property-specific tasks can never be global defaults
        ];

        $task = Task::create($taskData);

        // attach to room with next sort + pivot fields
        $nextOrder = (int)$room->tasks()->max('room_task.sort_order') + 1;
        $room->tasks()->attach($task->id, [
            'sort_order' => $nextOrder,
            'instructions' => $validated['instructions'] ?? null,
            'visible_to_owner' => (bool)($validated['visible_to_owner'] ?? true),
            'visible_to_housekeeper' => (bool)($validated['visible_to_housekeeper'] ?? true),
        ]);

        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $i => $file) {
                if (!$file) continue;

                $path = $file->store('task-media', 'public'); // e.g. "task-media/abc123.jpg"
                $mime = $file->getMimeType();
                $type = str_starts_with($mime, 'video') ? 'video' : 'image';

                $task->media()->create([
                    'type'       => $type,
                    'url'        => $path,                         // <-- store path only
                    'thumbnail'  => $type === 'image' ? $path : null, // <-- path only
                    'caption'    => $request->input("captions.$i"),
                    'sort_order' => $i + 1,
                ]);
            }
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => "Task attached: {$task->name}",
                'task' => $task->load('media'),
            ]);
        }

        return redirect()->route('properties.tasks.index', [$property, $room])
            ->with('status', "Task attached: {$task->name}");
    }

    public function bulkStoreTask(Request $request, Property $property, Room $room)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can bulk add tasks.');

        $validated = $request->validate([
            'tasks' => ['required', 'string'], // JSON string
            'default_type' => ['required', Rule::in(['room', 'inventory', 'verify', 'instructions'])],
            'default_is_sporadic' => ['nullable', 'boolean'],
        ]);

        $taskNames = json_decode($validated['tasks'], true);
        if (!is_array($taskNames) || empty($taskNames)) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['message' => 'Invalid tasks data'], 422);
            }
            return redirect()->back()->withErrors(['tasks' => 'Invalid tasks data']);
        }

        $created = 0;
        $skipped = 0;
        $defaultType = $validated['default_type'];
        $nextOrder = (int)$room->tasks()->max('room_task.sort_order') + 1;

        DB::beginTransaction();
        try {
            foreach ($taskNames as $taskName) {
                $taskName = trim($taskName);
                if (empty($taskName)) {
                    $skipped++;
                    continue;
                }

                // Always create a new independent task for this property's room
                $task = Task::create([
                    'name' => $taskName,
                    'type' => $defaultType,
                    'is_sporadic' => (bool) ($validated['default_is_sporadic'] ?? false),
                    'is_default' => false,
                ]);

                $room->tasks()->attach($task->id, [
                    'sort_order' => $nextOrder++,
                    'instructions' => null,
                    'visible_to_owner' => true,
                    'visible_to_housekeeper' => true,
                ]);
                $created++;
            }

            DB::commit();

            $message = "Successfully created {$created} task(s)";
            if ($skipped > 0) {
                $message .= " ({$skipped} skipped - already exist)";
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'message' => $message,
                    'created' => $created,
                    'skipped' => $skipped,
                ]);
            }

            return redirect()->route('properties.tasks.index', [$property, $room])
                ->with('status', $message);
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Failed to create tasks: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->withErrors(['error' => 'Failed to create tasks. Please try again.']);
        }
    }

    public function updateTask(Request $request, Property $property, Room $room, Task $task)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can update tasks.');
        abort_unless($property->rooms()->where('rooms.id', $room->id)->exists(), 403, 'Room does not belong to the specified property.');
        abort_unless($room->tasks()->where('tasks.id', $task->id)->exists(), 404, 'Task not found in the specified room.');

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:160'],
            'type'         => ['required', Rule::in(['room', 'inventory', 'verify', 'instructions'])],
            'is_sporadic'  => ['nullable', 'boolean'],
            'is_default'   => ['nullable', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'visible_to_owner'       => ['nullable', 'boolean'],
            'visible_to_housekeeper' => ['nullable', 'boolean'],
            'media.*'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,avi', 'max:20480'], // 20MB
            'captions.*'   => ['nullable', 'string', 'max:255'],
        ]);

        $newName = trim($data['name']);

        $currentSort = (int) $room->tasks()->where('tasks.id', $task->id)->first()->pivot->sort_order ?? 0;

        // Update this property's own task record directly (no sharing)
        $updateData = [
            'name' => $newName, 
            'type' => $data['type'],
            'is_sporadic' => $request->boolean('is_sporadic'),
            'is_default' => false, // Strictly isolate: Property-specific tasks can never become global defaults
        ];

        $task->update($updateData);
        $room->tasks()->updateExistingPivot($task->id, [
            'instructions' => $data['instructions'] ?? null,
            'visible_to_owner' => (bool)($data['visible_to_owner'] ?? true),
            'visible_to_housekeeper' => (bool)($data['visible_to_housekeeper'] ?? true),
        ]);

        // Process new media uploads
        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $i => $file) {
                if (!$file) continue;

                $path = $file->store('task-media', 'public');
                $mime = $file->getMimeType();
                $type = str_starts_with($mime, 'video') ? 'video' : 'image';

                // Append new media to the end
                $nextSort = (int) $task->media()->max('sort_order') + 1;

                $task->media()->create([
                    'type'       => $type,
                    'url'        => $path,
                    'thumbnail'  => $type === 'image' ? $path : null,
                    'caption'    => $request->input("captions.$i"),
                    'sort_order' => $nextSort,
                ]);
            }
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => "Task updated: {$task->name}",
                'task' => $task->load('media'),
            ]);
        }

        return redirect()->route('properties.tasks.index', [$property, $room])
            ->with('status', "Task updated: {$task->name}");
    }



    public function detachTask(Request $request, Property $property, Room $room, Task $task)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can detach tasks.');

        $taskName = $task->name;
        $room->tasks()->detach($task->id);
        $task->delete(); // Each task is now independent per room, safe to delete
        return redirect()->route('properties.tasks.index', [$property, $room])
            ->with('status', "Removed task: {$taskName}");
    }

    // Property-level tasks (not room-specific)
    public function propertyTasks(Request $request, Property $property)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can manage property tasks.');

        $property->load(['propertyTasks.media' => fn($q) => $q->orderBy('sort_order')]);

        return view('properties.property-tasks.index', [
            'property' => $property,
            'navProperty' => $property,
        ]);
    }

    public function storePropertyTask(Request $request, Property $property)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can add property tasks.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(['room', 'inventory', 'verify', 'instructions'])],
            'phase' => ['required', Rule::in(['pre_cleaning', 'during_cleaning', 'post_cleaning'])],
            'is_sporadic' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'visible_to_owner' => ['nullable', 'boolean'],
            'visible_to_housekeeper' => ['nullable', 'boolean'],
            'media.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,avi', 'max:20480'],
            'captions.*' => ['nullable', 'string', 'max:255'],
        ]);

        // Always create a new independent task for this property
        $name = trim($validated['name']);
        $task = Task::create([
            'name' => $name,
            'type' => $validated['type'],
            'phase' => $validated['phase'],
            'is_sporadic' => (bool)($validated['is_sporadic'] ?? false),
            'is_default' => $request->user()->hasRole('admin') ? $request->boolean('is_default') : false,
        ]);

        // Attach to property with next sort order
        $nextOrder = (int) $property->propertyTasks()->max('property_tasks.sort_order') + 1;
        $property->propertyTasks()->attach($task->id, [
            'sort_order' => $nextOrder,
            'instructions' => $validated['instructions'] ?? null,
            'visible_to_owner' => (bool)($validated['visible_to_owner'] ?? true),
            'visible_to_housekeeper' => (bool)($validated['visible_to_housekeeper'] ?? true),
        ]);

        // Process media uploads
        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $i => $file) {
                if (!$file) continue;

                $path = $file->store('task-media', 'public');
                $mime = $file->getMimeType();
                $type = str_starts_with($mime, 'video') ? 'video' : 'image';

                $nextSort = (int) $task->media()->max('sort_order') + 1;

                $task->media()->create([
                    'type'       => $type,
                    'url'        => $path,
                    'thumbnail'  => $type === 'image' ? $path : null,
                    'caption'    => $request->input("captions.$i"),
                    'sort_order' => $nextSort,
                ]);
            }
        }

        $message = "Property task added: {$task->name}";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => $message,
                'task' => $task->load('media'),
            ]);
        }

        return redirect()->route('properties.property-tasks.index', $property)
            ->with('status', $message);
    }

    public function updatePropertyTask(Request $request, Property $property, Task $task)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can update property tasks.');
        abort_unless($property->propertyTasks()->where('tasks.id', $task->id)->exists(), 404, 'Task not found for this property.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(['room', 'inventory', 'verify', 'instructions'])],
            'phase' => ['required', Rule::in(['pre_cleaning', 'during_cleaning', 'post_cleaning'])],
            'is_sporadic' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'visible_to_owner' => ['nullable', 'boolean'],
            'visible_to_housekeeper' => ['nullable', 'boolean'],
            'media.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,avi', 'max:20480'],
            'captions.*' => ['nullable', 'string', 'max:255'],
        ]);

        $newName = trim($validated['name']);

        // Update task fields
        $taskUpdateData = [
            'name' => $newName,
            'type' => $validated['type'],
            'phase' => $validated['phase'],
            'is_sporadic' => $request->boolean('is_sporadic'),
        ];
        
        if ($request->user()->hasRole('admin')) {
            $taskUpdateData['is_default'] = $request->boolean('is_default');
        }

        $task->update($taskUpdateData);

        // Update pivot data
        $property->propertyTasks()->updateExistingPivot($task->id, [
            'instructions' => $validated['instructions'] ?? null,
            'visible_to_owner' => (bool)($validated['visible_to_owner'] ?? true),
            'visible_to_housekeeper' => (bool)($validated['visible_to_housekeeper'] ?? true),
        ]);

        // Process new media uploads
        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $i => $file) {
                if (!$file) continue;

                $path = $file->store('task-media', 'public');
                $mime = $file->getMimeType();
                $type = str_starts_with($mime, 'video') ? 'video' : 'image';

                $nextSort = (int) $task->media()->max('sort_order') + 1;

                $task->media()->create([
                    'type'       => $type,
                    'url'        => $path,
                    'thumbnail'  => $type === 'image' ? $path : null,
                    'caption'    => $request->input("captions.$i"),
                    'sort_order' => $nextSort,
                ]);
            }
        }

        $message = "Property task updated: {$task->name}";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => $message,
                'task' => $task->load('media'),
            ]);
        }

        return redirect()->route('properties.property-tasks.index', $property)
            ->with('status', $message);
    }

    public function detachPropertyTask(Request $request, Property $property, Task $task)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can detach property tasks.');

        $taskName = $task->name;
        $property->propertyTasks()->detach($task->id);
        $task->delete(); // Each task is now independent per property, safe to delete

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'message' => "Removed property task: {$taskName}",
            ]);
        }

        return redirect()->route('properties.property-tasks.index', $property)
            ->with('status', "Removed property task: {$taskName}");
    }
}
