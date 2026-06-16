<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl">
                {{ $acting === 'housekeeper' ? 'My Schedule' : 'Cleaning Sessions Calendar' }}
            </h2>
            <div class="flex items-center gap-2">
                {{-- Role scope indicator (admin can act as owner via ?as=owner) --}}
                <span class="text-xs px-2 py-1 rounded border bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                    Scope: {{ ucfirst($acting) }}
                </span>
                <x-button variant="secondary"
                    href="{{ route('calendar.index', ['month' => $prevMonth] + request()->except('page')) }}"
                    class="!py-1">← Prev</x-button>
                <x-button variant="secondary" href="{{ route('calendar.index') }}" class="!py-1">Today</x-button>
                <x-button variant="secondary"
                    href="{{ route('calendar.index', ['month' => $nextMonth] + request()->except('page')) }}"
                    class="!py-1">Next →</x-button>
            </div>
        </div>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Calendar grid --}}
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div class="font-medium">
                        {{ $monthStart->format('F Y') }}
                    </div>
                    {{-- Quick legend --}}
                    <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-3">
                        <span class="inline-flex items-center gap-1"><span
                                class="h-2 w-2 rounded-full bg-indigo-600 inline-block"></span> Assigned</span>
                        @if($acting !== 'housekeeper')
                            <span class="inline-flex items-center gap-1"><span
                                    class="h-2 w-2 rounded-full bg-orange-500 inline-block"></span> Guest Checkout</span>
                        @endif
                    </div>
                </div>

                <div class="p-2">
                    {{-- Weekday headers - Start with Sunday --}}
                    <div class="grid grid-cols-7 text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                        @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $w)
                            <div class="px-2 py-2 text-center">{{ $w }}</div>
                        @endforeach
                    </div>

                    {{-- Days --}}
                    <div class="grid grid-cols-7 text-sm text-center">
                        @foreach ($days as $day)
                            @php
                                $dDate = $day['date'];
                                $isSel = $selectedDay === $dDate;
                                $hasSession = $day['sessionCount'] > 0;
                                $hasUnscheduled = ($day['unscheduledCount'] ?? 0) > 0;
                            @endphp
                            <a href="{{ route('calendar.index', ['month' => \Carbon\Carbon::parse($dDate)->format('Y-m'), 'day' => $dDate] + request()->except('page')) }}"
                                class="h-24 border -m-[0.5px] p-1 relative flex flex-col justify-between
                  {{ $day['inMonth'] ? 'bg-white dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-900/40' }}
                  {{ $day['isToday'] ? 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-300 dark:border-yellow-700' : '' }}
                  border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition
                  {{ $isSel ? 'ring-2 ring-indigo-600 z-10' : '' }}
                ">
                                <div class="flex items-start justify-between w-full">
                                    <span
                                        class="text-xs ml-1 mt-1 font-medium {{ $day['inMonth'] ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400' }} {{ $day['isToday'] ? 'font-bold text-yellow-700 dark:text-yellow-400' : '' }}">
                                        {{ \Carbon\Carbon::parse($dDate)->format('j') }}
                                    </span>
                                </div>
                                
                                <div class="flex flex-col gap-1 items-center w-full mb-1">
                                    @if ($hasSession)
                                        <div class="w-full px-1">
                                            <div class="bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 text-[10px] rounded px-1 py-0.5 truncate text-center">
                                                {{ $day['sessionCount'] }} task{{ $day['sessionCount'] > 1 ? 's' : '' }}
                                            </div>
                                        </div>
                                    @endif
                                    @if ($hasUnscheduled && $acting !== 'housekeeper')
                                        <div class="w-full px-1">
                                            <div class="bg-orange-100 dark:bg-orange-900/50 text-orange-700 dark:text-orange-300 text-[10px] rounded px-1 py-0.5 truncate text-center">
                                                {{ $day['unscheduledCount'] }} checkout{{ $day['unscheduledCount'] > 1 ? 's' : '' }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Day details --}}
        <div>
            <div class="bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 h-full flex flex-col">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                    <div class="font-medium text-lg">
                        {{ $selectedDay ? \Carbon\Carbon::parse($selectedDay)->toFormattedDateString() : 'Select a date' }}
                    </div>
                </div>
                <div class="p-4 flex-1 overflow-y-auto max-h-[calc(100vh-200px)]">
                    @if (!$selectedDay)
                        <div class="flex flex-col items-center justify-center h-full text-gray-500 text-center py-12">
                            <svg class="w-12 h-12 mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <p class="text-sm">Select a date on the calendar<br>to view details.</p>
                        </div>
                    @else
                    @if ($daySessions->isEmpty() && $dayUnscheduled->isEmpty())
                        <div class="text-center py-8 text-gray-500">
                            <p class="text-sm">No assignments or checkouts found.</p>
                        </div>
                    @else
                        {{-- Unscheduled Section --}}
                        @if($dayUnscheduled->isNotEmpty())
                            <div class="mb-6">
                                <h4 class="text-xs font-semibold text-orange-600 dark:text-orange-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-orange-500"></span>
                                    Pending Checkouts
                                </h4>
                                <div class="space-y-3">
                                    @foreach($dayUnscheduled as $u)
                                        <div class="bg-orange-50 dark:bg-orange-900/10 border border-orange-200 dark:border-orange-800 rounded-lg p-3">
                                             <div class="flex justify-between items-start mb-2">
                                                 <div>
                                                     <div class="font-medium text-gray-900 dark:text-gray-100 text-sm">{{ $u['property_name'] }}</div>
                                                     <div class="text-xs text-orange-800 dark:text-orange-300 mt-0.5 flex items-center justify-between">
                                                         <span>Guest: {{ $u['guest_name'] }}</span>
                                                         @if(!empty($u['source']))
                                                             <span class="px-1.5 py-0.5 rounded bg-orange-100 dark:bg-orange-800/30 text-[9px] font-bold uppercase">{{ $u['source'] }}</span>
                                                         @endif
                                                     </div>
                                                 </div>
                                             </div>
                                             <a href="{{ route('manage.sessions.create', [
                                                    'property_id' => $u['property_id'],
                                                    'date' => $u['date'],
                                                    'checkout_id' => $u['id']
                                                ]) }}" 
                                                class="block w-full text-center py-1.5 bg-orange-600 hover:bg-orange-700 text-white text-xs font-medium rounded transition">
                                                 Schedule Cleaning
                                             </a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif

                    @hasanyrole('admin|owner|company')
                         <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                             <x-button variant="secondary" href="{{ route('manage.sessions.create', ['date' => $selectedDay]) }}" class="w-full !text-xs">
                                 + Schedule New Session
                             </x-button>
                         </div>
                    @endhasanyrole

                            {{-- Assigned Section --}}
                            @if ($daySessions->isNotEmpty())
                                <div>
                                    <h4 class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                                        Scheduled Sessions
                                    </h4>
                                    <ul class="space-y-3">
                                        @foreach ($daySessions as $s)
                                            <li class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm rounded-lg p-3 hover:shadow-md transition group">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="font-medium text-gray-900 dark:text-gray-100 text-sm truncate">{{ $s->property->name }}</div>
                                                        <div class="flex flex-col gap-0.5 mt-1">
                                                            @if ($s->scheduled_time)
                                                                <div class="text-xs font-medium text-indigo-600 dark:text-indigo-400 flex items-center gap-1">
                                                                     <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                                     {{ \Carbon\Carbon::parse($s->scheduled_time)->format('g:i A') }}
                                                                </div>
                                                            @endif
                                                            @if ($acting !== 'housekeeper')
                                                                <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                                    To: {{ $s->housekeeper->name }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="flex flex-col items-end gap-2 ml-2">
                                                        <x-status-badge :status="$s->status" class="!text-[10px] !px-1.5 !py-0.5" />
                                                        <a href="{{ route('sessions.show', $s) }}"
                                                            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                                            View &rarr;
                                                        </a>
                                                    </div>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                    @endif
                </div>
            </div>


            {{-- Quick tips for HKs --}}
            @if ($acting === 'housekeeper')
                <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
                    Tip: Start a session from this list → GPS confirm → complete room tasks → inventory → upload ≥8
                    photos per room → submit.
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
