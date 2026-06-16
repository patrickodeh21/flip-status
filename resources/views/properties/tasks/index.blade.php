<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
            <h2 class="text-lg sm:text-xl font-semibold break-words min-w-0 flex-1">
                Tasks — {{ $property->name }} / {{ $room->name }}
            </h2>
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                <x-button variant="secondary" href="{{ route('properties.rooms.index', $property) }}" class="w-full sm:w-auto whitespace-nowrap">← Rooms</x-button>
                @role('admin|owner|company')
                    <x-button variant="secondary" @click="$dispatch('open-preview-panel', 'bulk-add-tasks-{{ $property->id }}-{{ $room->id }}')" class="w-full sm:w-auto whitespace-nowrap">+ Bulk Add Tasks</x-button>
                    <x-button variant="primary" @click="$dispatch('open-preview-panel', 'add-task-{{ $property->id }}-{{ $room->id }}')" class="w-full sm:w-auto whitespace-nowrap">+ Add Task</x-button>
                @endrole
            </div>
        </div>
    </x-slot>

    {{-- Changed Route Name 'properties.tasks.order' -> 'properties.property-tasks.update' --}}
    @php $orderUrl = route('rooms.tasks.order', [$room]); @endphp

    <div x-data="taskList({ orderUrl: @js($orderUrl), csrf: @js(csrf_token()) })" class="space-y-4">
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
                            @role('admin|owner|company')
                                <th class="px-3 py-2 w-10"></th>
                            @endrole
                            <th class="px-4 py-2 text-left">Task</th>
                            <th class="px-4 py-2 text-center">Type</th>
                            <th class="px-4 py-2 text-center">Media</th>
                            @role('admin|owner|company')
                                <th class="px-4 py-2 w-48 text-right">Action</th>
                            @endrole
                        </tr>
                    </thead>
                    <tbody x-ref="tbody" class="divide-y dark:divide-gray-700">
                        @forelse($tasks as $t)
                            <tr data-task-id="{{ $t->id }}" class=" hover:bg-gray-100 dark:hover:bg-gray-900">
                                @role('admin|owner|company')
                                    <td class="px-3">
                                        <button type="button" class="drag-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300" title="Drag">⋮⋮</button>
                                    </td>
                                @endrole
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
                                <td class="px-4 py-3 text-center">
                                    {{ $t->media->count() }}
                                </td>
                                @role('admin|owner|company')
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <button type="button" class="text-indigo-600 hover:underline dark:text-indigo-400"
                                                @click="$dispatch('open-preview-panel', 'edit-task-{{ $property->id }}-{{ $room->id }}-{{ $t->id }}')">Edit</button>
                                        <span class="mx-2 text-gray-400">·</span>
                                        <form action="{{ route('properties.tasks.detach', [$property, $room, $t]) }}" method="post" class="inline">
                                            @csrf @method('DELETE')
                                            <button class="text-rose-600 hover:underline dark:text-rose-400"
                                                    onclick="return confirm('Detach this task from the room?')">Detach</button>
                                        </form>
                                    </td>
                                @endrole
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-10 text-center text-gray-500 dark:text-gray-400" @if(auth()->user()->hasAnyRole(['admin', 'owner'])) colspan="5" @else colspan="4" @endif>No tasks yet</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        @role('admin|owner|company')
            {{-- Mobile Floating Action Button (FAB) --}}
            <div class="fixed bottom-20 right-4 z-40 sm:hidden">
                <button
                    type="button"
                    @click="$dispatch('open-preview-panel', 'add-task-{{ $property->id }}-{{ $room->id }}')"
                    class="flex items-center justify-center w-14 h-14 bg-indigo-600 text-white rounded-full shadow-lg hover:bg-indigo-700 hover:shadow-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    aria-label="Add Task"
                >
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </button>
            </div>
        @endrole
    </div>

    @role('admin|owner|company')
        {{-- Add Task Preview Panel --}}
        <x-preview-panel
            name="add-task-{{ $property->id }}-{{ $room->id }}"
            :overlay="true"
            side="right"
            initialWidth="32rem"
            minWidth="24rem"
            title="Add Task"
            :subtitle="'Add a new task to ' . $room->name . ' in ' . $property->name">

            @php $suggestUrl = route('tasks.suggest'); @endphp

            @include('properties.tasks.__task_form', [
                'property' => $property,
                'room' => $room,
                'task' => null,
                'pivot' => null,
                'suggestUrl' => $suggestUrl,
                'mode' => 'create',
            ])
        </x-preview-panel>

        {{-- Bulk Add Tasks Preview Panel --}}
        <x-preview-panel
            name="bulk-add-tasks-{{ $property->id }}-{{ $room->id }}"
            :overlay="true"
            side="right"
            initialWidth="32rem"
            minWidth="24rem"
            title="Bulk Add Tasks"
            :subtitle="'Quickly add multiple tasks to ' . $room->name . ' in ' . $property->name">

            @include('properties.tasks.__bulk_task_form', [
                'property' => $property,
                'room' => $room,
            ])
        </x-preview-panel>

        {{-- Edit Task Preview Panels --}}
        @foreach($tasks as $t)
            <x-preview-panel
                name="edit-task-{{ $property->id }}-{{ $room->id }}-{{ $t->id }}"
                :overlay="true"
                side="right"
                initialWidth="32rem"
                minWidth="24rem"
                title="Edit Task"
                :subtitle="$t->name">

                @php
                    $suggestUrl = route('tasks.suggest');
                    $pivot = $t->pivot;
                @endphp

                @include('properties.tasks.__task_form', [
                    'property' => $property,
                    'room' => $room,
                    'task' => $t,
                    'pivot' => $pivot,
                    'suggestUrl' => $suggestUrl,
                    'mode' => 'edit',
                ])
            </x-preview-panel>
        @endforeach
    @endrole
</x-app-layout>
