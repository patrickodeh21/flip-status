@php
    use Illuminate\Support\Str;
@endphp

@props([
    'session',
    'rooms',
    'roomTasksByRoom',
    'inventoryTasksByRoom',
    'preCleaningTasks',
    'duringCleaningTasks',
    'postCleaningTasks',
    'photosByRoom',
    'photoCounts',
    'isViewOnly' => false,
])

<div class="space-y-6">
    {{-- Property-Level Tasks Summary --}}
    @if ($preCleaningTasks->count() > 0 || $duringCleaningTasks->count() > 0 || $postCleaningTasks->count() > 0)
        <x-card class="p-6">
            <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-6">Property-Level Tasks</h3>
            <div class="space-y-6">
                {{-- Pre-Cleaning Tasks --}}
                @if ($preCleaningTasks->count() > 0)
                    <div>
                        <h4 class="font-semibold text-sm text-gray-700 dark:text-gray-300 mb-3">Pre-Cleaning Tasks</h4>
                        <div class="space-y-2">
                            @foreach ($preCleaningTasks as $task)
                                @php
                                    $item = $session->checklistItems->first(
                                        fn($ci) => $ci->room_id === null && (int) $ci->task_id === (int) $task->id,
                                    );
                                @endphp
                                <x-checklist.task-item 
                                    :task="$task"
                                    :item="$item"
                                    :session="$session"
                                    :disabled="$isViewOnly"
                                    :completed="(bool) ($item && $item->checked)"
                                />
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- During-Cleaning Tasks --}}
                @if ($duringCleaningTasks->count() > 0)
                    <div>
                        <h4 class="font-semibold text-sm text-gray-700 dark:text-gray-300 mb-3">During-Cleaning Tasks</h4>
                        <div class="space-y-2">
                            @foreach ($duringCleaningTasks as $task)
                                @php
                                    $item = $session->checklistItems->first(
                                        fn($ci) => $ci->room_id === null && (int) $ci->task_id === (int) $task->id,
                                    );
                                @endphp
                                <x-checklist.task-item 
                                    :task="$task"
                                    :item="$item"
                                    :session="$session"
                                    :disabled="$isViewOnly"
                                    :completed="(bool) ($item && $item->checked)"
                                />
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Post-Cleaning Tasks --}}
                @if ($postCleaningTasks->count() > 0)
                    <div>
                        <h4 class="font-semibold text-sm text-gray-700 dark:text-gray-300 mb-3">Post-Cleaning Tasks</h4>
                        <div class="space-y-2">
                            @foreach ($postCleaningTasks as $task)
                                @php
                                    $item = $session->checklistItems->first(
                                        fn($ci) => $ci->room_id === null && (int) $ci->task_id === (int) $task->id,
                                    );
                                @endphp
                                <x-checklist.task-item 
                                    :task="$task"
                                    :item="$item"
                                    :session="$session"
                                    :disabled="$isViewOnly"
                                    :completed="(bool) ($item && $item->checked)"
                                />
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </x-card>
    @endif

    {{-- Room Tasks Summary --}}
    @foreach ($rooms as $room)
        @php
            $roomTasks = $roomTasksByRoom[$room->id] ?? collect();
            $inventoryTasks = $inventoryTasksByRoom[$room->id] ?? collect();
        @endphp
        <x-card class="p-6">
            <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-6">{{ $room->name }}</h3>
            <div class="space-y-6">
                {{-- Room tasks --}}
                @if ($roomTasks->count())
                    <div>
                        <h4 class="font-semibold text-sm text-gray-700 dark:text-gray-300 mb-3">Room Tasks</h4>
                        <div class="space-y-2">
                            @foreach ($roomTasks as $task)
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
                                    :disabled="$isViewOnly"
                                    :completed="(bool) ($item && $item->checked)"
                                />
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Inventory tasks --}}
                @if ($inventoryTasks->count())
                    <div>
                        <h4 class="font-semibold text-sm text-gray-700 dark:text-gray-300 mb-3">Inventory Tasks</h4>
                        <div class="space-y-2">
                            @foreach ($inventoryTasks as $task)
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
                                    :disabled="$isViewOnly"
                                    :completed="(bool) ($item && $item->checked)"
                                />
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </x-card>
    @endforeach

    {{-- Photos summary --}}
    <x-card class="p-6">
        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-6">Photos Summary</h3>
        <div class="space-y-6">
            @foreach ($rooms as $room)
                <div>
                    <h4 class="font-semibold text-sm text-gray-700 dark:text-gray-300 mb-3">
                        {{ $room->name }}: {{ $photoCounts[$room->id] ?? 0 }}/8 photos
                    </h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        @foreach ($photosByRoom[$room->id] ?? collect() as $photo)
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
                </div>
            @endforeach
        </div>
    </x-card>
</div>
