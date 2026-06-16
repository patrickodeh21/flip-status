<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
            <h2 class="text-lg sm:text-xl font-semibold break-words min-w-0 flex-1">
                Tasks — {{ $room->name }}
            </h2>
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                <x-button variant="secondary" href="{{ route('rooms.index') }}" class="w-full sm:w-auto whitespace-nowrap">← Rooms</x-button>
                <x-button variant="secondary" @click="$dispatch('open-preview-panel', 'bulk-add-tasks-{{ $room->id }}')" class="w-full sm:w-auto whitespace-nowrap">
                    + Bulk Add Tasks
                </x-button>
                <x-button variant="primary" @click="$dispatch('open-preview-panel', 'add-task-{{ $room->id }}')" class="w-full sm:w-auto whitespace-nowrap">
                    + Add Task
                </x-button>
            </div>
        </div>
    </x-slot>

    @php $orderUrl = route('rooms.tasks.order', $room); @endphp

    <div @close-preview-panel.window="if ($event.detail.startsWith('edit-task-panel-')) setTimeout(() => { /* cleanup */ }, 300)"
         x-data="taskList({ orderUrl: @js($orderUrl), csrf: @js(csrf_token()) })" class="space-y-4">


        {{-- Forms preloaded directly in panels now --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-3">
            <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">Drag ⋮⋮ to reorder. Auto-saves.</p>
            <div class="min-h-[28px]">
                <span x-show="status==='saving'" x-cloak class="text-xs px-2 py-1 rounded bg-amber-100 text-amber-800 dark:bg-amber-400/20 dark:text-amber-300">Saving…</span>
                <span x-show="status==='saved'" x-cloak class="text-xs px-2 py-1 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-400/20 dark:text-emerald-300">✓ Saved at <span x-text="savedAt"></span></span>
                <span x-show="status==='error'" x-cloak class="text-xs px-2 py-1 rounded bg-rose-100 text-rose-800 dark:bg-rose-400/20 dark:text-rose-300">Failed</span>
            </div>
        </div>

        <x-card class="!px-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="uppercase text-xs tracking-wide sticky top-0 z-10">
                        <tr class="text-gray-600 dark:text-gray-300">
                            <th class="px-3 py-2 w-10"></th>
                            <th class="px-4 py-2 text-left">Task</th>
                            <th class="px-4 py-2 text-center">Type</th>
                            <th class="px-4 py-2">Instructions</th>
                            <th class="px-4 py-2 text-center">Media</th>
                            <th class="px-4 py-2 w-48 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody x-ref="tbody" class="divide-y dark:divide-gray-700">
                        @if($tasks->isEmpty())
                            <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">No tasks yet</td></tr>
                        @else
                            @foreach ($tasks as $t)
                                <tr data-task-id="{{ $t->id }}" class=" hover:bg-gray-100 dark:hover:bg-gray-900">
                                    <td class="px-3">
                                        <button type="button" class="drag-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300" title="Drag">⋮⋮</button>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $t->name }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="text-xs px-2 py-0.5 rounded
                                            {{ ([
                                                'inventory' => 'bg-blue-100 text-blue-800 dark:bg-blue-400/20 dark:text-blue-300',
                                                'verify' => 'bg-amber-100 text-amber-800 dark:bg-amber-400/20 dark:text-amber-300',
                                                'instructions' => 'bg-slate-100 text-slate-800 dark:bg-slate-400/20 dark:text-slate-300',
                                            ][$t->type] ?? 'bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-300') }}">
                                            {{ ucfirst($t->type) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300 max-w-lg">
                                        {{ \Illuminate\Support\Str::limit(optional($t->pivot)->instructions, 120) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        {{ $t->media->count() }}
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <a class="text-indigo-600 hover:underline dark:text-indigo-400"
                                           href="{{ route('rooms.tasks.edit', [$room, $t]) }}"
                                           @click.prevent="window.innerWidth < 768 ? window.location.href = '{{ url('rooms') }}/{{ $room->id }}/tasks/{{ $t->id }}/edit' : $dispatch('open-preview-panel', 'edit-task-panel-{{ $t->id }}')">Edit</a>
                                        <span class="mx-2 text-gray-400">·</span>
                                        <form action="{{ route('rooms.tasks.detach', [$room, $t]) }}" method="post" class="inline">
                                            @csrf @method('DELETE')
                                            <button class="text-rose-600 hover:underline dark:text-rose-400"
                                                    onclick="return confirm('Detach this task from the room?')">Detach</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    {{-- Add Task Sidebar Panel --}}
    <x-preview-panel
        name="add-task-{{ $room->id }}"
        :overlay="true"
        side="right"
        initialWidth="32rem"
        minWidth="24rem"
        title="Add Task"
        :subtitle="'Add a new task to ' . $room->name">

        @php $suggestUrl = route('tasks.suggest'); @endphp

        @include('rooms.tasks.__task_form', [
            'room' => $room,
            'suggestUrl' => $suggestUrl,
        ])
    </x-preview-panel>

    {{-- Bulk Add Tasks Sidebar Panel --}}
    <x-preview-panel
        name="bulk-add-tasks-{{ $room->id }}"
        :overlay="true"
        side="right"
        initialWidth="32rem"
        minWidth="24rem"
        title="Bulk Add Tasks"
        :subtitle="'Quickly add multiple tasks to ' . $room->name">

        @include('rooms.tasks.__bulk_task_form', [
            'room' => $room,
        ])
    </x-preview-panel>

    {{-- Reusable Edit Panel Container --}}
    @foreach ($tasks as $t)
        <x-preview-panel
            name="edit-task-panel-{{ $t->id }}"
            :overlay="true"
            side="right"
            initialWidth="32rem"
            minWidth="24rem"
            title="Edit Task"
            :subtitle="'Updating task in ' . $room->name">
            @include('rooms.tasks.__edit_partial', ['room' => $room, 'task' => $t, 'pivot' => $t->pivot])
        </x-preview-panel>
    @endforeach
</x-app-layout>
