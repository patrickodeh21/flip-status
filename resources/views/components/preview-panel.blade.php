@props([
    'name', // unique id/key for this panel
    'title' => null,
    'subtitle' => null,
    'side' => 'right', // 'right' | 'left'
    'initialWidth' => '20rem', // starting width
    'minWidth' => '20rem', // minimum width
    'overlay' => true,
])

@php
    $side = $side === 'left' ? 'left' : 'right';
@endphp

<div x-data="{
    name: @js($name),
    side: @js($side),
    overlay: @js($overlay),

    show: false,
    widthPx: 448, // default fallback
    minPx: 320, // default fallback

    init() {
        const toPx = (val) => {
            if (typeof val === 'number') return val
            if (!val) return 448
            if (val.endsWith('rem')) return parseFloat(val) * 16
            if (val.endsWith('px')) return parseFloat(val)
            return parseFloat(val) || 448
        }

        this.widthPx = toPx(@js($initialWidth))
        this.minPx = toPx(@js($minWidth))

        this.$watch('show', value => {
            if (value) {
                // Lock body scroll (works on iOS too)
                this._scrollY = window.scrollY;
                document.body.style.position = 'fixed';
                document.body.style.top = `-${this._scrollY}px`;
                document.body.style.left = '0';
                document.body.style.right = '0';
                document.body.style.overflow = 'hidden';
            } else {
                // Restore body scroll
                document.body.style.position = '';
                document.body.style.top = '';
                document.body.style.left = '';
                document.body.style.right = '';
                document.body.style.overflow = '';
                window.scrollTo(0, this._scrollY || 0);
            }
        })
    },

    openIfMatches(id) {
        if (id === this.name) this.open()
    },
    closeIfMatches(id) {
        // if id is null/undefined -> close all, or close only matching
        if (!id || id === this.name) this.close()
    },

    open() {
        this.show = true
    },
    close() {
        this.show = false
    },

    isOpen() {
        return this.show
    },

    panelStyle() {
        if (window.innerWidth < 768) {
            return { width: '100% !important' };
        }
        return { width: this.widthPx + 'px' };
    },

    // --- Resizing ---
    _resizing: false,
    _startX: 0,
    _startW: 0,

    startResize(e) {
        this._resizing = true
        this._startX = (e.touches?.[0]?.clientX ?? e.clientX)
        this._startW = this.widthPx

        const move = (ev) => {
            if (!this._resizing) return
            const x = (ev.touches?.[0]?.clientX ?? ev.clientX)
            const dx = x - this._startX
            const dir = this.side === 'right' ? -1 : 1
            const newW = this._startW + dir * dx
            this.widthPx = Math.max(newW, this.minPx)
        }

        const up = () => {
            this._resizing = false
            window.removeEventListener('mousemove', move)
            window.removeEventListener('mouseup', up)
            window.removeEventListener('touchmove', move)
            window.removeEventListener('touchend', up)
        }

        window.addEventListener('mousemove', move)
        window.addEventListener('mouseup', up)
        window.addEventListener('touchmove', move, { passive: true })
        window.addEventListener('touchend', up)
    },
}" x-init="init()" x-on:open-preview-panel.window="openIfMatches($event.detail)"
    x-on:close-preview-panel.window="closeIfMatches($event.detail)" x-on:keydown.escape.window="if(show) { $event.stopPropagation(); close(); }" x-cloak>
    
    <template x-teleport="body">
        <div x-show="show" class="fixed inset-0 z-50 pointer-events-none" style="z-index: 99998;" x-cloak>
            {{-- Overlay – mobile/fullscreen --}}
            <div x-show="show" x-transition.opacity
                class="fixed inset-0 bg-gray-900/40 dark:bg-gray-900/80 pointer-events-auto backdrop-blur-sm" style="touch-action: none;"
                @click="close()" aria-hidden="true"></div>

            {{-- Panel --}}
            <aside x-show="show" x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-x-full" x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 translate-x-full" x-bind:style="{ ...panelStyle(), zIndex: 99999 }"
                class="fixed top-0 bottom-0 h-[100dvh] max-h-[100dvh] z-50 bg-white dark:bg-gray-900 shadow-2xl
                       border-l border-gray-200 dark:border-gray-800 flex flex-col
                       w-full md:max-w-[90vw] transition-all pb-safe pointer-events-auto"
                :class="side === 'right' ? 'right-0 md:rounded-l-2xl' : 'left-0 md:rounded-r-2xl'"
                role="dialog" aria-modal="true" aria-labelledby="{{ $name }}-title">
                
                {{-- Header --}}
                <div class="flex items-center justify-between gap-2 sm:gap-3 p-3 sm:p-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="min-w-0 text-left">
                        @if (isset($title))
                            <h3 id="{{ $name }}-title" class="font-semibold truncate text-gray-900 dark:text-gray-100">
                                {{ $title }}
                            </h3>
                        @endif

                        @if (isset($subtitle))
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                {{ $subtitle }}
                            </p>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="hidden md:block text-[10px] text-gray-400 select-none">
                            <span x-text="Math.round(widthPx) + 'px'"></span>
                        </div>

                        <button type="button"
                            class="inline-flex items-center justify-center rounded-md p-2
                                   text-gray-500 hover:text-gray-700 hover:bg-gray-100
                                   dark:text-gray-400 dark:hover:text-gray-200
                                   dark:hover:bg-gray-800"
                            @click="close()" aria-label="Close panel">
                            ✕
                        </button>
                    </div>
                </div>

                {{-- Body --}}
                <div class="relative flex-1 flex flex-col min-h-0 bg-white dark:bg-gray-900">
                    <div class="flex-1 preview-scroll px-4 py-3 sm:p-4 overflow-y-auto overscroll-contain" style="-webkit-overflow-scrolling: touch;">
                        {{ $slot }}
                    </div>

                    {{-- Resize handle (desktop only) --}}
                    <div class="hidden md:block absolute top-0 bottom-0 w-2 cursor-ew-resize
                               hover:bg-gray-100/50 dark:hover:bg-gray-800/50"
                        :class="side === 'right' ? 'left-0' : 'right-0'" @mousedown="startResize($event)"
                        @touchstart.passive="startResize($event)" aria-hidden="true"></div>
                </div>

                {{-- Footer (optional) --}}
                @if (isset($footer))
                    <div class="flex-shrink-0 p-3 sm:p-4 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                        {{ $footer }}
                    </div>
                @endif
            </aside>
        </div>
    </template>
</div>
<style>
    /* base */
    .preview-scroll {
        scrollbar-width: thin;
        /* Firefox */
        scrollbar-color: #d1d5db #f9fafb;
        /* thumb track */
    }

    /* WebKit browsers */
    .preview-scroll::-webkit-scrollbar {
        width: 8px;
    }

    .preview-scroll::-webkit-scrollbar-track {
        background: #f9fafb;
        /* light track (gray-50) */
    }

    .preview-scroll::-webkit-scrollbar-thumb {
        background-color: #d1d5db;
        /* light thumb (gray-300) */
        border-radius: 9999px;
    }

    /* Dark mode (Tailwind .dark on html/body) */
    .dark .preview-scroll {
        scrollbar-color: #4b5563 #030712;
        /* thumb track */
    }

    .dark .preview-scroll::-webkit-scrollbar-track {
        background: #030712;
        /* gray-950 */
    }

    .dark .preview-scroll::-webkit-scrollbar-thumb {
        background-color: #4b5563;
        /* gray-600 */
        border-radius: 9999px;
    }
</style>
