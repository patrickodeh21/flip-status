<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl">Add Room — {{ $property->name }}</h2>
            <div class="text-sm">
                <a href="{{ route('properties.rooms.index', $property) }}"
                    class="text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                    ← Back to Rooms
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $suggestUrl = route('rooms.suggest');
    @endphp

    <x-card>
        <form method="post" action="{{ route('properties.rooms.store', $property) }}" class="space-y-6">
            @csrf

            <div x-data="roomAutocomplete({ suggestUrl: @js($suggestUrl), csrf: @js(csrf_token()) })" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Autocomplete Name --}}
                <div class="relative">
                    <x-form.label for="name" value="Room Name" />
                    <div class="mt-1">
                        <input x-model="q" name="name" id="name" type="text" required autocomplete="off"
                            placeholder="e.g., Kitchen, Bedroom, Laundry"
                            class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                            @input="onInput" @focus="onFocus" @keydown="keyDown" aria-autocomplete="list"
                            aria-expanded="open" aria-controls="room-suggest" />
                    </div>

                    {{-- Dropdown --}}
                    <div x-cloak x-show="open"
                        class="absolute z-20 mt-1 w-full rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-lg overflow-hidden"
                        id="room-suggest" role="listbox">
                        {{-- Loading row --}}
                        <div x-show="loading" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                            Searching…
                        </div>

                        {{-- Results --}}
                        <template x-if="hasResults">
                            <ul class="max-h-60 overflow-y-auto">
                                <template x-for="(item, idx) in items" :key="item.id">
                                    <li class="px-3 py-2 cursor-pointer text-sm flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-800"
                                        :class="{ 'bg-gray-50 dark:bg-gray-800': focusedIndex === idx }" role="option"
                                        @mouseenter="focusedIndex = idx" @mouseleave="focusedIndex = -1"
                                        @click="choose(item)">
                                        <span x-text="item.name" class="text-gray-800 dark:text-gray-100"></span>
                                        <span x-show="item.is_default"
                                            class="ml-2 text-[10px] uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-400/20 dark:text-emerald-300 px-1.5 py-0.5 rounded">
                                            Default
                                        </span>
                                    </li>
                                </template>

                                {{-- "Create new" option at the end when query not in list --}}
                                <li class="px-3 py-2 cursor-pointer text-sm text-indigo-700 dark:text-indigo-300 bg-indigo-50/60 dark:bg-indigo-900/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/50"
                                    :class="{ 'ring-1 ring-indigo-400': focusedIndex === items.length }"
                                    @mouseenter="focusedIndex = items.length" @mouseleave="focusedIndex = -1"
                                    @click="open = false"
                                    x-show="q && !items.some(i => i.name.toLowerCase() === q.toLowerCase())">
                                    <span x-text="createLabel()"></span>
                                </li>
                            </ul>
                        </template>

                        {{-- No results + create --}}
                        <div x-show="!loading && !hasResults && q"
                            class="px-3 py-2 text-sm text-indigo-700 dark:text-indigo-300 bg-indigo-50/60 dark:bg-indigo-900/30">
                            Create "<span x-text="q"></span>"
                        </div>
                    </div>

                    @error('name')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror

                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Type to search existing room templates. If it doesn’t exist, we’ll create it on save and attach
                        it to this property.
                    </p>
                </div>

                {{-- Default toggle --}}
                @role('admin')
                <div class="pt-6">
                    <label class="inline-flex items-center gap-3">
                        <input id="is_default" type="checkbox" name="is_default" value="1"
                            class="rounded border-gray-300 dark:border-gray-700">
                        <span class="text-sm text-gray-700 dark:text-gray-200">Mark as default template</span>
                    </label>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Default rooms are highlighted in suggestions.
                    </p>
                </div>
                @endrole
            </div>

            <div class="flex gap-2">
                <x-button>Save</x-button>
                <x-button variant="secondary" href="{{ route('properties.rooms.index', $property) }}">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
