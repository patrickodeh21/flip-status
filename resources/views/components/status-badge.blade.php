@props([
    'status' => 'pending', // pending | in_progress | completed
    'variant' => 'subtle', // subtle | solid
    'size' => 'sm', // sm | md
    'withIcon' => true, // show leading icon
])

@php
    // Map status -> label, icon, and color classes (light + dark)
    $map = [
        'pending' => [
            'label' => 'Pending',
            'icon' => 'heroicon-o-clock',
            'subtle' =>
                'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900/60',
            'solid' => 'bg-amber-500 text-white dark:bg-amber-400 dark:text-gray-900',
        ],
        'in_progress' => [
            'label' => 'In progress',
            'icon' => 'icons.progress',
            'subtle' =>
                'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:ring-sky-900/60',
            'solid' => 'bg-sky-600 text-white dark:bg-sky-400 dark:text-gray-900',
        ],
        'completed' => [
            'label' => 'Completed',
            'icon' => 'heroicon-o-check-circle',
            'subtle' =>
                'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/60',
            'solid' => 'bg-emerald-600 text-white dark:bg-emerald-400 dark:text-gray-900',
        ],
    ];

    $cfg = $map[$status] ?? $map['pending'];

    $sizeMap = [
        'sm' => ['wrapper' => 'text-xs px-2 py-0.5 gap-1', 'icon' => 'w-3.5 h-3.5'],
        'md' => ['wrapper' => 'text-sm px-2.5 py-0.5 gap-1.5', 'icon' => 'w-4 h-4'],
    ];
    $sz = $sizeMap[$size] ?? $sizeMap['sm'];

    $tone = $variant === 'solid' ? $cfg['solid'] : $cfg['subtle'];

    // Allow overriding the visible text via slot
    $label = trim((string) $slot) !== '' ? $slot : $cfg['label'];
@endphp

<span
    {{ $attributes->merge([
        'class' => "inline-flex items-center rounded-full font-medium {$sz['wrapper']} {$tone}",
    ]) }}>
    @if ($withIcon)
        <x-dynamic-component :component="$cfg['icon']" class="{{ $sz['icon'] }}" aria-hidden="true" />
    @endif
    <span>{{ $label }}</span>
</span>
