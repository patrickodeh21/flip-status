@props([
    'room', // \App\Models\Room
    'name' => 'assign-tasks-' . $room->id, // modal name
    'maxWidth' => '2xl',
])

<x-modal :name="$name" :show="false" :maxWidth="$maxWidth" focusable>
    <div x-data="taskPicker({
        fetchUrl: '{{ route('tasks.suggest') }}',
        postUrl: '{{ route('rooms.tasks.attach', $room) }}',
        csrf: '{{ csrf_token() }}'
    })" class="p-6 text-left">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            Add tasks to: {{ $room->name }}
        </h3>

        {{-- Search / type --}}
        <div class="mt-4 space-y-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                for="task-picker-input-{{ $room->id }}">
                Search or type task names
            </label>

            <div class="relative">
                <input id="task-picker-input-{{ $room->id }}" x-model="query" x-on:input.debounce.200ms="search()"
                    x-on:keydown.enter.prevent="confirm()" x-on:keydown.arrow-down.prevent="move(1)"
                    x-on:keydown.arrow-up.prevent="move(-1)" x-on:keydown.escape.prevent="open=false" type="text"
                    class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="e.g. Mop floor, Change sheets, Dust shelves" autocomplete="off" />

                {{-- Suggestions --}}
                <div x-show="open && suggestions.length" x-transition x-cloak
                    class="flex flex-col absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white shadow dark:border-gray-700 dark:bg-gray-800"
                    role="listbox" :aria-activedescendant="highlighted >= 0 ? 'task-opt-' + highlighted : null"
                    x-ref="list">
                    <template x-for="(item, i) in suggestions" :key="item.id">
                        <button type="button" :id="'task-opt-' + i" :data-idx="i"
                            class="w-full px-3 py-2 text-left"
                            :class="i === highlighted ?
                                'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100' :
                                'hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200'"
                            x-on:mouseenter="hoverIndex(i)" x-on:mousemove="hoverIndex(i)" x-on:click="addItem(item)"
                            role="option" :aria-selected="i === highlighted" x-text="item.name"></button>
                    </template>
                </div>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                Use ↑/↓ to navigate, Enter to select. Press Enter to add a free-text task if not found.
            </p>
        </div>

        {{-- Selected chips --}}
        <div class="mt-4">
            <div class="flex flex-wrap gap-2">
                <template x-for="(t, idx) in selected" :key="t.key">
                    <span
                        class="inline-flex items-center gap-2 rounded-md bg-gray-100 px-2 py-1 text-sm text-gray-800 dark:bg-gray-700 dark:text-gray-100">
                        <span x-text="t.name"></span>
                        <button type="button"
                            class="text-gray-500 hover:text-red-600 dark:text-gray-300 dark:hover:text-red-400"
                            aria-label="Remove" x-on:click="remove(idx)">&times;</button>
                    </span>
                </template>
            </div>
        </div>

        {{-- Submit form with hidden inputs --}}
        @php $formId = 'assignTasksForm-'.$room->id; @endphp
        <form id="{{ $formId }}" method="POST" :action="postUrl" class="mt-6" x-ref="form">
            @csrf

            <div class="hidden" aria-hidden="true">
                {{-- existing tasks (ids) --}}
                <template x-for="t in selected" :key="t.key + '-id'">
                    <template x-if="t.id">
                        <input type="hidden" name="task_ids[]" :value="t.id">
                    </template>
                </template>

                {{-- new free-text tasks (names) --}}
                <template x-for="t in selected" :key="t.key + '-name'">
                    <template x-if="!t.id">
                        <input type="hidden" name="task_names[]" :value="t.name">
                    </template>
                </template>
            </div>

            <div class="mt-4 flex items-center justify-end gap-2">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close')">Cancel</x-button>

                <x-button type="submit" class="bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500"
                    x-bind:disabled="selected.length === 0">
                    Attach (<span x-text="selected.length"></span>)
                </x-button>
            </div>
        </form>
    </div>
</x-modal>
