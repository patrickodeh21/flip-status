@props([
    'disabled' => false,
    'withicon' => false, // adds left padding to make room for an absolute icon
    'options' => null, // array: ['value' => 'Label', ...] or [['value'=>..,'label'=>..], ...]
    'placeholder' => null, // string|null
    'selected' => null, // current value (falls back to old(name))
])

@php
    $isMultiple = $attributes->has('multiple');

    // Keep parity with your input component: pl-11 when withicon, else px-4
    $leftPad = $withicon ? 'pl-11' : 'px-4';
    // Leave room for caret on single-selects
    $rightPad = $isMultiple ? 'pr-4' : 'pr-10';
    $padding = trim($leftPad . ' ' . $rightPad);

    $base =
        'py-2 border-gray-300 rounded-md focus:border-gray-400 focus:ring focus:ring-offset-2 focus:ring-offset-white dark:border-gray-700 dark:bg-dark-eval-1 dark:text-gray-300 dark:focus:ring-offset-dark-eval-1';
    $state = $disabled ? 'opacity-60 cursor-not-allowed' : '';

    $name = $attributes->get('name');
    $current = old($name, $selected);
@endphp

<div class="{{ $isMultiple ? '' : 'relative' }}">
    <select {{ $disabled ? 'disabled' : '' }}
            data-focus-ring="theme-primary"
            {!! $attributes->merge([
                'class' => "appearance-none {$padding} {$base} {$state}",
            ]) !!}>
        @if ($placeholder)
            <option value="" disabled {{ $current === null || $current === '' ? 'selected' : '' }} hidden>
                {{ $placeholder }}
            </option>
        @endif

        @if (is_array($options))
            @foreach ($options as $value => $label)
                @php
                    // Support both ['val' => 'Label'] and [['value'=>..., 'label'=>...]]
                    $optValue = is_array($label) ? $label['value'] ?? $value : $value;
                    $optLabel = is_array($label) ? $label['label'] ?? ($label['value'] ?? $value) : $label;
                @endphp
                <option value="{{ $optValue }}" {{ (string) $optValue === (string) $current ? 'selected' : '' }}>
                    {{ $optLabel }}
                </option>
            @endforeach
        @else
            {{ $slot }}
        @endif
    </select>

    @unless ($isMultiple)
        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
            <x-heroicon-o-chevron-down class="w-4 h-4 text-gray-500" />
        </span>
    @endunless
</div>

<style>
    [data-focus-ring="theme-primary"]:focus {
        --tw-ring-color: var(--theme-primary) !important;
    }
</style>
