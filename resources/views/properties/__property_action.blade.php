<x-action-dropdown align="right" width="w-56" label="Property actions">
    <x-dropdown.label>Manage</x-dropdown.label>

    {{-- Add Rooms (opens modal) --}}
    @role('admin|owner|company')
        <x-dropdown.item as="button"
            x-on:click="
            $dispatch('open-preview-panel', 'duplicate-property-{{ $property->id }}');
            $dispatch('dropdown-close');
         ">
            {{-- copy icon --}}
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                <path
                    d="M8 7a3 3 0 0 1 3-3h7a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3h-7a3 3 0 0 1-3-3V7Zm3-1a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-7Z" />
                <path
                    d="M2 8a3 3 0 0 1 3-3h1a1 1 0 1 1 0 2H5a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1v-1a1 1 0 1 1 2 0v1a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3V8Z" />
            </svg>
            <span>Duplicate</span>
        </x-dropdown.item>

        <x-dropdown.item as="button"
            x-on:click="
            $dispatch('open-preview-panel', 'assign-rooms-{{ $property->id }}');
            $dispatch('dropdown-close');
         ">
            {{-- inline plus icon --}}
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                <path d=" M11 11V5a1 1 0 1 1 2 0v6h6a1 1 0 1 1 0 2h-6v6a1 1 0 1 1-2 0v-6H5a1 1 0 1 1 0-2h6z" />
            </svg>
            <span>Add Rooms</span>
        </x-dropdown.item>
    @endrole

    <x-dropdown.item href="{{ route('properties.rooms.index', ['property' => $property->id]) }}">
        {{-- rooms icon --}}
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
            <path
                d="M3 7a2 2 0 0 1 2-2h4v14H5a2 2 0 0 1-2-2V7zm12-2h4a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-4V5zM9 5h6v14H9V5z" />
        </svg>
        <span>Rooms</span>
    </x-dropdown.item>

    <x-dropdown.item href="{{ route('properties.property-tasks.index', ['property' => $property->id]) }}">
        {{-- tasks icon --}}
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
            <path
                d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
            <path fill-rule="evenodd"
                d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                clip-rule="evenodd" />
        </svg>
        <span>Property Tasks</span>
    </x-dropdown.item>

    <x-dropdown.divider />

    @role('admin|owner|company')
        <x-dropdown.item href="{{ route('properties.edit', $property) }}">
            {{-- edit icon --}}
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                <path
                    d="M3 17.25V21h3.75l11-11-3.75-3.75-11 11zM20.71 7.04a1.003 1.003 0 0 0 0-1.42L18.37 3.29a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.83z" />
            </svg>
            <span>Edit</span>
        </x-dropdown.item>

        <x-dropdown.item as="button"
            class="text-rose-600 dark:text-rose-400 hover:bg-rose-50/60 dark:hover:bg-rose-900/20"
            x-on:click="
                    $dispatch('open-modal', 'confirm-delete-property-{{ $property->id }}');
                    $dispatch('dropdown-close');
                ">
            {{-- trash icon --}}
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                <path
                    d="M9 3a1 1 0 0 0-1 1v1H4a1 1 0 1 0 0 2h1v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7h1a1 1 0 1 0 0-2h-4V4a1 1 0 0 0-1-1H9zm2 4a1 1 0 1 0-2 0v10a1 1 0 1 0 2 0V7zm4 0a1 1 0 1 0-2 0v10a1 1 0 1 0 2 0V7z" />
            </svg>
            <span>Delete</span>
        </x-dropdown.item>
    @endrole
</x-action-dropdown>

{{-- Panels are rendered once per property in the index view to avoid duplicates --}}

<x-modal name="confirm-delete-property-{{ $property->id }}" :show="false" maxWidth="md">
    <div class="p-6 text-left">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Delete
            Property
        </h3>
        <div class="mt-2 text-sm text-gray-600 dark:text-gray-300 text-wrap">
            You are about to permanently delete the property
            <strong>{{ $property->name }}</strong>.
            This will remove the property and its
            {{ $property->rooms_count ?? 'associated' }}
            rooms and related data and cannot be undone.
            Please confirm you want to proceed.
        </div>

        <div class="mt-6 flex items-center justify-end gap-2">
            <x-button variant="secondary" x-on:click="$dispatch('close')">Cancel</x-button>
            <form method="post" action="{{ route('properties.destroy', $property) }}">
                @csrf
                @method('DELETE')
                <x-button class="bg-rose-600 hover:bg-rose-700 focus:ring-rose-500">Delete</x-button>
            </form>
        </div>
    </div>
</x-modal>
