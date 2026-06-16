<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg sm:text-xl font-semibold leading-tight">Properties</h2>
    </x-slot>

    <div class="space-y-4 w-full max-w-full">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 px-1 sm:px-0">
            <form method="get" class="flex flex-col sm:flex-row flex-wrap gap-2 sm:gap-2 flex-1 min-w-0 w-full">
                <x-form.input name="q" type="text" :value="old('q', request('q'))" autofocus autocomplete="name"
                    placeholder="Search by name" class="w-full sm:flex-1 min-w-0 max-w-full" />

                <x-form.select name="owner_id" :selected="request('owner_id')"
                    class="w-full sm:w-auto sm:min-w-[140px] sm:max-w-[200px] !py-1">
                    <option value="">All owners</option>
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}" @selected(request('owner_id') == $owner->id)>{{ $owner->name }}</option>
                    @endforeach
                </x-form.select>

                <x-button variant="secondary" class="w-full sm:w-auto whitespace-nowrap">Filter</x-button>
                <x-button variant="secondary" :href="route('properties.index')"
                    class="w-full sm:w-auto whitespace-nowrap">Clear</x-button>
            </form>

            @role('admin|owner|company')
                <x-button href="{{ route('properties.create') }}"
                    class="inline-flex items-center justify-center px-3 py-2 rounded bg-indigo-600 text-white w-full sm:w-auto whitespace-nowrap">+
                    New</x-button>
            @endrole
        </div>

        {{-- Mobile Card View --}}
        <div class="lg:hidden space-y-3">
            @forelse($properties as $property)
                @php
                    $photoUrl = method_exists($property, 'getPhotoUrlAttribute')
                        ? $property->photo_url
                        : ($property->photo_path
                            ? (Str::startsWith($property->photo_path, ['http://', 'https://'])
                                ? $property->photo_path
                                : url('file/' . ltrim($property->photo_path, '/')))
                            : asset('images/placeholders/property.png'));
                @endphp
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex gap-4">
                        <div class="flex-shrink-0">
                            <img src="{{ $photoUrl }}" class="h-16 w-16 rounded-xl object-cover" alt="Photo">
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                                {{ $property->name }}
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                {{ $property->owner->name }}
                            </p>
                            <div class="flex items-center gap-4 mt-2 text-sm text-gray-500 dark:text-gray-400">
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    {{ $property->rooms_count }} rooms
                                </span>
                            </div>
                            @if($property->address)
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">
                                    {{ $property->address }}
                                </p>
                            @endif
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('properties.rooms.index', $property) }}" class="text-sm text-indigo-600 dark:text-indigo-400 font-medium">
                                Rooms
                            </a>
                            <span class="text-gray-300 dark:text-gray-600">|</span>
                            <a href="{{ route('properties.property-tasks.index', $property) }}" class="text-sm text-indigo-600 dark:text-indigo-400 font-medium">
                                Tasks
                            </a>
                        </div>
                        <div class="flex-shrink-0">
                            @includeIf('properties.__property_action', [
                                'property' => $property,
                                'rooms' => $rooms,
                            ])
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 text-center text-gray-500">
                    No properties yet
                </div>
            @endforelse
        </div>

        {{-- Desktop Table View --}}
        <x-card class="!px-0 overflow-x-auto w-full hidden lg:block">
            <table class="min-w-full text-sm">
                <thead class="dark:bg-dark-eval-1">
                    <tr class="uppercase text-left">
                        <th class="px-4">Photo</th>
                        <th class="px-4">Name</th>
                        <th class="px-4">Owner</th>
                        <th class="px-4">Rooms Count</th>
                        <th class="px-4">Address</th>
                        <th class="px-4">Lat/Lng</th>
                        <th class="text-center px-4">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    @forelse($properties as $property)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3">
                                @php
                                    $photoUrl = method_exists($property, 'getPhotoUrlAttribute')
                                        ? $property->photo_url
                                        : ($property->photo_path
                                            ? (Str::startsWith($property->photo_path, ['http://', 'https://'])
                                                ? $property->photo_path
                                                : url('file/' . ltrim($property->photo_path, '/')))
                                            : asset('images/placeholders/property.png'));
                                @endphp
                                <img src="{{ $photoUrl }}" class="h-12 w-12 rounded-xl object-cover" alt="Photo">
                            </td>
                            <td class="px-4 py-3 font-medium">{{ $property->name }}</td>
                            <td class="py-3 font-medium">{{ $property->owner->name }}</td>
                            <td class="px-4 py-3">{{ $property->rooms_count }}</td>
                            <td class="px-4 py-3">{{ $property->address ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($property->latitude && $property->longitude)
                                    {{ number_format($property->latitude, 5) }},
                                    {{ number_format($property->longitude, 5) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @includeIf('properties.__property_action', [
                                    'property' => $property,
                                    'rooms' => $rooms,
                                ])
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-6 text-center text-gray-500" colspan="10">No properties yet</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>

        {{ $properties->links() }}

        {{-- Render panels ONCE per property (outside mobile/desktop split to avoid duplicates) --}}
        @foreach($properties as $property)
            @include('properties.__assign_rooms_panel', [
                'roomsForJs' => $rooms,
                'property' => $property,
                'attachedRoomIds' => $property->rooms->pluck('id')->toArray(),
            ])

            @role('admin|owner|company')
                @include('properties.__duplicate_property_panel', [
                    'property' => $property,
                ])
            @endrole
        @endforeach
    </div>
</x-app-layout>
