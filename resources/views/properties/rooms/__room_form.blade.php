@props([
    'property',
    'room' => null, // null for create, Room model for edit
    'suggestUrl',
    'mode' => 'create', // 'create' or 'edit'
])

@php
    $isEdit = $mode === 'edit' && $room;
    $storeUrl = $isEdit
        ? route('properties.rooms.update', [$property, $room])
        : route('properties.rooms.store', $property);
    $panelName = $isEdit
        ? "edit-room-{$property->id}-{$room->id}"
        : "add-room-{$property->id}";
    $sortOrder = $isEdit ? ($room->pivot->sort_order ?? null) : null;
    $tasksCount = $isEdit ? ($room->tasks_count ?? $room->tasks()->count()) : 0;
@endphp

<div class="p-0 sm:p-2 md:p-4 lg:p-6 space-y-4 sm:space-y-6 max-w-full flex flex-col" x-data="propertyRoomForm({
    suggestUrl: @js($suggestUrl),
    storeUrl: @js($storeUrl),
    csrf: @js(csrf_token()),
    propertyId: @js($property->id),
    roomId: @js($room?->id),
    mode: @js($mode),
    panelName: @js($panelName),
    initialData: @js($isEdit ? [
        'name' => $room->name ?? '',
        'is_default' => (bool) ($room->is_default ?? false),
        'min_photos' => (int) ($room->min_photos ?? 2),
    ] : [
        'name' => '',
        'is_default' => false,
        'min_photos' => 2,
    ])
})">
    <form @submit.prevent="submitForm" class="space-y-6 flex flex-col">
        @if($isEdit)
            @method('PUT')
        @endif

        <div x-data="roomAutocomplete({ suggestUrl: @js($suggestUrl), csrf: @js(csrf_token()) })"
             @if($isEdit) x-init="q = @js($room->name)" @endif
             class="grid grid-cols-1 @if($isEdit) lg:grid-cols-3 @else md:grid-cols-2 @endif gap-4 sm:gap-6">
            {{-- Room Name with Autocomplete --}}
            <div class="@if($isEdit) lg:col-span-2 @endif">
                <label for="room-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Room Name <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <input
                        x-model="q"
                        x-ref="nameInput"
                        name="name"
                        id="room-name"
                        type="text"
                        required
                        autocomplete="off"
                        placeholder="e.g., Kitchen, Bedroom, Laundry"
                        class="w-full max-w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100
                               focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                               transition-all duration-200 px-3 sm:px-4 py-2.5 text-sm"
                        @input="onInput"
                        @focus="onFocus"
                        @keydown="keyDown"
                        aria-autocomplete="list"
                        aria-expanded="open"
                        aria-controls="room-suggest"
                    />

                    {{-- Autocomplete Dropdown --}}
                <div
                    x-cloak
                    x-show="open"
                    id="room-suggest"
                    role="listbox"
                    class="absolute z-50 mt-1 w-full max-w-full rounded-lg border border-gray-200 dark:border-gray-700
                           bg-white dark:bg-gray-800 shadow-xl overflow-hidden"
                >
                        <div x-show="loading" class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Searching…
                        </div>

                        <template x-if="hasResults">
                            <ul class="max-h-60 overflow-y-auto">
                                <template x-for="(item, idx) in items" :key="item.id">
                                    <li
                                        class="px-4 py-2.5 cursor-pointer text-sm flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                        :class="{'bg-gray-50 dark:bg-gray-700': focusedIndex === idx}"
                                        role="option"
                                        @mouseenter="focusedIndex = idx"
                                        @mouseleave="focusedIndex = -1"
                                        @click="choose(item)"
                                    >
                                        <span x-text="item.name" class="text-gray-900 dark:text-gray-100 font-medium"></span>
                                        <span x-show="item.is_default"
                                              class="ml-2 text-[10px] uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-400/20 dark:text-emerald-300 px-2 py-0.5 rounded-full font-medium">
                                            Default
                                        </span>
                                    </li>
                                </template>

                                <li
                                    class="px-4 py-2.5 cursor-pointer text-sm text-indigo-700 dark:text-indigo-300
                                           bg-indigo-50/60 dark:bg-indigo-900/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/50
                                           transition-colors flex items-center gap-2"
                                    :class="{'ring-2 ring-indigo-400': focusedIndex === items.length}"
                                    @mouseenter="focusedIndex = items.length"
                                    @mouseleave="focusedIndex = -1"
                                    @click="open = false"
                                    x-show="q && !items.some(i => i.name.toLowerCase() === q.toLowerCase())"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span x-text="createLabel()"></span>
                                </li>
                            </ul>
                        </template>

                        <div
                            x-show="!loading && !hasResults && q"
                            class="px-4 py-2.5 text-sm text-indigo-700 dark:text-indigo-300
                                   bg-indigo-50/60 dark:bg-indigo-900/30 flex items-center gap-2"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Create "<span x-text="q" class="font-semibold"></span>"
                        </div>
                    </div>
                </div>
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                    @if($isEdit)
                        Start typing to select an existing room template, or enter a new name to create one and attach it to this property.
                    @else
                        Type to search existing room templates. If it doesn't exist, we'll create it on save and attach it to this property.
                    @endif
                </p>
            </div>

            {{-- Default toggle --}}
            @role('admin')
            <div class="@if($isEdit) lg:col-span-1 @endif">
                <label class="inline-flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700
                              hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer transition-colors h-full">
                    <x-form.checkbox
                        name="is_default"
                        value="1"
                        x-model="formData.is_default"
                    />
                    <div class="flex-1">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Default Room</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Highlighted in suggestions</div>
                    </div>
                </label>
            </div>
            @endrole

            {{-- Min Photos --}}
            <div class="@if($isEdit) lg:col-span-1 @endif">
                <label for="room-min-photos" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Mandatory Photos Req.
                </label>
                <input
                    x-model.number="formData.min_photos"
                    name="min_photos"
                    id="room-min-photos"
                    type="number"
                    min="0"
                    max="50"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200 px-3 py-2.5 text-sm"
                />
            </div>

            @if($isEdit)
                {{-- Context / pivot info --}}
                <div class="lg:col-span-3 bg-gray-50 dark:bg-gray-900/40 rounded-lg p-4">
                    <div class="text-sm text-gray-700 dark:text-gray-200 font-semibold mb-3">Attachment</div>
                    <dl class="text-sm space-y-2">
                        <div class="flex items-center justify-between py-2 border-b dark:border-gray-800">
                            <dt class="text-gray-500 dark:text-gray-400">Currently attached as</dt>
                            <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ $room->name }}</dd>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b dark:border-gray-800">
                            <dt class="text-gray-500 dark:text-gray-400">Tasks</dt>
                            <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ $tasksCount }}</dd>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <dt class="text-gray-500 dark:text-gray-400">Sort order (this property)</dt>
                            <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ $sortOrder ?? '—' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2 sm:gap-2">
                        <a href="{{ route('properties.tasks.index', ['property' => $property->id, 'room' => $room->id]) }}"
                           class="text-indigo-600 hover:underline dark:text-indigo-400 text-sm text-center sm:text-left">
                            Manage tasks →
                        </a>

                        <button type="button"
                                class="text-rose-600 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300 text-sm underline text-center sm:text-right"
                                @click="showDetachModal = true">
                            Detach from property
                        </button>
                    </div>
                </div>
            @endif
        </div>

        {{-- Error Message --}}
        <div x-show="error" x-cloak class="p-3 rounded-lg bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800">
            <p class="text-sm text-rose-800 dark:text-rose-200" x-text="error"></p>
        </div>

        {{-- Success Message --}}
        <div x-show="success" x-cloak class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800">
            <p class="text-sm text-emerald-800 dark:text-emerald-200" x-text="success"></p>
        </div>
        {{-- Footer Actions - Inline at bottom of form --}}
        <div class="flex flex-row items-center gap-2 sm:gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button
                type="button"
                @click="$dispatch('close-preview-panel', panelName)"
                class="w-1/2 px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300
                       bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700
                       rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors flex items-center justify-center"
            >
                Cancel
            </button>
            <button
                type="submit"
                :disabled="submitting"
                :class="submitting ? 'opacity-60 cursor-not-allowed' : ''"
                class="w-1/2 px-4 py-3 text-sm font-medium text-white bg-indigo-600
                       hover:bg-indigo-700 rounded-lg transition-colors flex items-center justify-center gap-2"
            >
                <svg x-show="submitting" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="submitting ? 'Saving...' : (mode === 'edit' ? 'Save Changes' : 'Create Room')"></span>
            </button>
        </div>
    </form>

    @if($isEdit)
        {{-- Detach Modal --}}
        <div x-show="showDetachModal" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 dark:bg-black/70"
             @click.self="showDetachModal = false">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-4 sm:p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Detach Room</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        This will remove <span class="font-medium">{{ $room->name }}</span> from <span class="font-medium">{{ $property->name }}</span>.
                        The room template itself will remain available globally. Tasks attached to this room in other properties are unaffected.
                    </p>

                    <div class="mt-6 flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="showDetachModal = false"
                            class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm font-medium text-gray-700 dark:text-gray-300
                                   bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700
                                   rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            Cancel
                        </button>
                        <form method="post" action="{{ route('properties.rooms.destroy', [$property, $room]) }}" class="inline w-full sm:w-auto">
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm font-medium text-white bg-rose-600
                                       hover:bg-rose-700 rounded-lg transition-colors"
                            >
                                Detach
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

