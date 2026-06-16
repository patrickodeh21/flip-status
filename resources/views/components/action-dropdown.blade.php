@props([
    'align' => 'right', // 'left'|'right'
    'width' => 'w-56', // tailwind width
    'icon' => 'vertical', // 'vertical'|'horizontal'
    'label' => 'Open menu',
    'offset' => 8, // px gap between trigger and panel
])

@php
    $iconSvg =
        $icon === 'horizontal'
            ? '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 dark:text-gray-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="6" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="18" cy="12" r="2"/></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 dark:text-gray-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>';
@endphp

<div x-data="dropdown({ align: '{{ $align }}', offset: {{ (int) $offset }} })"
     x-init="init()"
     x-on:keydown.escape.window="close()"
     x-on:dropdown-close.window="close()"
     class="relative inline-block text-left">
    {{-- Trigger --}}
    <button type="button" x-ref="button" x-on:click.stop="toggle()" :aria-expanded="open" aria-haspopup="true"
        aria-label="{{ $label }}"
        data-focus-ring="theme-primary"
        class="inline-flex items-center justify-center rounded-md p-2
               hover:bg-gray-100 dark:hover:bg-gray-700
               focus:outline-none focus:ring-2">
        {!! $iconSvg !!}
    </button>

    {{-- Teleported panel to avoid overflow clipping --}}
    <template x-teleport="body">
        <div x-show="open"
             x-transition.opacity
             x-cloak
             x-ref="panel"
             :style="panelStyle"
             x-init="
                const updateTheme = () => {
                    const isDark = document.querySelector('.dark') !== null;
                    $el.classList.toggle('bg-gray-800', isDark);
                    $el.classList.toggle('bg-white', !isDark);
                    $el.classList.toggle('ring-white/10', isDark);
                    $el.classList.toggle('ring-black/5', !isDark);
                    $el.classList.toggle('divide-gray-700', isDark);
                    $el.classList.toggle('divide-gray-100', !isDark);
                };
                updateTheme();
                const observer = new MutationObserver(updateTheme);
                observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
                $el._themeObserver = observer;
             "
             class="fixed z-50 rounded-md shadow-lg ring-1 bg-white ring-black/5 divide-gray-100
                    {{ $width }}"
            role="menu" aria-orientation="vertical" tabindex="-1" @mousedown.stop
            @click.outside="if(!justOpened) close()" @keydown.arrow-down.prevent="focusNext($event)"
            @keydown.arrow-up.prevent="focusPrev($event)">
            <div class="py-1" role="none">
                {{ $slot }}
            </div>
        </div>
    </template>
</div>

<style>
    [data-focus-ring="theme-primary"]:focus {
        --tw-ring-color: var(--theme-primary) !important;
    }
</style>
