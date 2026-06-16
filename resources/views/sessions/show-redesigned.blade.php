@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Route;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100">
                    {{ $session->property->name }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    {{ $session->scheduled_date->format('F j, Y') }}
                </p>
            </div>
            @if($session->status !== 'pending')
                <x-status-badge :status="$session->status" />
            @endif
        </div>
    </x-slot>

    @php
        $dataUrl = Route::has('sessions.data')
            ? route('sessions.data', ['session' => $session->id])
            : route('api.sessions.data', ['session' => $session->id]);

        $reportUrl = Route::has('reports.sessions.show')
            ? route('reports.sessions.show', ['token' => $session->report_token])
            : null;
    @endphp

    @php
        // Build a flat, grouped list of all "instructions"-type tasks for the global Instructions modal
        $allInstructionItems = collect();

        $collectPropertyInstructions = function ($tasks, $sectionLabel) use (&$allInstructionItems) {
            foreach ($tasks->where('type', 'instructions') as $task) {
                $text = $task->instructions ?? null;
                if (!empty($text)) {
                    $allInstructionItems->push([
                        'section' => $sectionLabel,
                        'title'   => $task->name,
                        'body'    => $text,
                    ]);
                }
            }
        };

        $collectPropertyInstructions($preCleaningTasks ?? collect(), 'Pre-Cleaning');
        $collectPropertyInstructions($duringCleaningTasks ?? collect(), 'During Cleaning');
        $collectPropertyInstructions($postCleaningTasks ?? collect(), 'Post-Cleaning');

        foreach (($rooms ?? collect()) as $room) {
            $roomTasks = $roomTasksByRoom[$room->id] ?? collect();
            foreach ($roomTasks->where('type', 'instructions') as $task) {
                $text = $task->pivot->instructions ?? $task->instructions ?? null;
                if (!empty($text)) {
                    $allInstructionItems->push([
                        'section' => $room->name,
                        'title'   => $task->name,
                        'body'    => $text,
                    ]);
                }
            }
        }

        $groupedInstructions = $allInstructionItems->groupBy('section');
    @endphp
    <div x-data="checklist({ dataUrl: @js($dataUrl) })" x-init="init()" data-session-id="{{ $session->id }}" data-report-url="{{ $reportUrl }}" class="space-y-6">
        {{-- Notification Toast --}}
        <div 
            x-show="success || error"
            x-cloak
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            class="fixed top-4 right-4 z-50 max-w-sm w-full"
        >
            <div 
                x-show="success"
                class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 shadow-lg"
            >
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-sm font-medium text-green-800 dark:text-green-200" x-text="success"></p>
                </div>
            </div>
            <div 
                x-show="error"
                class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 shadow-lg"
            >
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-sm font-medium text-red-800 dark:text-red-200" x-text="error"></p>
                </div>
            </div>
        </div>

        {{-- View-only notice for housekeepers --}}
        @if (isset($isViewOnly) && $isViewOnly)
            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <p class="font-medium text-amber-800 dark:text-amber-200">View Only Mode</p>
                        <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">
                            This assignment is scheduled for {{ $session->scheduled_date->format('F j, Y') }}.
                            You can view the checklist, but you can only start working on the scheduled date when you're at the property location.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- PENDING: Start gate --}}
        @if ($session->status === 'pending')
            <x-card class="p-8">
                <div class="max-w-2xl mx-auto text-center">
                    @if (isset($isViewOnly) && $isViewOnly)
                        <div class="mb-6">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                <svg class="w-8 h-8 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">Session Not Available Yet</h3>
                            <p class="text-gray-600 dark:text-gray-400">
                                This assignment is scheduled for {{ $session->scheduled_date->format('F j, Y') }}.
                                You can start working on the scheduled date when you're at the property location.
                            </p>
                        </div>
                        <x-button disabled size="lg">Start Session</x-button>
                    @else
                        <div class="mb-6">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">Ready to Start</h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-4">
                                Please ensure you're at the property location. GPS will be used to verify your location.
                            </p>
                        </div>

                        <form method="post" action="{{ route('sessions.start', $session) }}" id="gps-start">
                            @csrf
                            <x-form.input type="hidden" name="latitude" id="lat" />
                            <x-form.input type="hidden" name="longitude" id="lng" />
                            <x-button id="start-btn" size="lg" class="w-full sm:w-auto">Start Session</x-button>
                            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400" id="location-status">
                                Checking location...
                            </p>
                        </form>

                        @error('gps')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    @endif
                </div>
            </x-card>

            {{-- GPS capture and location verification --}}
            @if (!isset($isViewOnly) || !$isViewOnly)
                @include('sessions.partials.gps-script', [
                    'propertyLat' => $session->property->latitude,
                    'propertyLng' => $session->property->longitude,
                    'propertyRadius' => $session->property->geo_radius_m ?? 100
                ])
            @endif
        @else
            {{-- PROGRESS HEADER --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <x-status-badge :status="$session->status" />
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                Started: {{ optional($session->started_at)->format('M j, Y g:i A') ?? '—' }}
                            </p>
                            @if ($session->gps_confirmed_at)
                                <span class="inline-flex items-center gap-1 text-xs text-green-600 dark:text-green-400 mt-1">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    GPS Confirmed
                                </span>
                            @endif
                        </div>
                    </div>
                    @if($groupedInstructions->isNotEmpty())
                        <div x-data="{ instructionsModalOpen: false }" class="relative">
                            <button
                                type="button"
                                @click="instructionsModalOpen = true"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200 border border-amber-300 dark:border-amber-700 hover:bg-amber-200 dark:hover:bg-amber-900/50 transition-colors"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s4.332.477 5.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                                Instructions
                            </button>

                            {{-- Instructions Modal --}}
                            <div
                                x-show="instructionsModalOpen"
                                x-cloak
                                @click.self="instructionsModalOpen = false"
                                @keydown.escape.window="instructionsModalOpen = false"
                                class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
                            >
                                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-2xl w-full max-h-[85vh] flex flex-col" @click.stop>
                                    <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Instructions</h3>
                                        <button type="button" @click="instructionsModalOpen = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="p-6 overflow-y-auto space-y-6">
                                        @foreach($groupedInstructions as $sectionName => $items)
                                            <div>
                                                <h4 class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">{{ $sectionName }}</h4>
                                                <div class="space-y-4">
                                                    @foreach($items as $item)
                                                        <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4">
                                                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">{{ $item['title'] }}</p>
                                                            <div class="prose dark:prose-invert prose-sm max-w-none text-gray-600 dark:text-gray-400 leading-relaxed">
                                                                {!! nl2br(e($item['body'])) !!}
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Current Stage:</span>
                        @if ($stage === 'summary')
                            <a href="{{ $reportUrl }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="inline-flex items-center rounded-lg bg-indigo-50 dark:bg-indigo-500/10 px-3 py-2 text-sm font-medium text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-500/20">
                                View Report
                            </a>
                        @else
                            <span class="px-3 py-1 rounded-lg text-sm font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200">
                                {{ ucwords(str_replace('_', ' ', $stage)) }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- PRE-CLEANING STAGE --}}
            @if ($stage === 'pre_cleaning')
                <x-card class="p-6">
                    <x-checklist.stage-header 
                        title="Pre-Cleaning Tasks"
                        :current="$checkedPreCleaningCount"
                        :total="$preCleaningCount"
                        description="Complete these tasks before starting the cleaning process"
                    />
                    <div class="space-y-3">
                        @forelse ($preCleaningTasks as $task)
                            @php
                                $item = $session->checklistItems->first(
                                    fn($ci) => $ci->room_id === null && (int) $ci->task_id === (int) $task->id,
                                );
                            @endphp
                            <x-checklist.task-item 
                                :task="$task"
                                :item="$item"
                                :session="$session"
                                :disabled="isset($isViewOnly) && $isViewOnly"
                                :completed="(bool) ($item && $item->checked)"
                            />
                        @empty
                            <p class="text-center text-gray-500 dark:text-gray-400 py-8">No pre-cleaning tasks defined.</p>
                        @endforelse
                    </div>
                </x-card>
            @endif

            {{-- ROOMS STAGE --}}
            @if ($stage === 'rooms')
                <div class="space-y-6">
                    @foreach ($rooms as $index => $room)
                        @php
                            $tasks = $roomTasksByRoom[$room->id] ?? collect();
                            // Allow skipping between rooms - only disable for view-only mode
                            $disabled = isset($isViewOnly) && $isViewOnly;
                            $roomCheckedCount = ChecklistItem::where('session_id', $session->id)
                                ->where('room_id', $room->id)
                                ->whereIn('task_id', $tasks->pluck('id'))
                                ->where('checked', true)
                                ->count();
                        @endphp
                        <x-card class="p-6">
                            <div class="mb-6">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $room->name }}</h3>
                                </div>
                                <x-checklist.progress-bar 
                                    :current="$roomCheckedCount"
                                    :total="$tasks->count()"
                                    label="Room Tasks Progress"
                                    color="blue"
                                />
                            </div>
                            <div class="space-y-3">
                                @forelse ($tasks as $task)
                                    @php
                                        $item = $session->checklistItems->first(
                                            fn($ci) => (int) $ci->room_id === (int) $room->id && (int) $ci->task_id === (int) $task->id,
                                        );
                                    @endphp
                                    <x-checklist.task-item 
                                        :task="$task"
                                        :item="$item"
                                        :session="$session"
                                        :room="$room"
                                        :disabled="$disabled"
                                        :completed="(bool) ($item && $item->checked)"
                                    />
                                @empty
                                    <p class="text-center text-gray-500 dark:text-gray-400 py-4">No tasks defined for this room.</p>
                                @endforelse
                            </div>
                        </x-card>
                    @endforeach
                </div>
            @endif

            {{-- DURING-CLEANING STAGE --}}
            @if ($stage === 'during_cleaning')
                <x-card class="p-6">
                    <x-checklist.stage-header 
                        title="During-Cleaning Tasks"
                        :current="$checkedDuringCleaningCount"
                        :total="$duringCleaningCount"
                        description="Tasks to complete while cleaning"
                    />
                    <div class="space-y-3">
                        @forelse ($duringCleaningTasks as $task)
                            @php
                                $item = $session->checklistItems->first(
                                    fn($ci) => $ci->room_id === null && (int) $ci->task_id === (int) $task->id,
                                );
                            @endphp
                            <x-checklist.task-item 
                                :task="$task"
                                :item="$item"
                                :session="$session"
                                :disabled="isset($isViewOnly) && $isViewOnly"
                                :completed="(bool) ($item && $item->checked)"
                            />
                        @empty
                            <p class="text-center text-gray-500 dark:text-gray-400 py-8">No during-cleaning tasks defined.</p>
                        @endforelse
                    </div>
                </x-card>
            @endif

            {{-- POST-CLEANING STAGE --}}
            @if ($stage === 'post_cleaning')
                <x-card class="p-6">
                    <x-checklist.stage-header 
                        title="Post-Cleaning Tasks"
                        :current="$checkedPostCleaningCount"
                        :total="$postCleaningCount"
                        description="Final tasks to complete after cleaning"
                    />
                    <div class="space-y-3">
                        @forelse ($postCleaningTasks as $task)
                            @php
                                $item = $session->checklistItems->first(
                                    fn($ci) => $ci->room_id === null && (int) $ci->task_id === (int) $task->id,
                                );
                            @endphp
                            <x-checklist.task-item 
                                :task="$task"
                                :item="$item"
                                :session="$session"
                                :disabled="isset($isViewOnly) && $isViewOnly"
                                :completed="(bool) ($item && $item->checked)"
                            />
                        @empty
                            <p class="text-center text-gray-500 dark:text-gray-400 py-8">No post-cleaning tasks defined.</p>
                        @endforelse
                    </div>
                </x-card>
            @endif

            {{-- INVENTORY STAGE --}}
            @if ($stage === 'inventory')
                <div class="space-y-6">
                    @foreach ($rooms as $index => $room)
                        @php
                            $tasks = $inventoryTasksByRoom[$room->id] ?? collect();
                            // Allow skipping between rooms - only disable for view-only mode
                            $disabled = isset($isViewOnly) && $isViewOnly;
                            $inventoryCheckedCount = ChecklistItem::where('session_id', $session->id)
                                ->where('room_id', $room->id)
                                ->whereIn('task_id', $tasks->pluck('id'))
                                ->where('checked', true)
                                ->count();
                        @endphp
                        @if ($tasks->count())
                            <x-card class="p-6">
                                <div class="mb-6">
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $room->name }} — Inventory</h3>
                                    </div>
                                    <x-checklist.progress-bar 
                                        :current="$inventoryCheckedCount"
                                        :total="$tasks->count()"
                                        label="Inventory Progress"
                                        color="purple"
                                    />
                                </div>
                                <div class="space-y-3">
                                    @foreach ($tasks as $task)
                                        @php
                                            $item = $session->checklistItems->first(
                                                fn($ci) => (int) $ci->room_id === (int) $room->id && (int) $ci->task_id === (int) $task->id,
                                            );
                                        @endphp
                                        <x-checklist.task-item 
                                            :task="$task"
                                            :item="$item"
                                            :session="$session"
                                            :room="$room"
                                            :disabled="$disabled"
                                            :completed="(bool) ($item && $item->checked)"
                                        />
                                    @endforeach
                                </div>
                            </x-card>
                        @endif
                    @endforeach
                </div>
            @endif

            {{-- PHOTOS STAGE --}}
            @if ($stage === 'photos')
                <div class="space-y-6">
                    @foreach ($rooms as $room)
                        @php 
                            $roomPhotos = $photosByRoom[$room->id] ?? collect();
                            $photoCount = $photoCounts[$room->id] ?? 0;
                        @endphp
                        <x-card class="p-6" data-room-photos>
                            <div class="mb-6">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $room->name }} — Photos</h3>
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400" data-photo-count>
                                        {{ $photoCount }}/8 photos
                                    </span>
                                </div>
                                <x-checklist.progress-bar 
                                    :current="$photoCount"
                                    :total="8"
                                    label="Photo Progress"
                                    color="green"
                                />
                            </div>

                            <form
                                method="post"
                                enctype="multipart/form-data"
                                action="{{ route('photos.store', [$session, $room->id]) }}"
                                data-checklist-photo-form
                                data-room-id="{{ $room->id }}"
                                class="mb-6"
                            >
                                @csrf
                                <div class="flex items-center gap-3">
                                    <input
                                        type="file"
                                        name="photos[]"
                                        accept="image/*"
                                        capture="environment"
                                        class="flex-1 block w-full text-sm text-gray-500 dark:text-gray-400
                                            file:mr-4 file:py-2 file:px-4
                                            file:rounded-lg file:border-0
                                            file:text-sm file:font-medium
                                            file:bg-blue-50 file:text-blue-700
                                            hover:file:bg-blue-100
                                            dark:file:bg-blue-900/30 dark:file:text-blue-400"
                                    />
                                    <x-button type="submit" :disabled="isset($isViewOnly) && $isViewOnly">
                                        Upload Photos
                                    </x-button>
                                </div>
                                <div data-upload-progress class="hidden mt-2"></div>
                            </form>

                            @if ($roomPhotos->count())
                                <div data-photo-gallery class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                    @foreach ($roomPhotos as $photo)
                                        @php
                                            $src = Str::startsWith($photo->path, ['http://', 'https://'])
                                                ? $photo->path
                                                : url('file/' . ltrim($photo->path, '/'));
                                        @endphp
                                        <div 
                                            x-data="{ open: false }"
                                            class="relative group"
                                        >
                                            <button 
                                                type="button" 
                                                @click="open = true"
                                                class="w-full"
                                            >
                                                <img 
                                                    src="{{ $src }}" 
                                                    alt="Photo"
                                                    class="aspect-square w-full object-cover rounded-xl border transition hover:opacity-90" 
                                                    onerror="this.src='https://placehold.co/400x300?text=Photo+Missing'; this.onerror=null;"
                                                />
                                                <span class="absolute bottom-1 right-1 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white">
                                                    {{ optional($photo->captured_at)->format('H:i') }}
                                                </span>
                                            </button>
                                            <div 
                                                x-show="open"
                                                x-cloak
                                                @click.self="open = false"
                                                @keydown.escape.window="open = false"
                                                class="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4"
                                            >
                                                <img 
                                                    src="{{ $src }}" 
                                                    class="max-h-[90vh] max-w-[90vw] rounded-lg shadow-2xl"
                                                    alt="Photo preview"
                                                />
                                                <button 
                                                    type="button" 
                                                    @click="open = false"
                                                    class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300"
                                                >
                                                    ×
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-center text-gray-500 dark:text-gray-400 py-8">No photos uploaded yet.</p>
                            @endif
                        </x-card>
                    @endforeach

                    <x-card class="p-6">
                        <form method="post" action="{{ route('sessions.complete', $session) }}" class="text-center">
                            @csrf
                            <x-button 
                                size="lg" 
                                :disabled="isset($isViewOnly) && $isViewOnly"
                                class="w-full sm:w-auto"
                            >
                                Submit Checklist
                            </x-button>
                            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                                Requires ≥8 photos per room. Timestamp overlay is automatic on upload.
                            </p>
                        </form>
                    </x-card>
                </div>
            @endif

            {{-- SUMMARY STAGE --}}
            @if ($stage === 'summary')
                @include('sessions.partials.summary', [
                    'session' => $session,
                    'rooms' => $rooms,
                    'roomTasksByRoom' => $roomTasksByRoom,
                    'inventoryTasksByRoom' => $inventoryTasksByRoom,
                    'preCleaningTasks' => $preCleaningTasks,
                    'duringCleaningTasks' => $duringCleaningTasks,
                    'postCleaningTasks' => $postCleaningTasks,
                    'photosByRoom' => $photosByRoom,
                    'photoCounts' => $photoCounts,
                    'isViewOnly' => $isViewOnly ?? false,
                ])
            @endif
        @endif
    </div>
</x-app-layout>
