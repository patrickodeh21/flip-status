{{-- resources/views/rooms/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
            <h2 class="text-lg sm:text-xl font-semibold break-words min-w-0 flex-1">Rooms — {{ $property->name }}</h2>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                <x-button variant="secondary" href="{{ route('properties.index') }}" class="w-full sm:w-auto whitespace-nowrap">← Back to Properties</x-button>
                @role('admin|owner|company')
                    <x-button variant="secondary" href="{{ route('properties.property-tasks.index', $property) }}" class="w-full sm:w-auto whitespace-nowrap">
                        Property Tasks
                    </x-button>
                    <x-button variant="primary" @click="$dispatch('open-preview-panel', 'add-room-{{ $property->id }}')" class="w-full sm:w-auto whitespace-nowrap">
                        + Add Room
                    </x-button>
                @endrole
            </div>
        </div>
    </x-slot>

    @php
        $orderUrl = route('properties.rooms.order', $property);
    @endphp

    <div x-data="roomsList({ orderUrl: @js($orderUrl), csrf: @js(csrf_token()) })" class="space-y-4">
        <div class="mb-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-3">
            <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">
                Drag the <span
                    class="inline-flex items-center px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-800">⋮⋮</span> handle
                to reorder rooms. This order is saved per property.
            </p>

            @role('admin|owner|company')
                <div class="min-h-[28px]">
                    <span x-show="status === 'saving'" x-cloak
                        class="inline-flex items-center gap-2 text-xs px-2 py-1 rounded bg-amber-100 text-amber-800 dark:bg-amber-400/20 dark:text-amber-300">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="3"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3A5 5 0 007 12H4z"></path>
                        </svg>
                        Saving…
                    </span>

                    <span x-show="status === 'saved'" x-cloak
                        class="inline-flex items-center gap-2 text-xs px-2 py-1 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-400/20 dark:text-emerald-300">
                        ✓ Saved at <span x-text="savedAt"></span>
                    </span>

                    <span x-show="status === 'error'" x-cloak
                        class="inline-flex items-center gap-2 text-xs px-2 py-1 rounded bg-rose-100 text-rose-800 dark:bg-rose-400/20 dark:text-rose-300">
                        ⚠︎ <span x-text="errorMsg"></span>
                    </span>
                </div>
            @endrole
        </div>

        {{-- Mobile Card View --}}
        <div x-ref="mobileList" class="md:hidden space-y-3">
            @forelse($rooms as $r)
                <div data-room-id="{{ $r->id }}" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            @role('admin|owner|company')
                                <button type="button"
                                    class="drag-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 flex-shrink-0"
                                    title="Drag to reorder" aria-label="Drag to reorder">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M7 6a1 1 0 11-2 0 1 1 0 012 0zm4 0a1 1 0 102 0 1 1 0 00-2 0zM7 10a1 1 0 11-2 0 1 1 0 012 0zm4 0a1 1 0 102 0 1 1 0 00-2 0zM7 14a1 1 0 11-2 0 1 1 0 012 0zm4 0a1 1 0 102 0 1 1 0 00-2 0z" />
                                    </svg>
                                </button>
                            @endrole
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $r->name }}</h3>
                                <div class="flex items-center gap-3 mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    <span>{{ $r->tasks_count }} tasks</span>
                                    @role('admin')
                                    @if($r->is_default)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-green-100 text-green-800 dark:bg-green-400/20 dark:text-green-300">
                                            Default
                                        </span>
                                    @endif
                                    @endrole
                                </div>
                            </div>
                        </div>
                        <div class="flex-shrink-0">
                            @include('properties.rooms.__room_action', [
                                'property' => $property,
                                'room' => $r,
                            ])
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 text-center text-gray-500">
                    No rooms yet — add your first one.
                </div>
            @endforelse
        </div>

        {{-- Desktop Table View --}}
        <x-card class="!px-0 overflow-hidden hidden md:block">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="uppercase text-xs tracking-wide sticky top-0 z-10">
                        <tr class="text-gray-600 dark:text-gray-300">
                            @role('admin|owner|company')
                                <th class="px-3 py-2 w-10"></th>
                            @endrole
                            <th class="px-4 py-2 text-left">Name</th>
                            @role('admin')
                            <th class="px-4 py-2 text-center">Default?</th>
                            @endrole
                            <th class="px-4 py-2 text-center">Tasks</th>
                            <th class="px-4 py-2 w-48 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody x-ref="tbody" id="rooms-tbody" class="divide-y dark:divide-gray-700">
                        @forelse($rooms as $r)
                            <tr data-room-id="{{ $r->id }}" class="hover:bg-gray-100 dark:hover:bg-gray-900">
                                @role('admin|owner|company')
                                    <td class="px-3 py-2 align-middle">
                                        <button type="button"
                                            class="drag-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                            title="Drag to reorder" aria-label="Drag to reorder">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                                fill="currentColor">
                                                <path
                                                    d="M7 6a1 1 0 11-2 0 1 1 0 012 0zm4 0a1 1 0 102 0 1 1 0 00-2 0zM7 10a1 1 0 11-2 0 1 1 0 012 0zm4 0a1 1 0 102 0 1 1 0 00-2 0zM7 14a1 1 0 11-2 0 1 1 0 012 0zm4 0a1 1 0 102 0 1 1 0 00-2 0z" />
                                            </svg>
                                        </button>
                                    </td>
                                @endrole

                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                    {{ $r->name }}
                                </td>
                                @role('admin')
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs
                                        {{ $r->is_default
                                            ? 'bg-green-100 text-green-800 dark:bg-green-400/20 dark:text-green-300'
                                            : 'bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-400' }}">
                                        {{ $r->is_default ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                @endrole
                                <td class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">
                                    {{ $r->tasks_count }}
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @include('properties.rooms.__room_action', [
                                        'property' => $property,
                                        'room' => $r,
                                    ])
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-10 text-center text-gray-500 dark:text-gray-400"
                                    @if(auth()->user()->hasAnyRole(['admin', 'owner'])) colspan="5" @else colspan="4" @endif>
                                    No rooms yet — add your first one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    {{-- Add Room Preview Panel --}}
    @role('admin|owner|company')
        <x-preview-panel
            name="add-room-{{ $property->id }}"
            :overlay="true"
            side="right"
            initialWidth="32rem"
            minWidth="24rem"
            title="Add Room"
            :subtitle="'Add a new room to ' . $property->name">

            @php $suggestUrl = route('rooms.suggest'); @endphp

            @include('properties.rooms.__room_form', [
                'property' => $property,
                'room' => null,
                'suggestUrl' => $suggestUrl,
                'mode' => 'create',
            ])
        </x-preview-panel>

        {{-- Edit Room Preview Panels --}}
        @foreach($rooms as $r)
            <x-preview-panel
                name="edit-room-{{ $property->id }}-{{ $r->id }}"
                :overlay="true"
                side="right"
                initialWidth="32rem"
                minWidth="24rem"
                title="Edit Room"
                :subtitle="$r->name">

                @php $suggestUrl = route('rooms.suggest'); @endphp

                @include('properties.rooms.__room_form', [
                    'property' => $property,
                    'room' => $r,
                    'suggestUrl' => $suggestUrl,
                    'mode' => 'edit',
                ])
            </x-preview-panel>

            {{-- Bulk Add Tasks Preview Panel for each room --}}
            <x-preview-panel
                name="bulk-add-tasks-{{ $property->id }}-{{ $r->id }}"
                :overlay="true"
                side="right"
                initialWidth="32rem"
                minWidth="24rem"
                title="Bulk Add Tasks"
                :subtitle="'Quickly add multiple tasks to ' . $r->name . ' in ' . $property->name">

                @include('properties.tasks.__bulk_task_form', [
                    'property' => $property,
                    'room' => $r,
                ])
            </x-preview-panel>
        @endforeach
    @endrole
</x-app-layout>
