@props([
    // If not provided, pull from session('ok')
    'message' => session('ok'),
    // Auto-hide after ms (0 = never)
    'timeout' => 5000,
])

@if ($message)
    <div
        x-data="{ show: true }"
        x-init="if ({{ (int) $timeout }} > 0) setTimeout(() => show = false, {{ (int) $timeout }});"
        x-show="show"
        x-transition.opacity.duration.200ms
        role="status" aria-live="polite" aria-atomic="true"
        class="mb-3 rounded border border-green-200 bg-green-50 text-green-800
               dark:border-green-800 dark:bg-green-900/30 dark:text-green-300"
    >
        <div class="px-4 py-3 flex items-start gap-3">
            <svg class="h-5 w-5 mt-0.5 flex-none" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16Zm3.857-9.809a.75.75 0 10-1.214-.882l-3.22 4.43L7.4 10.4a.75.75 0 10-1.06 1.06l2.5 2.5a.75.75 0 001.14-.09l3.877-5.68Z"
                    clip-rule="evenodd" />
            </svg>
            <div class="flex-1 text-sm">
                {{ $message }}
            </div>
            <button
                type="button"
                @click="show = false"
                class="text-green-700/70 hover:text-green-800
                       dark:text-green-300/70 dark:hover:text-green-200"
                aria-label="Dismiss" title="Dismiss"
            >
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd"
                        d="M10 8.586L4.293 2.879A1 1 0 102.879 4.293L8.586 10l-5.707 5.707a1 1 0 001.414 1.414L10 11.414l5.707 5.707a1 1 0 001.414-1.414L11.414 10l5.707-5.707A1 1 0 0015.707 2.88L10 8.586z"
                        clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    </div>
@endif
