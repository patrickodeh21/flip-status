<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    /**
     * GET /tasks
     * List all tasks (global), optional search & type filter.
     */
    public function index(Request $request)
    {
        $q    = trim((string) $request->query('q', ''));
        $type = $request->query('type');

        $tasks = Task::query()
            ->when($q !== '', fn($qq) => $qq->where('name', 'like', "%{$q}%"))
            ->when(in_array($type, ['room', 'inventory', 'verify', 'instructions'], true), fn($qq) => $qq->where('type', $type))
            ->when($request->room_id ?? false, fn($query) => $query->whereHas('rooms', fn($query) => $query->where('rooms.id', $request->room_id)))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('tasks.index', compact('tasks', 'q', 'type'));
    }

    /**
     * GET /tasks/create
     */
    public function create(Request $request)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can create tasks.');
        
        return view('tasks.create');
    }

    /**
     * POST /tasks
     * Create a global task template.
     */
    public function store(Request $request)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can create tasks.');
        
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255', Rule::unique('tasks', 'name')],
            'type'       => ['required', Rule::in(['room', 'inventory', 'verify', 'instructions'])],
            'is_default' => ['nullable', 'boolean'],
            'is_sporadic' => ['nullable', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:20000'],

        ]);

        $task = Task::create([
            'name'       => $data['name'],
            'type'       => $data['type'],
            'is_default' => $request->user()->hasRole('admin') ? (bool) ($data['is_default'] ?? false) : false,
            'is_sporadic' => (bool) ($data['is_sporadic'] ?? false),
            'instructions' => $data['instructions'] ?? null,
        ]);

        // Handle Media Uploads
        if ($request->hasFile('media')) {
            $files = collect($request->file('media', []))->filter();
            $captions = $request->input('captions', []);

            foreach ($files as $i => $file) {
                $path = $file->store('task-media', 'public');
                $mime = $file->getMimeType() ?? '';
                $type = str_starts_with($mime, 'video') ? 'video' : 'image';

                $task->media()->create([
                    'type'       => $type,
                    'url'        => $path,
                    'thumbnail'  => $type === 'image' ? $path : null, // Simplification for now
                    'caption'    => $captions[$i] ?? null,
                    'sort_order' => $i + 1,
                ]);
            }
        }

        return redirect()->route('tasks.index')->with('ok', 'Task created.');
    }

    /**
     * GET /tasks/{task}
     * Show a task and where it's used (rooms count/list).
     */
    public function show(Task $task)
    {
        $task->loadCount('rooms');
        $rooms = $task->rooms()
            ->withPivot(['sort_order', 'instructions', 'visible_to_owner', 'visible_to_housekeeper'])
            ->with('properties:id,name') // if you want to show which properties include those rooms
            ->orderBy('room_task.sort_order')
            ->orderBy('rooms.name')
            ->paginate(20);

        return view('tasks.show', compact('task', 'rooms'));
    }

    /**
     * GET /tasks/{task}/edit
     */
    public function edit(Request $request, Task $task)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can edit tasks.');
        
        if ($request->ajax() || $request->wantsJson() || $request->hasHeader('X-Requested-With')) {
            return view('tasks.edit-partial', compact('task'));
        }

        return view('tasks.edit', compact('task'));
    }

    /**
     * PUT/PATCH /tasks/{task}
     */
    public function update(Request $request, Task $task)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can update tasks.');
        
        $data = $request->validate([
            'name'       => [
                'required',
                'string',
                'max:255',
                Rule::unique('tasks', 'name')
                ->ignore($task->id)
                ->where(function ($query) use ($request) {
                $query->where('type', $request->input('type'));
            }),
            ],
            'type'       => ['required', Rule::in(['room', 'inventory', 'verify', 'instructions'])],
            'is_default' => ['nullable', 'boolean'],
            'is_sporadic' => ['nullable', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:20000'],
        ]);

        $updateData = [
            'name'       => $data['name'],
            'type'       => $data['type'],
            'is_sporadic' => $request->boolean('is_sporadic'),
            'instructions' => $data['instructions'] ?? null,
        ];
        
        if ($request->user()->hasRole('admin')) {
             $updateData['is_default'] = $request->boolean('is_default');
        }

        $task->update($updateData);

        // Handle New Media Uploads
        if ($request->hasFile('media')) {
            $files = collect($request->file('media', []))->filter();
            $captions = $request->input('captions', []);
            $startOrder = (int) ($task->media()->max('sort_order') ?? 0);

            foreach ($files as $i => $file) {
                $path = $file->store('task-media', 'public');
                $mime = $file->getMimeType() ?? '';
                $type = str_starts_with($mime, 'video') ? 'video' : 'image';

                $task->media()->create([
                    'type'       => $type,
                    'url'        => $path,
                    'thumbnail'  => $type === 'image' ? $path : null,
                    'caption'    => $captions[$i] ?? null,
                    'sort_order' => $startOrder + $i + 1,
                ]);
            }
        }

        return redirect()->route('tasks.index')->with('ok', 'Task updated.');
    }

    /**
     * DELETE /tasks/{task}
     */
    public function destroy(Request $request, Task $task)
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'owner', 'company']), 403, 'Only administrators, owners, and companies can delete tasks.');
        
        // If you want to prevent deletion when attached to rooms, guard here:
        // if ($task->rooms()->exists()) { return back()->with('error', 'Task is in use. Detach first.'); }

        $task->delete();
        return redirect()->route('tasks.index')->with('ok', 'Task deleted.');
    }

    /**
     * GET /tasks/suggest?q=...&type=room|inventory
     * Lightweight autocomplete endpoint (used by your forms).
     */
    public function suggest(Request $request)
    {
        $q    = trim((string) $request->query('q', ''));
        $type = $request->query('type');

        $tasks = Task::query()
            ->where('is_default', true)
            ->when($q !== '', fn($qq) => $qq->where('name', 'like', "%{$q}%"))
            ->when(in_array($type, ['room', 'inventory', 'verify', 'instructions'], true), fn($qq) => $qq->where('type', $type))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'type']);

        return response()->json($tasks);
    }

    // ------------------------------------------------------------------
    // OPTIONAL: room <-> task management (not part of core task CRUD)
    // Keep these if you still need to attach/detach/reorder on room pages.
    // ------------------------------------------------------------------

    /**
     * POST /rooms/{room}/tasks/attach
     * Body: task_id OR name+type to create+attach, plus optional pivot fields.
     */
    public function attachToRoom(Request $request, Room $room)
    {
        $data = $request->validate([
            'task_id'                => ['nullable', 'integer', 'exists:tasks,id'],
            'name'                   => ['required_without:task_id', 'string', 'max:255'],
            'type'                   => ['required_without:task_id', Rule::in(['room', 'inventory', 'verify', 'instructions'])],
            'instructions'           => ['nullable', 'string', 'max:2000'],
            'visible_to_owner'       => ['nullable', 'boolean'],
            'visible_to_housekeeper' => ['nullable', 'boolean'],
        ]);

        $task = isset($data['task_id'])
            ? Task::findOrFail($data['task_id'])
            : Task::create([
                'name' => $data['name'],
                'type' => $data['type'],
                'is_default' => false
            ]);

        $nextOrder = (int) $room->tasks()->max('room_task.sort_order');
        $nextOrder = $nextOrder > 0 ? $nextOrder + 1 : 1;

        $room->tasks()->syncWithoutDetaching([
            $task->id => [
                'sort_order'            => $nextOrder,
                'instructions'          => $data['instructions'] ?? null,
                'visible_to_owner'      => (bool) ($data['visible_to_owner'] ?? true),
                'visible_to_housekeeper' => (bool) ($data['visible_to_housekeeper'] ?? true),
            ],
        ]);

        return back()->with('ok', 'Task attached to room.');
    }

    /**
     * DELETE /rooms/{room}/tasks/{task}
     * Detach task from a room.
     */
    public function detachFromRoom(Room $room, Task $task)
    {
        abort_unless($room->tasks()->where('tasks.id', $task->id)->exists(), 404);
        $room->tasks()->detach($task->id);
        return back()->with('ok', 'Task detached from room.');
    }

    /**
     * POST /rooms/{room}/tasks/reorder
     * Body: { order: [taskId1, taskId2, ...] }
     */
    public function reorderForRoom(Request $request, Room $room)
    {
        $data = $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer', 'exists:tasks,id'],
        ]);

        DB::transaction(function () use ($room, $data) {
            $ord = 1;
            foreach ($data['order'] as $taskId) {
                if ($room->tasks()->where('tasks.id', $taskId)->exists()) {
                    $room->tasks()->updateExistingPivot($taskId, ['sort_order' => $ord++]);
                }
            }
        });

        return response()->json(['ok' => true]);
    }
}
