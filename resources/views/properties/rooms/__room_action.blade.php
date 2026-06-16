<x-action-dropdown align="right" width="w-56" label="Room actions">
    <x-dropdown.label>Manage</x-dropdown.label>

    {{-- Assign Default Tasks (opens bulk task panel) --}}
    @role('admin|owner|company')
        <x-dropdown.item as="button"
            x-on:click="
                $dispatch('open-preview-panel', 'bulk-add-tasks-{{ $property->id }}-{{ $room->id }}');
                $dispatch('dropdown-close');
            ">
            {{-- tasks icon --}}
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                <path
                    d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                <path fill-rule="evenodd"
                    d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                    clip-rule="evenodd" />
            </svg>
            <span>Assign Default Tasks</span>
        </x-dropdown.item>
    @endrole

    {{-- View/Edit Tasks --}}
    <x-dropdown.item href="{{ route('properties.tasks.index', ['property' => $property->id, 'room' => $room->id]) }}">
        {{-- list/edit tasks icon --}}
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
            <path d="M3 6h18v2H3V6zm0 5h18v2H3v-2zm0 5h18v2H3v-2z" />
        </svg>
        <span>@hasanyrole('admin|owner|company')Edit @else View @endrole Tasks</span>
    </x-dropdown.item>

    <x-dropdown.divider />

    @role('admin|owner|company')
        {{-- Edit Room --}}
        <x-dropdown.item as="button"
            x-on:click="
                $dispatch('open-preview-panel', 'edit-room-{{ $property->id }}-{{ $room->id }}');
                $dispatch('dropdown-close');
            ">
            {{-- edit icon --}}
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                <path
                    d="M3 17.25V21h3.75l11-11-3.75-3.75-11 11zM20.71 7.04a1.003 1.003 0 0 0 0-1.42L18.37 3.29a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.83z" />
            </svg>
            <span>Edit Room</span>
        </x-dropdown.item>
    @endrole
</x-action-dropdown>
