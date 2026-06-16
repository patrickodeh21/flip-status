{{-- resources/views/properties/rooms/edit.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl">
                Edit Room — {{ $property->name }}
            </h2>
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
        // You likely eager-load tasks_count in controller:
        // $room = $room->loadCount('tasks');
        // $sortOrder = $property->rooms()->where('rooms.id', $room->id)->first()->pivot->sort_order ?? null;
        $sortOrder = $sortOrder ?? ($room->pivot->sort_order ?? null);
    @endphp

    <x-card>
        <form method="post" action="{{ route('properties.rooms.update', [$property, $room]) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div
                x-data="roomAutocomplete({ suggestUrl: @js($suggestUrl), csrf: @js(csrf_token()) })"
                x-init="
                    // preload the current name
                    q = @js($room->name);
                "
                class="grid grid-cols-1 lg:grid-cols-3 gap-6"
            >
                {{-- Column 1: Identify / switch room by name --}}
                <div class="lg:col-span-2">
                    <div class="relative">
                        <x-form.label for="name" value="Room Name" />
                        <div class="mt-1">
                            <input
                                x-model="q"
                                name="name"
                                id="name"
                                type="text"
                                required
                                autocomplete="off"
                                placeholder="e.g., Kitchen, Bedroom, Laundry"
                                class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                @input="onInput"
                                @focus="onFocus"
                                @keydown="keyDown"
                                aria-autocomplete="list"
                                aria-expanded="open"
                                aria-controls="room-suggest"
                            />
                        </div>

                        {{-- Dropdown --}}
                        <div
                            x-cloak
                            x-show="open"
                            class="absolute z-20 mt-1 w-full rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-lg overflow-hidden"
                            id="room-suggest"
                            role="listbox"
                        >
                            <div x-show="loading" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                Searching…
                            </div>

                            <template x-if="hasResults">
                                <ul class="max-h-60 overflow-y-auto">
                                    <template x-for="(item, idx) in items" :key="item.id">
                                        <li
                                            class="px-3 py-2 cursor-pointer text-sm flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-800"
                                            :class="{'bg-gray-50 dark:bg-gray-800': focusedIndex === idx}"
                                            role="option"
                                            @mouseenter="focusedIndex = idx"
                                            @mouseleave="focusedIndex = -1"
                                            @click="choose(item)"
                                        >
                                            <span x-text="item.name" class="text-gray-800 dark:text-gray-100"></span>
                                            <span x-show="item.is_default"
                                                  class="ml-2 text-[10px] uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-400/20 dark:text-emerald-300 px-1.5 py-0.5 rounded">
                                                Default
                                            </span>
                                        </li>
                                    </template>

                                    {{-- create-new option if not in list --}}
                                    <li
                                        class="px-3 py-2 cursor-pointer text-sm text-indigo-700 dark:text-indigo-300 bg-indigo-50/60 dark:bg-indigo-900/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/50"
                                        :class="{'ring-1 ring-indigo-400': focusedIndex === items.length}"
                                        @mouseenter="focusedIndex = items.length"
                                        @mouseleave="focusedIndex = -1"
                                        @click="open = false"
                                        x-show="q && !items.some(i => i.name.toLowerCase() === q.toLowerCase())"
                                    >
                                        <span x-text="createLabel()"></span>
                                    </li>
                                </ul>
                            </template>

                            <div x-show="!loading && !hasResults && q"
                                class="px-3 py-2 text-sm text-indigo-700 dark:text-indigo-300 bg-indigo-50/60 dark:bg-indigo-900/30">
                                Create "<span x-text="q"></span>"
                            </div>
                        </div>

                        @error('name')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror

                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Start typing to select an existing room template, or enter a new name to create one and attach it to this property.
                        </p>
                    </div>

                    @role('admin')
                    <div class="mt-4">
                        <label class="inline-flex items-center gap-3">
                            <input id="is_default" type="checkbox" name="is_default" value="1"
                                   @checked(old('is_default', $room->is_default))
                                   class="rounded border-gray-300 dark:border-gray-700">
                            <span class="text-sm text-gray-700 dark:text-gray-200">Mark as default template</span>
                        </label>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Default rooms are highlighted in suggestions.
                        </p>
                    </div>
                    @endrole
                </div>

                {{-- Column 2: Context / pivot info --}}
                <div class="bg-gray-50 dark:bg-gray-900/40 rounded-md p-4 h-full">
                    <div class="text-sm text-gray-700 dark:text-gray-200 font-semibold mb-2">Attachment</div>
                    <dl class="text-sm">
                        <div class="flex items-center justify-between py-2 border-b dark:border-gray-800">
                            <dt class="text-gray-500 dark:text-gray-400">Currently attached as</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $room->name }}</dd>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b dark:border-gray-800">
                            <dt class="text-gray-500 dark:text-gray-400">Tasks</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $room->tasks_count ?? $room->tasks()->count() }}</dd>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <dt class="text-gray-500 dark:text-gray-400">Sort order (this property)</dt>
                            <dd class="text-gray-900 dark:text-gray-100">
                                {{ $sortOrder ?? '—' }}
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex items-center justify-between gap-2">
                        <a href="{{ route('properties.tasks.index', ['property' => $property->id, 'room' => $room->id]) }}"
                           class="text-indigo-600 hover:underline dark:text-indigo-400 text-sm">
                            Manage tasks →
                        </a>

                        {{-- Detach action opens confirmation modal --}}
                        <button type="button"
                                class="text-rose-600 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300 text-sm underline"
                                @click="$dispatch('open-modal', 'confirm-detach')">
                            Detach from property
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex gap-2">
                <x-button>Save Changes</x-button>
                <x-button variant="secondary" href="{{ route('properties.rooms.index', $property) }}">Cancel</x-button>
            </div>
        </form>
    </x-card>

    {{-- Confirm Detach Modal (uses your modal component) --}}
    <x-modal name="confirm-detach" :show="false" maxWidth="md">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Detach Room</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                This will remove <span class="font-medium">{{ $room->name }}</span> from <span class="font-medium">{{ $property->name }}</span>.
                The room template itself will remain available globally. Tasks attached to this room in other properties are unaffected.
            </p>

            <div class="mt-6 flex items-center justify-end gap-2">
                <x-button variant="secondary" x-on:click="$dispatch('close')">Cancel</x-button>
                <form method="post" action="{{ route('properties.rooms.destroy', [$property, $room]) }}">
                    @csrf
                    @method('DELETE')
                    <x-button class="bg-rose-600 hover:bg-rose-700 focus:ring-rose-500">Detach</x-button>
                </form>
            </div>
        </div>
    </x-modal>
</x-app-layout>
