{{-- resources/views/rooms/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg sm:text-xl font-semibold flex items-center gap-2">
            Rooms
        </h2>
    </x-slot>

    <div x-data="{
        ...roomsIndex,
        openRoomEditDrawer(roomId) {
            if (window.innerWidth < 768) {
                window.location.href = '{{ url('rooms') }}/' + roomId + '/edit';
                return;
            }
            this.$dispatch('open-preview-panel', 'edit-room-panel-' + roomId);
        }
    }" x-init="init()" data-rooms='@json($rooms->pluck('id'))'
        data-tasks='@json($tasks)' data-bulk-url="{{ route('rooms.bulk-attach-tasks') }}"
        data-csrf="{{ csrf_token() }}">
        <div class="mb-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-3 px-1 sm:px-0">
            <form method="get" action="{{ route('rooms.index') }}" class="flex-1 flex flex-col sm:flex-row items-stretch sm:items-center gap-2 min-w-0">
                <x-form.input name="search" placeholder="Search rooms…" value="{{ request('search') }}"
                    class="w-full sm:flex-1 min-w-0 max-w-full" />

                <x-button variant="secondary" type="submit" class="w-full sm:w-auto whitespace-nowrap">Filter</x-button>

                @if (request('search'))
                    <a href="{{ route('rooms.index') }}" class="text-xs sm:text-sm underline text-gray-600 dark:text-gray-300 text-center sm:text-left">
                        Reset
                    </a>
                @endif
            </form>

            @role('admin|owner|company')
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                    {{-- Global bulk assign button --}}
                    <x-button type="button"
                        class="w-full sm:w-auto whitespace-nowrap bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500"
                        x-bind:disabled="selectedRoomIds.length === 0"
                        x-bind:class="selectedRoomIds.length === 0 ? 'opacity-60 cursor-not-allowed' : ''"
                        @click="$dispatch('open-modal', 'bulk-assign-tasks')">
                        Assign Tasks to Selected
                        <span
                            class="ml-1 inline-flex items-center justify-center rounded-full bg-emerald-700/80 text-xs px-1.5 py-0.5"
                            x-show="selectedRoomIds.length > 0" x-text="selectedRoomIds.length"></span>
                    </x-button>

                    <x-button variant="primary" @click="$dispatch('open-preview-panel', 'add-room')" class="w-full sm:w-auto whitespace-nowrap">
                        + Add Room
                    </x-button>
                </div>
            @endrole
        </div>

        <x-card class="!px-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="uppercase text-xs tracking-wide">
                        <tr class="text-gray-600 dark:text-gray-300">
                            @role('admin|owner|company')
                                {{-- Select all --}}
                                <th class="px-4 py-2 text-left">
                                    <x-form.checkbox x-model="selectAll" @change="toggleSelectAll()" />
                                </th>
                            @endrole
                            <th class="px-4 py-2 text-left">Name</th>
                            @role('admin')
                            <th class="px-4 py-2 text-center">Default?</th>
                            @endrole
                            <th class="px-4 py-2 text-center">Tasks</th>
                            <th class="px-4 py-2 text-center">Created</th>
                            @role('admin|owner|company')
                                <th class="px-4 py-2 w-40 text-right">Action</th>
                            @endrole
                        </tr>
                    </thead>

                    <tbody class="divide-y dark:divide-gray-700">
                        @forelse($rooms as $r)
                            <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-900/30">
                                @role('admin|owner|company')
                                    {{-- Row checkbox --}}
                                    <td class="px-4 py-2">
                                        <x-form.checkbox value="{{ $r->id }}" x-model="selectedRoomIds" />
                                    </td>
                                @endrole



                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">
                                    {{ $r->name }}
                                </td>

                                @role('admin')
                                <td class="px-4 py-2 text-center">
                                    <span class="px-2 py-0.5 rounded text-xs {{ $r->is_default ? 'bg-green-100 text-green-800 dark:bg-green-400/20 dark:text-green-300' : 'bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-300' }}">
                                        {{ $r->is_default ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                @endrole

                                <td class="px-4 py-2 text-center">
                                    {{ $r->tasks_count ?? $r->tasks()->count() }}
                                </td>

                                <td class="px-4 py-2 text-center">
                                    {{ $r->created_at?->format('Y-m-d') }}
                                </td>

                                @role('admin|owner|company')
                                    <td class="px-4 py-2 text-right whitespace-nowrap">
                                        <x-action-dropdown align="right" width="w-56" label="Room actions">
                                            <x-dropdown.item href="{{ route('rooms.edit', $r) }}" @click.prevent="openRoomEditDrawer('{{ $r->id }}')">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24"
                                                    fill="currentColor">
                                                    <path
                                                        d="M3 17.25V21h3.75l11-11-3.75-3.75-11 11zM20.71 7.04a1.003 1.003 0 0 0 0-1.42L18.37 3.29a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.83z" />
                                                </svg>
                                                <span>Edit</span>
                                            </x-dropdown.item>


                                            <x-dropdown.item href="{{ route('rooms.tasks.index', $r) }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24"
                                                    fill="currentColor">
                                                    <path
                                                        d="M3 7a2 2 0 0 1 2-2h4v14H5a2 2 0 0 1-2-2V7zm12-2h4a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-4V5zM9 5h6v14H9V5z" />
                                                </svg>
                                                <span>Tasks</span>
                                            </x-dropdown.item>



                                            @if($r->properties_count > 0)
                                                {{-- Button that shows modal if room has properties --}}
                                                <x-dropdown.item as="button"
                                                    x-on:click="
                                                        $dispatch('open-modal', 'cannot-delete-room-{{ $r->id }}');
                                                        $root.closest('[x-data]')?.__x?.$data?.close?.();
                                                    ">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4"
                                                        viewBox="0 0 24 24" fill="currentColor">
                                                        <path
                                                            d="M9 3a1 1 0 0 0-1 1v1H4a1 1 0 1 0 0 2h1v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7h1a1 1 0 1 0 0-2h-4V4a1 1 0 0 0-1-1H9zm2 4a1 1 0 1 0-2 0v10a1 1 0 1 0 2 0V7zm4 0a1 1 0 1 0-2 0v10a1 1 0 1 0 2 0V7z" />
                                                    </svg>
                                                    <span>Delete</span>
                                                </x-dropdown.item>
                                            @else
                                                {{-- Form for rooms without properties --}}
                                                <x-dropdown.item as="form" method="POST"
                                                    href="{{ route('rooms.destroy', $r) }}">
                                                    @method('DELETE')
                                                    <button type="submit" data-menu-item
                                                        class="flex w-full items-center gap-2 px-3 py-2 text-sm text-rose-600 dark:text-rose-400 hover:bg-rose-50/60 dark:hover:bg-rose-900/20 rounded">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4"
                                                            viewBox="0 0 24 24" fill="currentColor">
                                                            <path
                                                                d="M9 3a1 1 0 0 0-1 1v1H4a1 1 0 1 0 0 2h1v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7h1a1 1 0 1 0 0-2h-4V4a1 1 0 0 0-1-1H9zm2 4a1 1 0 1 0-2 0v10a1 1 0 1 0 2 0V7zm4 0a1 1 0 1 0-2 0v10a1 1 0 1 0 2 0V7z" />
                                                        </svg>
                                                        <span>Delete</span>
                                                    </button>
                                                </x-dropdown.item>
                                            @endif
                                        </x-action-dropdown>

                                        {{-- Cannot Delete Modal - Shows when room has properties --}}
                                        @if($r->properties_count > 0)
                                            <x-cannot-delete-room-modal :room="$r" />
                                        @endif
                                    </td>
                                @endrole
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-10 text-center text-gray-500 dark:text-gray-400" @if(auth()->user()->hasRole('admin')) colspan="6" @elseif(auth()->user()->hasAnyRole(['owner', 'company'])) colspan="5" @else colspan="3" @endif>
                                    No rooms yet
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (method_exists($rooms, 'links'))
                <div class="px-4 py-3">
                    {{ $rooms->links() }}
                </div>
            @endif
        </x-card>

        @role('admin|owner|company')
            {{-- Bulk Assign Tasks Modal --}}
            <x-modal name="bulk-assign-tasks" maxWidth="2xl">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Assign Tasks to Selected Rooms
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    <span x-text="selectedRoomIds.length"></span> room(s) selected
                </p>
            </div>

            <div class="px-6 py-4 space-y-4">
                <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Search tasks
                        </label>
                        <input type="text"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm"
                            placeholder="Type to filter tasks…" x-model="taskSearch" />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Type
                        </label>
                        <select class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm"
                            x-model="taskTypeFilter">
                            <option value="">All types</option>
                            <option value="room">Room</option>
                            <option value="inventory">Inventory</option>
                            <option value="verify">Verify</option>
                        </select>
                    </div>
                </div>

                <div
                    class="max-h-72 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-3 space-y-2">
                    <template x-if="filteredTasks().length === 0">
                        <p class="text-xs text-gray-500">No tasks found. Try adjusting your filters.</p>
                    </template>

                    <template x-for="task in filteredTasks()" :key="task.id">
                        <label
                            class="flex items-center justify-between gap-3 text-sm px-2 py-1.5 rounded hover:bg-gray-50 dark:hover:bg-gray-800/70 cursor-pointer">
                            <span class="flex items-center gap-2">
                                <x-form.checkbox x-bind:value="task.id" x-model="selectedTaskIds" />
                                <span class="font-medium" x-text="task.name"></span>
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <span
                                    class="px-2 py-0.5 rounded-full text-2xs uppercase tracking-wide
                                        bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200"
                                    x-text="task.type"></span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wide"
                                    :class="task.is_default ?
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' :
                                        'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200'">
                                    <span x-text="task.is_default ? 'Default' : 'Custom'"></span>
                                </span>
                            </span>
                        </label>
                    </template>
                </div>
            </div>

            <div
                class="px-6 py-3 bg-gray-50 dark:bg-gray-900/40 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div class="text-xs text-gray-500 space-y-0.5">
                    <p>
                        <span class="font-semibold" x-text="selectedTaskIds.length"></span>
                        task(s) selected.
                    </p>
                    <p class="text-[11px]">
                        All selected tasks will be attached to
                        <span class="font-semibold" x-text="selectedRoomIds.length"></span>
                        room(s).
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <x-button type="button" variant="secondary" x-on:click="$dispatch('close')">
                        Cancel
                    </x-button>

                    <x-button type="button" class="bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500"
                        x-bind:disabled="!selectedRoomIds.length || !selectedTaskIds.length || isSubmittingBulk"
                        x-bind:class="(!selectedRoomIds.length || !selectedTaskIds.length || isSubmittingBulk) ?
                        'opacity-60 cursor-not-allowed' : ''"
                        @click="submitBulkAssign()">
                        <span x-show="!isSubmittingBulk">Assign to Rooms</span>
                        <span x-show="isSubmittingBulk">Assigning…</span>
                    </x-button>
                </div>
            </div>
        </x-modal>
        @endrole
    </div>

    @role('admin|owner|company')
        {{-- Add Room Sidebar Panel --}}
    <x-preview-panel
        name="add-room"
        :overlay="true"
        side="right"
        initialWidth="28rem"
        minWidth="24rem"
        title="Add Room"
        subtitle="Create a new room template">

        <div class="p-6 space-y-6" x-data="roomCreateForm({
            storeUrl: @js(route('rooms.store')),
            csrf: @js(csrf_token())
        })">
            <form @submit.prevent="submitForm" class="space-y-6">
                {{-- Room Name --}}
                <div>
                    <label for="room-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Room Name <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="room-name"
                        name="name"
                        x-model="formData.name"
                        required
                        placeholder="e.g. Bedroom, Kitchen, Bathroom"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100
                               focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                               transition-all duration-200 px-4 py-2.5 text-sm"
                    />
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Enter a descriptive name for this room type</p>
                </div>

                @role('admin')
                {{-- Default Template Checkbox --}}
                <div class="space-y-3">
                    <label class="flex items-start gap-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700
                                  hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer transition-colors">
                        <x-form.checkbox
                            name="is_default"
                            value="1"
                            x-model="formData.is_default"
                        />
                        <div class="flex-1">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Mark as default template</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Default rooms can be auto-assigned when new properties are created.
                            </div>
                        </div>
                    </label>
                </div>
                @endrole

                {{-- Error Message --}}
                <div x-show="error" x-cloak class="p-3 rounded-lg bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800">
                    <p class="text-sm text-rose-800 dark:text-rose-200" x-text="error"></p>
                </div>

                {{-- Success Message --}}
                <div x-show="success" x-cloak class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800">
                    <p class="text-sm text-emerald-800 dark:text-emerald-200" x-text="success"></p>
                </div>

                {{-- Footer Actions --}}
                <div class="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button
                        type="button"
                        @click="$dispatch('close-preview-panel', 'add-room')"
                        class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300
                               bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700
                               rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="submitting"
                        :class="submitting ? 'opacity-60 cursor-not-allowed' : ''"
                        class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-indigo-600
                               hover:bg-indigo-700 rounded-lg transition-colors flex items-center justify-center gap-2"
                    >
                        <svg x-show="submitting" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="submitting ? 'Creating...' : 'Create Room'"></span>
                    </button>
                </div>
            </form>
        </div>
        </div>
    </x-preview-panel>
    
    {{-- Reusable Edit Panel Container --}}
    @foreach ($rooms as $r)
        <x-preview-panel
            name="edit-room-panel-{{ $r->id }}"
            :overlay="true"
            side="right"
            initialWidth="38rem"
            minWidth="32rem"
            title="Edit Room & Tasks"
            :subtitle="'Updating ' . $r->name">
            @include('rooms.__edit_partial', ['room' => $r, 'roomTasks' => $r->tasks()->orderBy('room_task.sort_order')->get(), 'availableTasks' => $tasks])
        </x-preview-panel>
    @endforeach
    @endrole
</x-app-layout>
