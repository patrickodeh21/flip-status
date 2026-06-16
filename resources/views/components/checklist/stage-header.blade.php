@props([
    'title',
    'current' => 0,
    'total' => 0,
    'icon' => null,
    'description' => null,
])

<div class="mb-6">
    <div class="flex items-center gap-3 mb-2">
        @if($icon)
            <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                {!! $icon !!}
            </div>
        @endif
        <div class="flex-1">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $title }}</h2>
            @if($description)
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $description }}</p>
            @endif
        </div>
        @if($total > 0)
            <div class="flex-shrink-0 text-right">
                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $current }}/{{ $total }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">completed</div>
            </div>
        @endif
    </div>
    @if($total > 0)
        <x-checklist.progress-bar 
            :current="$current" 
            :total="$total" 
            color="blue"
        />
    @endif
</div>
