@php
    $storeUrl = route('rooms.tasks.bulk-store', $room);
    $suggestUrl = route('tasks.suggest');
@endphp

<div x-data="bulkTaskForm({
    storeUrl: @js($storeUrl),
    suggestUrl: @js($suggestUrl),
    csrf: @js(csrf_token()),
    roomId: {{ $room->id }},
    defaultIsSporadic: false,
})" class="space-y-4 p-0 sm:p-2 md:p-4 max-w-full">
    <div>
        <x-form.label value="Task Names" />
        <p class="mt-1 mb-3 text-sm text-gray-500 dark:text-gray-400">
            Search for existing tasks or type new task names, one per line. Press Enter after each task to add it to the list.
        </p>

        {{-- Autocomplete Search Input --}}
        <div class="relative mb-3"
             x-data="{
                 ...taskAutocomplete({ suggestUrl: @js($suggestUrl) }),
                 handleEnter(e) {
                     if (!this.open) {
                         if (this.q.trim()) {
                             $dispatch('add-suggested-task', this.q.trim());
                             this.q = '';
                         }
                         return;
                     }
                     const max = this.items.length;
                     if (e.key === 'Enter') {
                         e.preventDefault();
                         if (this.focusedIndex >= 0 && this.focusedIndex < max && this.items[this.focusedIndex]) {
                             const selected = this.items[this.focusedIndex];
                             this.q = selected.name;
                             this.open = false;
                             this.focusedIndex = -1;
                             $dispatch('add-suggested-task', selected.name);
                         } else if (this.q.trim()) {
                             $dispatch('add-suggested-task', this.q.trim());
                             this.q = '';
                             this.open = false;
                         }
                     }
                 }
             }"
             @click.outside="open = false">
            <div class="relative">
                <input
                    type="text"
                    x-model="q"
                    @input="onInput()"
                    @focus="onFocus()"
                    @keydown="keyDown($event); handleEnter($event)"
                    placeholder="Search existing tasks or type new task name..."
                    class="w-full max-w-full px-3 sm:px-4 py-2.5 pl-9 sm:pl-10 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:text-gray-100 text-sm"
                />
                <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <div x-show="loading" class="absolute right-3 top-1/2 transform -translate-y-1/2">
                    <svg class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>

            {{-- Suggestions Dropdown --}}
            <div x-show="open && (items.length > 0 || q.trim())"
                 x-cloak
                 class="absolute z-50 w-full max-w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                <template x-if="items.length > 0">
                    <div class="py-1">
                        <template x-for="(item, index) in items" :key="item.id">
                            <button
                                type="button"
                                @click="q = item.name; open = false; focusedIndex = -1; $dispatch('add-suggested-task', item.name)"
                                :class="index === focusedIndex ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-900 dark:text-indigo-100' : 'text-gray-900 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700'"
                                class="w-full text-left px-4 py-2 text-sm flex items-center justify-between"
                            >
                                <span x-text="item.name"></span>
                                <span class="text-xs text-gray-500 dark:text-gray-400" x-text="item.type || 'task'"></span>
                            </button>
                        </template>
                    </div>
                </template>
                <template x-if="items.length === 0 && q.trim() && !loading">
                    <div class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                        No matching tasks found. Press Enter to add "<span x-text="q"></span>" as a new task.
                    </div>
                </template>
            </div>
        </div>

        <textarea
            x-ref="taskInput"
            @keydown.enter.prevent="addTaskFromInput()"
            @paste="handlePaste($event)"
            @add-suggested-task.window="addSuggestedTask($event.detail)"
            placeholder="Type task name and press Enter&#10;Or paste multiple tasks (one per line)&#10;&#10;Example:&#10;Vacuum carpet&#10;Clean windows&#10;Dust furniture"
            class="w-full max-w-full min-h-[200px] px-3 sm:px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:text-gray-100 font-mono text-sm"
            rows="8"></textarea>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            Tip: Use the search above to find existing tasks, or paste a list of tasks from a document. Each line will become a separate task.
        </p>
    </div>

    {{-- Task List Preview --}}
    <div x-show="tasks.length > 0" class="space-y-2">
        <div class="flex items-center justify-between">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                <span x-text="'Tasks to Add (' + tasks.length + ')'"></span>
            </label>
            <button type="button" @click="clearAll()" class="text-sm text-rose-600 hover:text-rose-700 dark:text-rose-400">
                Clear All
            </button>
        </div>
        <div class="max-h-[300px] overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg divide-y dark:divide-gray-700">
            <template x-for="(task, index) in tasks" :key="index">
                <div class="flex items-center justify-between px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-800/50 group">
                    <div class="flex items-center gap-3 flex-1">
                        <span class="text-xs text-gray-400 dark:text-gray-500 font-mono" x-text="index + 1"></span>
                        <span class="text-sm text-gray-900 dark:text-gray-100" x-text="task.name"></span>
                    </div>
                    <button type="button" @click="removeTask(index)" class="opacity-0 group-hover:opacity-100 transition-opacity text-rose-600 hover:text-rose-700 dark:text-rose-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </template>
        </div>
    </div>

    {{-- Default Type Selection --}}
    <div x-show="tasks.length > 0" class="space-y-4">
        <div>
            <x-form.label value="Default Task Type" />
            <p class="mt-1 mb-3 text-sm text-gray-500 dark:text-gray-400">
                All tasks will be created with this type. You can change individual task types later when editing.
            </p>
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                <label class="flex items-center">
                    <input type="radio" name="default_type" value="room" x-model="defaultType" class="mr-2 text-indigo-600 focus:ring-indigo-500" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">Room Task</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" name="default_type" value="inventory" x-model="defaultType" class="mr-2 text-indigo-600 focus:ring-indigo-500" />
                    <span class="mr-4 text-sm text-gray-700 dark:text-gray-300">Inventory Task</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" name="default_type" value="verify" x-model="defaultType" class="mr-2 text-indigo-600 focus:ring-indigo-500" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">Verify Task</span>
                </label>
            </div>
        </div>

        <div>
            <label class="inline-flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200 cursor-pointer">
                <input type="checkbox" name="default_is_sporadic" value="1" x-model="defaultIsSporadic" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500">
                <span>Mark all as occasional tasks</span>
            </label>
        </div>
    </div>

    {{-- Status Messages --}}
    <div x-show="status" class="rounded-lg p-3" :class="{
        'bg-amber-50 text-amber-800 dark:bg-amber-400/20 dark:text-amber-300': status === 'saving',
        'bg-emerald-50 text-emerald-800 dark:bg-emerald-400/20 dark:text-emerald-300': status === 'saved',
        'bg-rose-50 text-rose-800 dark:bg-rose-400/20 dark:text-rose-300': status === 'error'
    }">
        <p class="text-sm font-medium" x-text="message"></p>
    </div>

    {{-- Action Buttons - Sticky at bottom --}}
    <div class="sticky bottom-0 flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2 sm:gap-3 pt-4 pb-2 sm:pb-0 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 -mx-0 sm:-mx-2 md:-mx-4 px-0 sm:px-2 md:px-4 mt-4 z-10">
        <x-button type="button" variant="secondary" @click="$dispatch('close-preview-panel')" class="w-full sm:w-auto">Cancel</x-button>
        <x-button type="button" variant="primary" @click="saveAll()" x-bind:disabled="tasks.length === 0 || status === 'saving'" class="w-full sm:w-auto">
            <span x-show="status !== 'saving'">Save <span x-text="tasks.length"></span> Task<span x-show="tasks.length !== 1">s</span></span>
            <span x-show="status === 'saving'" class="flex items-center gap-2">
                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Saving...
            </span>
        </x-button>
    </div>
</div>
