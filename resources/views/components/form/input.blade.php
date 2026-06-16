@props([
    'disabled' => false,
    'withicon' => false,
])

@php
    $withiconClasses = $withicon ? 'pl-11 pr-4' : 'px-4';
@endphp

<input {{ $disabled ? 'disabled' : '' }}
       data-focus-ring="theme-primary"
       {!! $attributes->merge([
           'class' =>
               $withiconClasses .
               ' py-1 border-gray-300 rounded-md focus:border-gray-500 focus:ring
                   focus:ring-offset-2 focus:ring-offset-white dark:border-gray-700 dark:bg-dark-eval-1
                   dark:text-gray-300 dark:focus:ring-offset-dark-eval-1',
       ]) !!}>

<style>
    [data-focus-ring="theme-primary"]:focus {
        --tw-ring-color: var(--theme-primary) !important;
    }
</style>
