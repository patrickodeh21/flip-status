@props([
    // Validation key to read from $errors (e.g., 'gps'). If null, will fall back to flash.
    'for' => null,

    // Named error bag, e.g. 'login'. Use 'default' for the main bag.
    'bag' => 'default',

    // If provided, this overrides anything pulled from errors/flash.
    'message' => null,

    // Auto-hide after ms (0 = never)
    'timeout' => 0,
])

@php
    // Resolve a message in this order:
    // 1) Explicit prop
    // 2) From $errors (for a specific key if provided, else first error)
    // 3) From flash session('error') or session('danger')
    $resolved = $message;

    if (is_null($resolved)) {
        $errorBag = $bag === 'default' ? $errors : $errors->{$bag};

        if ($for) {
            $resolved = $errorBag?->first($for);
        }

        if (is_null($resolved)) {
            $resolved = $errorBag?->first();
        }

        if (is_null($resolved)) {
            $resolved = session('error') ?? session('danger');
        }
    }
@endphp

@if ($resolved)
    <div x-data="{ show: true }" x-init="if ({{ (int) $timeout }} > 0) setTimeout(() => show = false, {{ (int) $timeout }});" x-show="show" x-transition.opacity.duration.200ms role="alert"
        aria-live="assertive" aria-atomic="true"
        class="mb-3 rounded border border-red-200 bg-red-50 text-red-800
               dark:border-red-800 dark:bg-red-900/30 dark:text-red-300">
        <div class="px-4 py-3 flex items-start gap-3">
            {{-- Error Icon --}}
            <svg class="h-5 w-5 mt-0.5 flex-none" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16ZM9 6a1 1 0 112 0v5a1 1 0 11-2 0V6Zm1 9a1.25 1.25 0 100-2.5A1.25 1.25 0 0010 15Z"
                    clip-rule="evenodd" />
            </svg>

            {{-- Message --}}
            <div class="flex-1 text-sm">
                {{ $resolved }}
            </div>

            {{-- Dismiss --}}
            <button type="button" @click="show = false"
                class="text-red-700/70 hover:text-red-800
                       dark:text-red-300/70 dark:hover:text-red-200"
                aria-label="Dismiss" title="Dismiss">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd"
                        d="M10 8.586L4.293 2.879A1 1 0 102.879 4.293L8.586 10l-5.707 5.707a1 1 0 001.414 1.414L10 11.414l5.707 5.707a1 1 0 001.414-1.414L11.414 10l5.707-5.707A1 1 0 0015.707 2.88L10 8.586z"
                        clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    </div>
@endif
