@props([
    'property',                                  // \App\Models\Property
    'name' => 'assign-rooms-' . $property->id,   // modal name
    'maxWidth' => '2xl',
])

{{-- Requires: resources/js/components/room-picker.js and Alpine registration in app.js --}}
<x-modal :name="$name" :show="false" :maxWidth="$maxWidth" focusable>
    <div
        x-data="roomPicker({
            fetchUrl: '{{ route('rooms.suggest') }}',
            postUrl: '{{ route('properties.rooms.attach', $property) }}',
            csrf: '{{ csrf_token() }}'
        })"
        class="p-6"
    >
        <h3 class="text-left text-lg font-semibold text-gray-900 dark:text-gray-100">
            Add rooms to: {{ $property->name }}
        </h3>

        {{-- Search / type input --}}
        <div class="text-left mt-4 space-y-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="room-picker-input-{{ $property->id }}">
                Search or type room names
            </label>

            <div class="relative">
                <input
                    id="room-picker-input-{{ $property->id }}"
                    x-model="query"
                    x-on:input.debounce.200ms="search()"
                    x-on:keydown.enter.prevent="enterQuery()"
                    type="text"
                    class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="e.g. Bedroom, Kitchen, Living Room"
                    autocomplete="off"
                />

                {{-- Suggestions dropdown --}}
                <div
                    x-show="open && suggestions.length"
                    x-transition
                    x-cloak
                    class="absolute z-10 mt-1 w-full rounded-md border border-gray-200 bg-white shadow dark:border-gray-700 dark:bg-gray-800"
                    role="listbox"
                    :aria-activedescendant="null"
                >
                    <template x-for="item in suggestions" :key="item.id">
                        <button
                            type="button"
                            class="w-full px-3 py-2 text-left hover:bg-gray-50 focus:bg-gray-50 dark:hover:bg-gray-700 dark:focus:bg-gray-700"
                            x-on:click="addItem(item)"
                            x-text="item.name"
                            role="option"
                        ></button>
                    </template>
                </div>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                Press <kbd class="px-1 py-0.5 rounded border text-[11px]">Enter</kbd> to add a free-text room if not found.
            </p>
        </div>

        {{-- Selected chips (visual) --}}
        <div class="mt-4">
            <div class="flex flex-wrap gap-2">
                <template x-for="(r, idx) in selected" :key="r.key">
                    <span class="inline-flex items-center gap-2 rounded-md bg-gray-100 px-2 py-1 text-sm text-gray-800 dark:bg-gray-700 dark:text-gray-100">
                        <span x-text="r.name"></span>
                        <button
                            type="button"
                            class="text-gray-500 hover:text-red-600 dark:text-gray-300 dark:hover:text-red-400"
                            aria-label="Remove"
                            x-on:click="remove(idx)"
                        >
                            &times;
                        </button>
                    </span>
                </template>
            </div>
        </div>

        {{-- Submit form (contains hidden inputs) --}}
        @php $formId = 'assignRoomsForm-'.$property->id; @endphp
        <form id="{{ $formId }}" method="POST" :action="postUrl" class="mt-6" x-ref="form">
            @csrf

            {{-- Hidden inputs that actually submit --}}
            <div class="hidden" aria-hidden="true">
                {{-- Existing rooms (by id) --}}
                <template x-for="r in selected" :key="r.key + '-id'">
                    <template x-if="r.id">
                        <input type="hidden" name="room_ids[]" :value="r.id">
                    </template>
                </template>

                {{-- New rooms (by name) --}}
                <template x-for="r in selected" :key="r.key + '-name'">
                    <template x-if="!r.id">
                        <input type="hidden" name="room_names[]" :value="r.name">
                    </template>
                </template>
            </div>

            <div class="mt-4 flex items-center justify-end gap-2">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close')">
                    Cancel
                </x-button>

                <x-button
                    type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500"
                    x-bind:disabled="selected.length === 0"
                >
                    Attach (<span x-text="selected.length"></span>)
                </x-button>
            </div>
        </form>
    </div>
</x-modal>
