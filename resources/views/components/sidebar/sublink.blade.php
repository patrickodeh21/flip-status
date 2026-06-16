@props([
    'title' => '',
    'active' => false
])

@php
    $baseHoverClass = $active ? '' : 'hover:text-gray-900 dark:hover:text-gray-100';
    $classes = "transition-colors {$baseHoverClass} block px-2 py-1 rounded-md";
    $inlineStyles = '';

    if ($active) {
        $classes .= ' font-medium';
        $inlineStyles = "background-color: color-mix(in srgb, var(--button-primary-color) 10%, transparent); color: var(--button-primary-color); --sublink-hover-bg: color-mix(in srgb, var(--button-primary-color) 20%, transparent);";
    } else {
        $classes .= ' text-gray-500 dark:text-gray-400';
    }
@endphp

<li class="relative leading-8 m-0 pl-6 last:before:bg-white last:before:h-auto last:before:top-4 last:before:bottom-0 dark:last:before:bg-dark-eval-1 before:block before:w-4 before:h-0 before:absolute before:left-0 before:top-4 before:border-t-2 before:border-t-gray-200 before:-mt-0.5 dark:before:border-t-gray-600">
    <a {{ $attributes->merge(['class' => $classes]) }} @if($inlineStyles) style="{{ $inlineStyles }}" @endif>
        {{ $title }}
    </a>
</li>

@if($active && $inlineStyles)
    <style>
        [style*="--sublink-hover-bg"]:hover {
            background-color: var(--sublink-hover-bg) !important;
        }
    </style>
@endif
