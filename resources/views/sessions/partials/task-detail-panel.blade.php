{{-- Reusable task details panel: instructions + media + lightbox --}}
@php
    /** @var \App\Models\Task $task */
    $peek = \Illuminate\Support\Str::of($task->instructions)->stripTags();
@endphp

<li x-data="{ open: false, galleryOpen: false, gallerySrc: null }" class="px-4 py-3 space-y-2">
    <div class="flex items-start sm:items-center justify-between gap-3">
        <div class="flex items-start sm:items-center gap-3">
            {{-- Toggle button (passed in by slot to avoid nested forms) --}}
            <div class="flex-shrink-0">
                {{ $toggleButton }}
            </div>

            {{-- Title + instruction peek --}}
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span
                        class="block text-sm font-medium truncate {{ $completed ? 'line-through text-gray-500 dark:text-gray-400' : 'text-gray-800 dark:text-gray-200' }}">
                        {{ $task->name }}
                    </span>

                    <button type="button"
                        class="text-xs inline-flex items-center gap-1 px-2 py-0.5 rounded border
                               border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700
                               text-gray-700 dark:text-gray-300"
                        @click="open = !open" :aria-expanded="open.toString()"
                        aria-controls="task-{{ $task->id }}-details">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd"
                                d="M10 3a1 1 0 01.894.553l6 12A1 1 0 0116 17H4a1 1 0 01-.894-1.447l6-12A1 1 0 0110 3zm0 4a1 1 0 00-1 1v2a1 1 0 002 0V8a1 1 0 00-1-1zm0 6a1 1 0 100 2 1 1 0 000-2z"
                                clip-rule="evenodd" />
                        </svg>
                        <span x-show="!open">Details</span>
                        <span x-show="open">Hide</span>
                    </button>
                </div>

                @if ($peek->isNotEmpty())
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 line-clamp-1">
                        {{ $peek }}
                    </p>
                @endif
            </div>
        </div>

        {{-- Right-side slot for the Note form (keeps forms separated) --}}
        <div class="flex items-center gap-2">
            {{ $noteForm }}
        </div>
    </div>

    {{-- Collapsible: full instructions + media --}}
    <div x-show="open" x-collapse x-cloak id="task-{{ $task->id }}-details"
        class="rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-3">

        {{-- Full instructions, rendered with simple formatting --}}
        @if ($task->instructions)
            <div class="prose dark:prose-invert prose-sm max-w-none">
                {{-- A tiny bit nicer formatting: turn lines that start with "-" or "*" into bullets --}}
                @php
                    $lines = preg_split("/\r\n|\n|\r/", $task->instructions);
                    $asList = collect($lines)->every(
                        fn($l) => \Illuminate\Support\Str::startsWith(trim($l), ['- ', '* ']),
                    );
                @endphp

                @if ($asList)
                    <ul class="!mt-0">
                        @foreach ($lines as $line)
                            <li>{{ \Illuminate\Support\Str::of($line)->ltrim('-* ')->toString() }}</li>
                        @endforeach
                    </ul>
                @else
                    {!! nl2br(e($task->instructions)) !!}
                @endif
            </div>
        @else
            <p class="text-xs text-gray-500 dark:text-gray-400">No detailed instructions provided.</p>
        @endif

        {{-- Media gallery (images/videos) --}}
        @if (method_exists($task, 'media') && $task->media->count())
            <div class="mt-3 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                @foreach ($task->media as $m)
                    <div class="relative rounded overflow-hidden border dark:border-gray-800 group">
                        @if ($m->type === 'image')
                            <button type="button" class="block w-full"
                                @click="galleryOpen = true; gallerySrc = '{{ $m->url }}'">
                                <img src="{{ $m->thumbnail ?? $m->url }}" alt="{{ $m->caption }}"
                                    class="w-full h-28 object-cover transition group-hover:opacity-90" loading="lazy">
                            </button>
                        @else
                            <div class="relative">
                                <video src="{{ $m->url }}" class="w-full h-28 object-cover" muted
                                    controls></video>
                                <button type="button"
                                    class="absolute bottom-1 right-1 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white"
                                    @click.prevent="
                                            $el.previousElementSibling?.requestPictureInPicture?.()
                                        ">
                                    PiP
                                </button>
                            </div>
                        @endif

                        @if ($m->caption)
                            <span
                                class="absolute bottom-1 left-1 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white">
                                {{ \Illuminate\Support\Str::limit($m->caption, 28) }}
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Lightbox --}}
            <div x-show="galleryOpen" x-cloak
                class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
                @keydown.escape.window="galleryOpen = false" @click.self="galleryOpen = false">
                <img :src="gallerySrc" class="max-h-[85vh] rounded-xl shadow-xl" alt="Preview">
                <button type="button" class="absolute top-4 right-4 text-white text-2xl"
                    @click="galleryOpen=false">×</button>
            </div>
        @endif
    </div>
</li>
