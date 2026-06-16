{{-- resources/views/rooms/__edit_partial.blade.php --}}
<div class="h-full flex flex-col p-6 overflow-y-auto">
    <form method="POST" action="{{ route('rooms.update', $room) }}" class="flex-1 flex flex-col">
            @csrf
            @method('PUT')

            {{-- Room details --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div>
                    <x-form.label value="Room Name" />
                    <x-form.input name="name" class="w-full" required value="{{ old('name', $room->name) }}" />
                    <x-form.error :messages="$errors->get('name')" />
                </div>

                @role('admin')
                <div class="flex items-center gap-3 mt-6 md:mt-8">
                    <x-form.checkbox id="is_default" name="is_default" value="1"
                        :checked="old('is_default', $room->is_default)" />
                    <label for="is_default" class="text-sm text-gray-700 dark:text-gray-300">
                        Mark as default room type
                    </label>
                </div>
                @endrole
            </div>

            {{-- Tasks section --}}
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                @php
                    $orderUrl = route('rooms.tasks.order', $room);
                    $attachUrl = route('rooms.tasks.attach', $room);
                    $suggestUrl = route('tasks.suggest');
                    // Base URL for detach - we'll append task ID in JS
                    $detachBaseUrl = route('rooms.tasks.detach', [$room, 0]);
                    $detachBaseUrl = str_replace('/0', '', $detachBaseUrl);
                @endphp

                <div x-data="roomTasksEditor({
                    orderUrl: @js($orderUrl),
                    attachUrl: @js($attachUrl),
                    suggestUrl: @js($suggestUrl),
                    detachBaseUrl: @js($detachBaseUrl),
                    csrf: @js(csrf_token()),
                    roomTasks: @js($roomTasks),
                    availableTasks: []
                })" x-init="init()">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                Tasks for this room
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Drag tasks to reorder. Use the search below to add new tasks.
                            </p>
                        </div>

                        <div class="min-h-[28px]">
                            <span x-show="status==='saving'" x-cloak class="text-xs px-2 py-1 rounded bg-amber-100 text-amber-800 dark:bg-amber-400/20 dark:text-amber-300">Saving…</span>
                            <span x-show="status==='saved'" x-cloak class="text-xs px-2 py-1 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-400/20 dark:text-emerald-300">✓ Saved at <span x-text="savedAt"></span></span>
                            <span x-show="status==='error'" x-cloak class="text-xs px-2 py-1 rounded bg-rose-100 text-rose-800 dark:bg-rose-400/20 dark:text-rose-300">Failed</span>
                        </div>
                    </div>

                    {{-- Add task search --}}
                    <div class="mb-4 relative">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Add Task
                        </label>
                        <div class="relative">
                            <input
                                type="text"
                                x-model="searchQuery"
                                @input="debounceCapitalize(); debounceSearch()"
                                @keydown="handleKeyDown($event)"
                                @focus="searchQuery && searchTasks()"
                                placeholder="Search tasks to add... (Press Enter to create if not found)"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm pl-3 pr-10 py-2"
                            />
                            <svg x-show="loading" class="absolute right-3 top-2.5 h-4 w-4 animate-spin text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>

                        {{-- Suggestions dropdown --}}
                        <div
                            x-show="openSuggestions && suggestions.length > 0"
                            x-cloak
                            class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-y-auto"
                            @click.away="openSuggestions = false"
                        >
                            <template x-for="(task, index) in suggestions" :key="task.id">
                                <button
                                    type="button"
                                    @click="addTask(task)"
                                    @mouseenter="hoverIndex(index)"
                                    :class="highlighted === index ? 'bg-indigo-50 dark:bg-indigo-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-700'"
                                    class="w-full px-4 py-2 text-left text-sm flex items-center justify-between"
                                >
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-gray-100" x-text="task.name"></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            <span class="uppercase" x-text="task.type"></span>
                                            <span x-show="task.is_default" class="ml-2 text-emerald-600 dark:text-emerald-400">• Default</span>
                                        </div>
                                    </div>
                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Room tasks list (draggable) --}}
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                        <div
                            x-ref="taskList"
                            class="divide-y dark:divide-gray-700"
                        >
                            <template x-if="roomTasks.length === 0">
                                <div class="px-4 py-8 text-sm text-gray-500 dark:text-gray-400 text-center">
                                    No tasks yet. Search above to add tasks to this room.
                                </div>
                            </template>

                            <template x-for="(task, index) in roomTasks" :key="task.key || `task-${task.id}`">
                                <div
                                    :data-task-id="task.id"
                                    class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                                >
                                    {{-- Drag handle --}}
                                    <button
                                        type="button"
                                        class="drag-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 flex-shrink-0"
                                        title="Drag to reorder"
                                    >
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M7 2a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM7 8a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM7 14a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM13 2a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM13 8a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM13 14a2 2 0 1 1 0 4 2 2 0 0 1 0-4z"></path>
                                        </svg>
                                    </button>

                                    {{-- Task info --}}
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-gray-900 dark:text-gray-100" x-text="task.name"></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            <span class="uppercase tracking-wide" x-text="task.type"></span>
                                            <span class="mx-1">•</span>
                                            <span x-text="task.is_default ? 'Default task' : 'Custom task'"></span>
                                        </div>
                                    </div>

                                    {{-- Badges --}}
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <span
                                            class="px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wide
                                                bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200"
                                            x-text="task.type"
                                        ></span>

                                        <span
                                            class="px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wide"
                                            :class="task.is_default ?
                                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' :
                                                'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200'"
                                        >
                                            <span x-text="task.is_default ? 'Default' : 'Custom'"></span>
                                        </span>
                                    </div>

                                    {{-- Remove button --}}
                                    <button
                                        type="button"
                                        @click="removeTask(task.id)"
                                        class="text-rose-600 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300 flex-shrink-0 p-1"
                                        title="Remove task from room"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- Sticky Footer Actions --}}
            <div class="mt-auto pt-6 pb-2 sticky bottom-0 bg-white dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700/50 -mx-6 px-6">
                <div class="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        @click="$dispatch('close-preview-panel', 'edit-room-panel-{{ $room->id }}')"
                        class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300
                               bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600
                               rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2.5 text-sm font-medium text-white bg-indigo-600 border border-transparent
                               rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors shadow-sm whitespace-nowrap"
                    >
                        Save Room
                    </button>
                </div>
            </div>
        </form>
</div>
