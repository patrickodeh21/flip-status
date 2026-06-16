@php
    $roomsForJs = $property->rooms
        ->map(fn($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'sort_order' => (int) ($r->pivot->sort_order ?? 0),
        ])
        ->sortBy('sort_order')
        ->values();

    $tasksForJs = $property->propertyTasks
        ->map(fn($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'phase' => $t->phase,
            'sort_order' => (int) ($t->pivot->sort_order ?? 0),
        ])
        ->sortBy('sort_order')
        ->values();
@endphp

<x-preview-panel name="duplicate-property-{{ $property->id }}" :overlay="true" side="right" initialWidth="34rem"
    minWidth="22rem" title="Duplicate Property" subtitle="Create a new property from this one and choose what to copy">
    <form id="duplicate-property-form-{{ $property->id }}" method="POST"
          action="{{ route('properties.duplicate', $property) }}" class="h-full">
        @csrf

        <div class="p-4 flex flex-col text-gray-800 dark:text-gray-100"
            x-data="duplicatePropertyPanel({
                initialName: @js($property->name . ' (Copy)'),
                rooms: @js($roomsForJs),
                tasks: @js($tasksForJs),
                selectedRoomIds: @js($roomsForJs->pluck('id')->all()),
                selectedTaskIds: @js($tasksForJs->pluck('id')->all()),
            })">

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">New property name</label>
                    <input name="name" x-model="newName" required
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700
                               bg-white dark:bg-gray-800 placeholder:text-gray-400 dark:placeholder:text-gray-500
                               focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="e.g. {{ $property->name }} (Copy)">
                    @error('name')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-3 bg-white dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <div class="text-sm font-semibold">Rooms to copy</div>
                        <span
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-gray-100 text-[11px]
                                   text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span x-text="selectedRoomIds.length + ' selected'"></span>
                        </span>
                    </div>

                    <div class="flex items-center justify-between gap-2 mb-2">
                        <input type="text" x-model="roomSearch" placeholder="Search rooms…"
                            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700
                                   bg-white dark:bg-gray-800 placeholder:text-gray-400 dark:placeholder:text-gray-500
                                   focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mb-3">
                        <button type="button"
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-gray-100 hover:bg-gray-200
                                   dark:bg-gray-800 dark:hover:bg-gray-700"
                            @click="selectAllRooms()">
                            Select visible
                        </button>
                        <button type="button"
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-transparent hover:bg-gray-100/60
                                   dark:hover:bg-gray-800/60"
                            @click="clearRooms()">
                            Clear
                        </button>
                    </div>

                    <div class="space-y-2 max-h-56 overflow-y-auto px-1">
                        <template x-if="!filteredRooms.length">
                            <p class="text-xs text-gray-500 dark:text-gray-400">No rooms found.</p>
                        </template>

                        <template x-for="room in filteredRooms" :key="room.id">
                            <button type="button"
                                class="w-full text-left group rounded-xl border px-3 py-2.5 transition
                                       flex items-center justify-between gap-3
                                       border-gray-200 bg-white hover:bg-indigo-50/70
                                       dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800"
                                :class="isRoomSelected(room.id) ? 'ring-2 ring-inset ring-indigo-500 border-indigo-400' : ''"
                                @click="toggleRoom(room.id)">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-lg text-xs font-semibold
                                               bg-indigo-50 text-indigo-700 group-hover:bg-indigo-100
                                               dark:bg-indigo-900/40 dark:text-indigo-200 dark:group-hover:bg-indigo-900/70">
                                        <span x-text="(room.name || '?').charAt(0).toUpperCase()"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate"
                                            x-text="room.name"></p>
                                    </div>
                                </div>
                                <span
                                    class="inline-flex items-center justify-center rounded-full border px-2 py-0.5 text-[11px]
                                           border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-300"
                                    x-text="isRoomSelected(room.id) ? 'Selected' : 'Select'"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-3 bg-white dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <div class="text-sm font-semibold">Property tasks to copy</div>
                        <span
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-gray-100 text-[11px]
                                   text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span x-text="selectedTaskIds.length + ' selected'"></span>
                        </span>
                    </div>

                    <input type="text" x-model="taskSearch" placeholder="Search property tasks…"
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700
                               bg-white dark:bg-gray-800 placeholder:text-gray-400 dark:placeholder:text-gray-500
                               focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">

                    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 my-3">
                        <button type="button"
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-gray-100 hover:bg-gray-200
                                   dark:bg-gray-800 dark:hover:bg-gray-700"
                            @click="selectAllTasks()">
                            Select visible
                        </button>
                        <button type="button"
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-transparent hover:bg-gray-100/60
                                   dark:hover:bg-gray-800/60"
                            @click="clearTasks()">
                            Clear
                        </button>
                    </div>

                    <div class="space-y-2 max-h-56 overflow-y-auto px-1">
                        <template x-if="!tasks.length">
                            <p class="text-xs text-gray-500 dark:text-gray-400">No property tasks on this property.</p>
                        </template>
                        <template x-if="tasks.length && !filteredTasks.length">
                            <p class="text-xs text-gray-500 dark:text-gray-400">No tasks match your search.</p>
                        </template>

                        <template x-for="task in filteredTasks" :key="task.id">
                            <button type="button"
                                class="w-full text-left group rounded-xl border px-3 py-2.5 transition
                                       flex items-center justify-between gap-3
                                       border-gray-200 bg-white hover:bg-indigo-50/70
                                       dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800"
                                :class="isTaskSelected(task.id) ? 'ring-2 ring-inset ring-indigo-500 border-indigo-400' : ''"
                                @click="toggleTask(task.id)">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate"
                                        x-text="task.name"></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400" x-show="task.phase"
                                        x-text="task.phase ? task.phase.replaceAll('_', ' ') : ''"></p>
                                </div>
                                <span
                                    class="inline-flex items-center justify-center rounded-full border px-2 py-0.5 text-[11px]
                                           border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-300"
                                    x-text="isTaskSelected(task.id) ? 'Selected' : 'Select'"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Selected IDs as hidden inputs --}}
                <template x-for="id in selectedRoomIds" :key="'r-' + id">
                    <input type="hidden" name="room_ids[]" :value="id">
                </template>
                <template x-for="id in selectedTaskIds" :key="'t-' + id">
                    <input type="hidden" name="task_ids[]" :value="id">
                </template>
            </div>
        </div>

        <div class="flex-shrink-0 p-3 sm:p-4 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <div class="flex items-center justify-end gap-2">
                <x-button variant="secondary"
                    @click="$dispatch('close-preview-panel', 'duplicate-property-{{ $property->id }}')">
                    Close
                </x-button>
                <x-button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700">
                    Create duplicate
                </x-button>
            </div>
        </div>
    </form>
</x-preview-panel>

