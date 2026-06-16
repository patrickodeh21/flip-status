{{-- resources/views/media/upload.blade.php --}}
@php
    // Configure the external form id and field names
    $formId = $formId ?? 'media-upload-form';
    $inputName = $inputName ?? 'media[]';
    $captionName = $captionName ?? 'captions[]';
    $accept = $accept ?? 'image/*,video/*';
    $multiple = $multiple ?? true;
@endphp

<div x-data="mediaDropzone({ formId: @js($formId), accept: @js($accept), multiple: @js($multiple) })" x-init="// Augment the dropzone with a 'replace' method so each card can update itself.
// Rebuilds the FileList, swaps the file at index, and updates preview URL.
$data.replace = (idx, e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const allowed = ($data.accept || '').split(',').map(s => s.trim());
    const isAllowed = (file, allowed) => {
        if (!allowed.length) return true;
        return allowed.some(a => a.endsWith('/*') ? file.type.startsWith(a.slice(0, -1)) : file.type === a);
    };
    if (!isAllowed(file, allowed)) { e.target.value = ''; return; }

    const dt = new DataTransfer();
    $data.previews.forEach((p, i) => {
        dt.items.add(i === idx ? file : p.file);
    });
    $refs.input.files = dt.files;

    // swap preview
    try { URL.revokeObjectURL($data.previews[idx]?.url); } catch {}
    const oldCaption = $data.previews[idx]?.caption || '';
    $data.previews[idx] = {
        file,
        url: URL.createObjectURL(file),
        kind: file.type.startsWith('video') ? 'video' : 'image',
        caption: oldCaption
    };

    e.target.value = ''; // reset per-card input
};" class="space-y-4">
    {{-- Dropzone --}}
    <div class="relative flex flex-col items-center justify-center rounded-2xl border-2 border-dashed p-8
           border-gray-300/80 dark:border-gray-700/80 bg-white/70 dark:bg-gray-900/40
           hover:border-indigo-400 hover:bg-indigo-50/40 dark:hover:bg-indigo-900/10 transition-colors"
        @dragover.prevent="hover=true" @dragleave.prevent="hover=false" @drop.prevent="handleDrop($event)"
        :class="hover ? 'border-indigo-400 bg-indigo-50/40 dark:bg-indigo-900/10' : ''">
        <div class="pointer-events-none absolute inset-0 rounded-2xl" :class="hover ? 'ring-2 ring-indigo-400/60' : ''">
        </div>

        <svg class="h-12 w-12 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M3 15a4 4 0 004 4h10a4 4 0 100-8h-1.5M15 11l-3-3m0 0l-3 3m3-3v12" />
        </svg>

        <div class="mt-3 text-center">
            <p class="text-sm text-gray-800 dark:text-gray-100">
                Drag & drop files here, or
                <button type="button" class="text-indigo-600 dark:text-indigo-400 underline font-medium"
                    @click="pick()">browse</button>
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">JPEG, PNG, WebP, MP4, MOV — up to 20MB each</p>
        </div>

        {{-- The REAL file input (bound to external form via form="...") --}}
        <input x-ref="input" type="file" class="hidden" form="{{ $formId }}" name="{{ $inputName }}"
            @change="handleChoose($event)" accept="{{ $accept }}"
            @if ($multiple) multiple @endif />
    </div>

    {{-- Previews grid --}}
    <template x-if="previews.length">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <template x-for="(p, i) in previews" :key="i">
                <div
                    class="group relative rounded-xl overflow-hidden border dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm">

                    {{-- Full-card click overlay → opens per-card file input to replace --}}
                    <input type="file" class="absolute inset-0 z-20 opacity-0 cursor-pointer"
                        accept="{{ $accept }}" @change="replace(i, $event)" aria-label="Replace media" />

                    {{-- Thumb --}}
                    <template x-if="p.kind === 'image'">
                        <img :src="p.url" class="w-full h-44 object-cover" loading="lazy" />
                    </template>
                    <template x-if="p.kind === 'video'">
                        <div class="w-full h-44 bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                            <svg class="h-10 w-10 opacity-70" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M8 5v14l11-7z" />
                            </svg>
                        </div>
                    </template>

                    {{-- Top-right chips (visible on hover) --}}
                    <div
                        class="absolute top-2 right-2 z-10 flex items-center gap-2 opacity-0 group-hover:opacity-100 transition">
                        <span class="text-[11px] px-2 py-0.5 rounded bg-gray-900/80 text-white"
                            x-text="p.kind === 'video' ? 'Video' : 'Image'"></span>
                        <button type="button" class="text-[11px] px-2 py-0.5 rounded bg-gray-900/80 text-white"
                            @click.stop="remove(i)">
                            Remove
                        </button>
                    </div>

                    {{-- Caption area (click anywhere on card to focus/replace; caption stays editable) --}}
                    <div class="border-t dark:border-gray-800">
                        <input type="text" name="{{ $captionName }}" :form="formId"
                            placeholder="Caption (optional)"
                            class="w-full px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:outline-none"
                            x-model="p.caption">
                    </div>

                    {{-- Hint footer --}}
                    <div
                        class="absolute bottom-10 left-0 right-0 mx-2 hidden group-hover:flex items-center justify-center">
                        <span class="text-[11px] px-2 py-0.5 rounded bg-gray-900/75 text-white">Click card to
                            replace</span>
                    </div>
                </div>
            </template>
        </div>
    </template>

    {{-- Actions --}}
    <div class="flex items-center justify-center">
        <x-button class="text-sm" form="{{ $formId }}" type="submit" x-bind:disabled="previews.length === 0">
            Upload
        </x-button>
    </div>
</div>
