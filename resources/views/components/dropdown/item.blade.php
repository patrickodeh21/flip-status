@props([
    'as' => 'a',     // 'a'|'button'|'form'|'div'
    'href' => null,  // for links/forms
    'method' => null // for forms
])

@php
    $base = 'flex w-full items-center gap-2 px-3 py-2 text-sm
             rounded outline-none transition-colors';
    $inlineStyles = "--dropdown-hover-bg: color-mix(in srgb, var(--button-primary-color) 10%, transparent); --dropdown-hover-bg-dark: color-mix(in srgb, var(--button-primary-color) 20%, transparent); --dropdown-hover-text: var(--button-primary-color); --dropdown-text-light: rgb(55, 65, 81); --dropdown-text-dark: rgb(229, 231, 235);";
@endphp

@if($as === 'button')
    <button type="button" data-menu-item class="{{ $base }}" style="{{ $inlineStyles }}"
            x-init="const updateTextColor = () => { const isDark = document.querySelector('.dark') !== null; $el.style.color = isDark ? 'var(--dropdown-text-dark)' : 'var(--dropdown-text-light)'; }; updateTextColor(); const observer = new MutationObserver(updateTextColor); observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] }); $el._textColorObserver = observer;"
            {{ $attributes }}>
        {{ $slot }}
    </button>
@elseif($as === 'form')
    <form method="{{ $method ?? 'POST' }}" action="{{ $href }}" {{ $attributes->except(['class']) }}>
        @csrf
        <button type="submit" data-menu-item class="{{ $base }}" style="{{ $inlineStyles }}"
                x-init="const updateTextColor = () => { const isDark = document.querySelector('.dark') !== null; $el.style.color = isDark ? 'var(--dropdown-text-dark)' : 'var(--dropdown-text-light)'; }; updateTextColor(); const observer = new MutationObserver(updateTextColor); observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] }); $el._textColorObserver = observer;">
            {{ $slot }}
        </button>
    </form>
@elseif($as === 'div')
    <div tabindex="0" data-menu-item class="{{ $base }}" style="{{ $inlineStyles }}"
         x-init="const updateTextColor = () => { const isDark = document.querySelector('.dark') !== null; $el.style.color = isDark ? 'var(--dropdown-text-dark)' : 'var(--dropdown-text-light)'; }; updateTextColor(); const observer = new MutationObserver(updateTextColor); observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] }); $el._textColorObserver = observer;"
         {{ $attributes }}>
        {{ $slot }}
    </div>
@else
    <a href="{{ $href }}" data-menu-item class="{{ $base }}" style="{{ $inlineStyles }}"
       x-init="const updateTextColor = () => { const isDark = document.querySelector('.dark') !== null; $el.style.color = isDark ? 'var(--dropdown-text-dark)' : 'var(--dropdown-text-light)'; }; updateTextColor(); const observer = new MutationObserver(updateTextColor); observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] }); $el._textColorObserver = observer;"
       {{ $attributes }}>
        {{ $slot }}
    </a>
@endif

<style>
    [data-menu-item][style*="--dropdown-hover-bg"]:hover {
        background-color: var(--dropdown-hover-bg) !important;
        color: var(--dropdown-hover-text) !important;
    }

    /* Dark theme hover */
    .dark [data-menu-item][style*="--dropdown-hover-bg"]:hover,
    body:has(.dark) [data-menu-item][style*="--dropdown-hover-bg"]:hover {
        background-color: var(--dropdown-hover-bg-dark) !important;
        color: var(--dropdown-hover-text) !important;
    }

    [data-menu-item][style*="--dropdown-hover-bg"]:focus {
        background-color: var(--dropdown-hover-bg) !important;
        color: var(--dropdown-hover-text) !important;
    }

    /* Dark theme focus */
    .dark [data-menu-item][style*="--dropdown-hover-bg"]:focus,
    body:has(.dark) [data-menu-item][style*="--dropdown-hover-bg"]:focus {
        background-color: var(--dropdown-hover-bg-dark) !important;
        color: var(--dropdown-hover-text) !important;
    }
</style>
