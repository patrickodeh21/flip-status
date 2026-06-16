<x-app-layout>
    @php
        $dateFormat = \App\Models\Setting::get('date_format', 'M d, Y');
    @endphp
    <x-slot name="header">
        <h2 class="text-lg sm:text-xl font-semibold">Manage Assignment</h2>
    </x-slot>

    <x-card class="mb-4">
        <form method="get" class="space-y-4 px-1 sm:px-0">
            <div class="flex flex-wrap gap-4">
                <div class="w-full sm:w-auto sm:flex-1 sm:min-w-[140px]">
                    <x-form.label value="Property" />
                    <x-form.select name="property_id" class="!py-1 w-full rounded border-gray-300">
                        <option value="">All</option>
                        @foreach ($properties as $p)
                            <option value="{{ $p->id }}" @selected($filters['property_id'] == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </x-form.select>
                </div>
                <div class="w-full sm:w-auto sm:flex-1 sm:min-w-[140px]">
                    <x-form.label value="Housekeeper" />
                    <x-form.select name="housekeeper_id" class="!py-1 w-full rounded border-gray-300">
                        <option value="">All</option>
                        @foreach ($housekeepers as $hk)
                            <option value="{{ $hk->id }}" @selected($filters['housekeeper_id'] == $hk->id)>{{ $hk->name }}</option>
                        @endforeach
                    </x-form.select>
                </div>
                <div class="w-full sm:w-auto sm:flex-1 sm:min-w-[120px]">
                    <x-form.label value="Status" />
                    <x-form.select name="status" class="!py-1 w-full rounded border-gray-300">
                        <option value="">All</option>
                        @foreach (['pending', 'in_progress', 'completed'] as $st)
                            <option value="{{ $st }}" @selected($filters['status'] === $st)>
                                {{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                        @endforeach
                    </x-form.select>
                </div>
                <div class="w-[calc(50%-0.5rem)] sm:w-auto sm:flex-1 sm:min-w-[140px]">
                    <x-form.label value="From" />
                    <x-form.input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full" />
                </div>
                <div class="w-[calc(50%-0.5rem)] sm:w-auto sm:flex-1 sm:min-w-[140px]">
                    <x-form.label value="To" />
                    <x-form.input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full" />
                </div>
            </div>
            <div class="flex items-center gap-2">
                <x-button class="whitespace-nowrap">Filter</x-button>
                <x-button href="{{ route('manage.sessions.create') }}" class="whitespace-nowrap">New Assignment</x-button>
            </div>
        </form>
    </x-card>

    {{-- Mobile Card View --}}
    <div class="md:hidden space-y-3">
        @forelse($sessions as $s)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                            {{ $s->property->name }}
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {{ $s->scheduled_date->format($dateFormat) }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            <span class="font-medium">Housekeeper:</span> {{ $s->housekeeper?->name ?? '—' }}
                        </p>
                    </div>
                    <div class="flex-shrink-0">
                        <x-status-badge :status="$s->status" />
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-3 flex-wrap">
                        <a href="{{ route('sessions.show', $s) }}" class="text-sm text-green-600 dark:text-green-400 font-medium">
                            Open
                        </a>
                        <a href="{{ route('manage.sessions.edit', $s) }}" class="text-sm text-indigo-600 dark:text-indigo-400 font-medium">
                            Edit
                        </a>
                        <form method="post" action="{{ route('manage.sessions.destroy', $s) }}" class="inline"
                            onsubmit="return confirm('Delete assignment?')">
                            @csrf @method('delete')
                            <button class="text-sm text-red-600 dark:text-red-400 font-medium">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 text-center text-gray-500">
                No assignments
            </div>
        @endforelse
    </div>

    {{-- Desktop Table View --}}
    <x-card class="!px-0 hidden md:block">
        <table class="min-w-full text-sm">
            <thead class="uppercase">
                <tr>
                    <th class="px-4 py-2 text-left">Date</th>
                    <th class="px-4 py-2 text-left">Property</th>
                    <th class="px-4 py-2 text-left">Housekeeper</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2 w-40">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y dark:divide-gray-700">
                @forelse($sessions as $s)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3">{{ $s->scheduled_date->format($dateFormat) }}</td>
                        <td class="px-4 py-3 truncate font-medium">{{ $s->property->name }}</td>
                        <td class="px-4 py-3">{{ $s->housekeeper?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center"><x-status-badge :status="$s->status" /></td>
                        <td class="px-4 py-3 flex justify-end">
                            <a href="{{ route('sessions.show', $s) }}"
                                class="text-green-600 hover:underline">Open</a>
                            <span class="mx-2">·</span>

                            <a href="{{ route('manage.sessions.edit', $s) }}"
                                class="text-indigo-600 hover:underline">Edit</a>
                            <span class="mx-2">·</span>

                            <form method="post" action="{{ route('manage.sessions.destroy', $s) }}" class="inline"
                                onsubmit="return confirm('Delete assignment?')">
                                @csrf @method('delete')
                                <button class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-4 py-6 text-center text-gray-500" colspan="5">No assignments</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $sessions->links() }}</div>
</x-app-layout>
