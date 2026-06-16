<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl">Command Center</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('calendar.index') }}"
                    class="px-3 py-2 rounded-md border border-gray-200 dark:border-gray-700 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">Open
                    Calendar</a>
                @hasanyrole('owner|admin')
                    <x-button href="{{ route('manage.sessions.index') }}" variant="primary" >
                        Manage Assignment
                    </x-button>
                @endhasanyrole
            </div>
        </div>
    </x-slot>

    @php
        $stats = $stats ?? [];
        $propertiesMini = $propertiesMini ?? collect(); // small list of properties with counts
        $upcomingSessions = $upcomingSessions ?? collect(); // upcoming sessions for acting role
        $recentSessions = $recentSessions ?? collect(); // recently completed sessions
        $hkTodaySessions = $hkTodaySessions ?? collect(); // housekeeper today list
    @endphp

    {{-- KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        @hasanyrole('admin|owner|company')
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                <a href="{{ route('properties.index') }}" class="block">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Properties</div>
                    <div class="text-2xl font-semibold mt-1">{{ data_get($stats, 'properties', 0) }}</div>
                </a>
            </div>
        @endhasanyrole


        @hasanyrole('admin|owner|company')
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                <div class="text-sm text-gray-500 dark:text-gray-400">Rooms</div>
                <div class="text-2xl font-semibold mt-1">{{ data_get($stats, 'rooms', 0) }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">across all properties</div>
            </div>
        @endhasanyrole
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
            <a href="{{ route('manage.sessions.index') }}" class="block">
                <div class="text-sm text-gray-500 dark:text-gray-400">Upcoming Sessions (7d)</div>
                <div class="text-2xl font-semibold mt-1">{{ data_get($stats, 'upcoming_7d', 0) }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1"><a href="{{ route('calendar.index') }}"
                        class="text-indigo-600 hover:underline">See calendar</a></div>
            </a>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
            <div class="text-sm text-gray-500 dark:text-gray-400">Completed (30d)</div>
            <div class="text-2xl font-semibold mt-1">{{ data_get($stats, 'completed_30d', 0) }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">housekeeping checklists</div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Upcoming list (role-aware) --}}
        <div class="lg:col-span-2">

            {{-- Unscheduled Checkouts Alert --}}
            @if(isset($unscheduledCheckouts) && $unscheduledCheckouts->isNotEmpty())
                <div class="mb-6 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 bg-orange-100 dark:bg-orange-900/40 border-b border-orange-200 dark:border-orange-800 flex items-center justify-between">
                        <div class="font-semibold text-orange-800 dark:text-orange-200 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            Action Required: Unscheduled Checkouts
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-orange-50 dark:bg-orange-900/20">
                                <tr class="text-left text-orange-800 dark:text-orange-200">
                                    <th class="px-4 py-2 font-medium">Checkout Date</th>
                                    <th class="px-4 py-2 font-medium">Property</th>
                                    <th class="px-4 py-2 font-medium text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-orange-100 dark:divide-orange-800/30">
                                @foreach($unscheduledCheckouts as $checkout)
                                    <tr>
                                        <td class="px-4 py-2 font-medium text-orange-900 dark:text-orange-100">
                                            {{ \Illuminate\Support\Carbon::parse($checkout['checkout_date'])->toFormattedDateString() }}
                                            @if(!empty($checkout['is_new']))
                                                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-white shadow-sm uppercase tracking-tighter animate-pulse">
                                                    New
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-orange-900 dark:text-orange-100">{{ $checkout['property_name'] }}</td>
                                        <td class="px-4 py-2 text-right">
                                            <a href="{{ route('manage.sessions.create', [
                                                    'property_id' => $checkout['property_id'],
                                                    'date' => $checkout['checkout_date']
                                                ]) }}"
                                               class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                                                Schedule Cleaning
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div class="font-semibold">Upcoming Sessions</div>
                    @hasanyrole('owner|admin')
                        <a href="{{ route('manage.sessions.create') }}" class="text-sm text-indigo-600 hover:underline">+
                            Schedule</a>
                    @endhasanyrole
                </div>

                @if ($upcomingSessions->isEmpty())
                    <div class="p-8 text-center text-sm text-gray-600 dark:text-gray-300">
                        No upcoming sessions. @hasanyrole('owner|admin')
                            <a href="{{ route('manage.sessions.create') }}" class="text-indigo-600 hover:underline">Create
                                one</a>.
                        @endhasanyrole
                    </div>
                @else
                    <div class="overflow-hidden">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800/50">
                                <tr class="text-left">
                                    <th class="px-4 py-2">Date</th>
                                    <th class="px-4 py-2">Property</th>
                                    <th class="px-4 py-2">Housekeeper</th>
                                    <th class="px-4 py-2">Status</th>
                                    <th class="px-4 py-2 w-24"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach ($upcomingSessions as $s)
                                    <tr>
                                        <td class="px-4 py-2">
                                            {{ \Illuminate\Support\Carbon::parse($s->scheduled_date)->toFormattedDateString() }}
                                        </td>
                                        <td class="px-4 py-2">{{ $s->property->name }}</td>
                                        <td class="px-4 py-2">{{ $s->housekeeper->name ?? '—' }}</td>
                                        <td class="px-4 py-2">
                                            <x-status-badge :status="$s->status" />
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <a href="{{ route('sessions.show', $s) }}"
                                                class="text-indigo-600 hover:underline">Open</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Recent activity (Hide for strictly housekeepers as requested) --}}
            @hasanyrole('admin|owner|company')
                <div class="mt-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-semibold">Recent Activity</div>
                    @if ($recentSessions->isEmpty())
                        <div class="p-8 text-center text-sm text-gray-600 dark:text-gray-300">No recent completions.</div>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($recentSessions as $s)
                                <li class="px-4 py-3 flex items-center justify-between">
                                    <div>
                                        <div class="font-medium">{{ $s->property->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ ucfirst($s->status) }} •
                                            {{ \Illuminate\Support\Carbon::parse($s->scheduled_date)->toFormattedDateString() }}
                                        </div>
                                    </div>
                                    <a href="{{ route('sessions.show', $s) }}"
                                        class="text-sm text-indigo-600 hover:underline">View</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endhasanyrole
        </div>

        {{-- Right: Role panels --}}
        <div class="space-y-6">
            {{-- Housekeeper: My Assignments Today --}}
            @role('housekeeper')
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-semibold">My Assignments
                    </div>
                    @if ($hkTodaySessions->isEmpty())
                        <div class="p-6 text-sm text-gray-600 dark:text-gray-300">
                            Nothing scheduled soon. <a href="{{ route('calendar.index') }}"
                                class="text-indigo-600 hover:underline">See full calendar</a>.
                        </div>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700 max-h-[600px] overflow-y-auto">
                            @foreach ($hkTodaySessions as $s)
                                <li class="px-4 py-3">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="font-medium">{{ $s->property->name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Due:
                                                {{ \Illuminate\Support\Carbon::parse($s->scheduled_date)->format('M j, Y') }}
                                            </div>
                                        </div>
                                        <a href="{{ route('sessions.show', $s) }}"
                                            class="px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">Open</a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endrole

            {{-- Owner/Admin/Company: Quick properties --}}
            @hasanyrole('owner|admin|company')
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <div class="font-semibold">Properties</div>
                        <a href="{{ route('properties.create') }}" class="text-sm text-indigo-600 hover:underline">+
                            New</a>
                    </div>

                    @if ($propertiesMini->isEmpty())
                        <div class="p-6 text-sm text-gray-600 dark:text-gray-300">No properties yet.</div>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($propertiesMini as $p)
                                <li class="px-4 py-3">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="font-medium">{{ $p->name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $p->rooms_count ?? '0' }} rooms</div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <a href="{{ route('properties.rooms.index', $p) }}"
                                                class="text-sm text-indigo-600 hover:underline">Rooms</a>
                                            <a href="{{ route('properties.edit', $p) }}"
                                                class="text-sm text-gray-600 dark:text-gray-300 hover:underline">Edit</a>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endhasanyrole

            {{-- Quick links --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-semibold">Quick Links</div>
                <div class="p-4 grid grid-cols-1 gap-2 text-sm">
                    <a href="{{ route('calendar.index') }}"
                        class="px-3 py-2 rounded-md border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">📅
                        Calendar</a>
                    @role('housekeeper')
                        <a href="{{ route('sessions.index') }}"
                            class="px-3 py-2 rounded-md border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">✅
                            My Assignments</a>
                    @endrole
                    @hasanyrole('owner|admin')
                        <a href="{{ route('manage.sessions.index') }}"
                            class="px-3 py-2 rounded-md border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">🗂
                            Manage Assignment</a>
                        <a href="{{ route('properties.index') }}"
                            class="px-3 py-2 rounded-md border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">🏠
                            Properties</a>
                    @endhasanyrole
                    @role('admin')
                        <a href="{{ route('activity.index') }}"
                            class="px-3 py-2 rounded-md border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">📋
                            Activity Log</a>
                    @endrole
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
