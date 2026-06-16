@props([
    'disabled' => false,
    'checked' => false,
])

@php
    // Generate unique ID for this checkbox instance
    $checkboxId = $attributes->get('id', 'checkbox-' . uniqid());

    // Check if checked attribute is passed via attributes or prop
    $isChecked = $checked || $attributes->has('checked') || $attributes->get('checked') === true || $attributes->get('checked') === 'checked';
@endphp

<input
    type="checkbox"
    data-checkbox-theme="button-primary"
    style="accent-color: var(--button-primary-color); --checkbox-theme-color: var(--button-primary-color);"
    {{ $disabled ? 'disabled' : '' }}
    {{ $isChecked ? 'checked' : '' }}
    {!! $attributes->merge([
        'id' => $checkboxId,
        'class' => 'w-4 h-4 border-gray-300 rounded focus:ring focus:ring-offset-2 focus:ring-offset-white dark:border-gray-600 dark:bg-dark-eval-1 dark:focus:ring-offset-dark-eval-1 text-indigo-600 focus:ring-indigo-500',
    ])->except(['style', 'checked']) !!}
>

@once
    <style>
        input[type="checkbox"][data-checkbox-theme] {
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            width: 1rem !important;
            height: 1rem !important;
            min-width: 1rem !important;
            min-height: 1rem !important;
            border: 2px solid #d1d5db !important;
            border-radius: 0.25rem !important;
            background-color: white !important;
            cursor: pointer !important;
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: relative;
            flex-shrink: 0;
            margin: 0 !important;
            padding: 0 !important;
            vertical-align: middle;
        }

        .dark input[type="checkbox"][data-checkbox-theme] {
            background-color: #1f2937 !important;
            border-color: #4b5563 !important;
        }

        input[type="checkbox"][data-checkbox-theme]:checked {
            background-color: var(--button-primary-color) !important;
            border-color: var(--button-primary-color) !important;
            background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M12.207 4.793a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-3-3a1 1 0 011.414-1.414L6 10.586l5.793-5.793a1 1 0 011.414 0z'/%3e%3c/svg%3e") !important;
            background-size: 100% 100% !important;
            background-position: center !important;
            background-repeat: no-repeat !important;
        }

        input[type="checkbox"][data-checkbox-theme]:focus {
            border-color: var(--button-primary-color) !important;
            --tw-ring-color: var(--button-primary-color);
            outline: none !important;
        }

        input[type="checkbox"][data-checkbox-theme]:disabled {
            opacity: 0.5 !important;
            cursor: not-allowed !important;
        }

        /* Dark mode specific adjustments */
        .dark input[type="checkbox"][data-checkbox-theme]:checked {
            background-color: var(--button-primary-color) !important;
            border-color: var(--button-primary-color) !important;
        }

        .dark input[type="checkbox"][data-checkbox-theme]:focus {
            border-color: var(--button-primary-color) !important;
            --tw-ring-color: var(--button-primary-color);
        }
    </style>
@endonce
