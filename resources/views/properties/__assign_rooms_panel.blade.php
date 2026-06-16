<x-preview-panel name="assign-rooms-{{ $property->id }}" :overlay="true" side="right" initialWidth="30rem"
    minWidth="20rem" title="Assign Rooms" subtitle="Filter, select and preview rooms for this property">
    <div class="p-4 h-full flex flex-col text-gray-800 dark:text-gray-100" x-ref="assignRoomsScope"
        x-data="assignRoomsPanel(
            @js($property->id),
            @js($roomsForJs),
            @js($attachedRoomIds),
            @js(route('properties.rooms.store', $property->id)),
            @js(route('properties.rooms.attach', $property->id)),
        )"
        x-on:assign-rooms-save-{{ $property->id }}.window="save()">
        <div class="flex flex-col gap-3 mb-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm text-left font-semibold text-gray-900 dark:text-gray-100">
                    Rooms for: {{ $property->name }}
                </h3>


                <span
                    class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-gray-100 text-[11px]
                           text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    <span x-text="selectedIds.length + ' selected'"></span>
                </span>
            </div>

            {{-- New room quick-create (front-end only stub) --}}
            <div class="flex items-center gap-2">
                <input type="text" x-model="newRoomName" placeholder="Quick create room…"
                    class="flex-1 px-3 py-2 text-xs rounded-lg border border-gray-300 dark:border-gray-700
                           bg-white dark:bg-gray-800 placeholder:text-gray-400 dark:placeholder:text-gray-500
                           focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <x-button type="button" size="sm" variant="secondary" class="!text-xs" @click="createRoom()"
                    x-bind:disabled="!newRoomName.trim() || isCreating">
                    <span x-show="!isCreating">Add</span>
                    <span x-show="isCreating">Adding…</span>
                </x-button>
            </div>

            {{-- Search + info --}}
            <div class="flex items-center justify-between gap-3 mt-2">
                <div class="flex-1 relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="h-4 w-4 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
                        </svg>
                    </span>
                    <input type="text" x-model="search" placeholder="Search rooms…"
                        class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700
                               bg-white dark:bg-gray-800 placeholder:text-gray-400 dark:placeholder:text-gray-500
                               focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div class="flex flex-col items-end gap-1 text-[11px] text-gray-500 dark:text-gray-400">
                    <span x-text="rooms.length + ' total'"></span>
                    <span x-show="search" x-text="'Filtered: ' + filtered.length"></span>
                </div>
            </div>

            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <div class="flex items-center gap-2">
                    <button type="button"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-gray-100 hover:bg-gray-200
                               dark:bg-gray-800 dark:hover:bg-gray-700"
                        @click="selectAll()">
                        <span>Select visible</span>
                    </button>
                    <button type="button"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-transparent hover:bg-gray-100/60
                               dark:hover:bg-gray-800/60"
                        @click="clearSelection()">
                        <span>Clear</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Room list --}}
        <div class="flex-1 space-y-2 px-1">
            <template x-if="!filtered.length">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    No rooms match your search.
                </p>
            </template>

            <template x-for="room in filtered" :key="room.id">
                <button type="button"
                    class="w-full text-left group rounded-xl border px-3 py-2.5 transition
                           flex items-center justify-between gap-3
                           border-gray-200 bg-white hover:bg-indigo-50/70
                           dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800"
                    :class="isSelected(room.id) ? 'ring-2 ring-inset ring-indigo-500 border-indigo-400' : ''"
                    @click="toggle(room.id)">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-xs font-semibold
                                   bg-indigo-50 text-indigo-700 group-hover:bg-indigo-100
                                   dark:bg-indigo-900/40 dark:text-indigo-200 dark:group-hover:bg-indigo-900/70">
                            <span x-text="room.name.charAt(0).toUpperCase()"></span>
                        </div>

                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="room.name"></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-2">
                                <span x-show="room.is_default"
                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-emerald-50
                                           text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Default
                                </span>
                                <span class="hidden sm:inline text-[11px] text-gray-400 dark:text-gray-500">
                                    Click to
                                    <span x-text="isSelected(room.id) ? 'unselect' : 'select'"></span>
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex items-center justify-center rounded-full border px-2 py-0.5 text-[11px]
                                   border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-300"
                            x-text="isSelected(room.id) ? 'Selected' : 'Tap to select'"></span>
                    </div>
                </button>
            </template>
        </div>
    </div>

    <x-slot:footer>
        <div class="flex items-center justify-end gap-2">
            <x-button variant="secondary" @click="$dispatch('close-preview-panel', 'assign-rooms-{{ $property->id }}')">
                Close
            </x-button>

            <x-button type="button" class="bg-indigo-600 hover:bg-indigo-700"
                @click="window.dispatchEvent(new CustomEvent('assign-rooms-save-{{ $property->id }}'))">
                Save selection
            </x-button>
        </div>
    </x-slot:footer>

</x-preview-panel>
