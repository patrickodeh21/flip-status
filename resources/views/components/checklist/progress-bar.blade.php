@props([
    'current' => 0,
    'total' => 0,
    'label' => '',
    'color' => 'blue',
])

@php
    $percentage = $total > 0 ? min(($current / $total) * 100, 100) : 0;
    $colorClasses = [
        'blue' => 'bg-blue-600',
        'green' => 'bg-green-600',
        'amber' => 'bg-amber-600',
        'purple' => 'bg-purple-600',
    ];
    $bgColor = $colorClasses[$color] ?? $colorClasses['blue'];
@endphp

<div class="w-full">
    @if($label)
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $current }}/{{ $total }}</span>
        </div>
    @endif
    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
        <div 
            class="h-full rounded-full transition-all duration-500 ease-out {{ $bgColor }}"
            style="width: {{ $percentage }}%"
            role="progressbar"
            aria-valuenow="{{ $current }}"
            aria-valuemin="0"
            aria-valuemax="{{ $total }}"
        ></div>
    </div>
</div>
