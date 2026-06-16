<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg sm:text-xl font-semibold">My Assignments</h2>
    </x-slot>

    {{-- Mobile Card View --}}
    <div class="md:hidden space-y-3 mb-4">
        @forelse($sessions as $s)
            <a href="{{ route('sessions.show', $s) }}" class="block">
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:border-indigo-300 dark:hover:border-indigo-600 transition-colors">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                                {{ $s->property->name }}
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                {{ $s->scheduled_date->toFormattedDateString() }}
                            </p>
                        </div>
                        <div class="flex-shrink-0">
                            <x-status-badge :status="$s->status" />
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-end">
                        <span class="text-sm text-indigo-600 dark:text-indigo-400 font-medium flex items-center gap-1">
                            Open
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </span>
                    </div>
                </div>
            </a>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 text-center text-gray-500">
                No sessions assigned
            </div>
        @endforelse
    </div>

    {{-- Desktop Table View --}}
    <x-card class="mb-4 !px-0 hidden md:block">
        <table class="min-w-full text-sm">
            <thead class="uppercase">
                <tr>
                    <th class="px-4 py-2 text-left">Date</th>
                    <th class="px-4 py-2 text-left">Property</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2 w-32"></th>
                </tr>
            </thead>
            <tbody class="divide-y dark:divide-gray-700">
                @forelse($sessions as $s)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3">{{ $s->scheduled_date->toFormattedDateString() }}</td>
                        <td class="px-4 py-3 font-medium">{{ $s->property->name }}</td>
                        <td class="px-4 py-3 text-center"><x-status-badge :status="$s->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('sessions.show', $s) }}" class="text-indigo-600 hover:underline font-medium">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-4 py-6 text-center text-gray-500" colspan="4">No sessions</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    {{ $sessions->links() }}
</x-app-layout>
