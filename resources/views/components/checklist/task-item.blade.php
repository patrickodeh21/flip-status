@php
    use Illuminate\Support\Str;
@endphp

@props([
    'task',
    'item' => null,
    'session',
    'room' => null,
    'disabled' => false,
    'completed' => false,
])

@php
    $isPropertyTask = $room === null;
    $toggleRoute = $isPropertyTask
        ? route('checklist.property-task.toggle', [$session, $task])
        : route('checklist.toggle', [$session, $room, $task]);
    $noteRoute = $isPropertyTask
        ? route('checklist.property-task.note', [$session, $task])
        : route('checklist.note', [$session, $room, $task]);
    $photoRoute = $isPropertyTask
        ? route('checklist.property-task.photo', [$session, $task])
        : route('checklist.task-photo', [$session, $room, $task]);

    // Get instructions from pivot (room-specific) or task model
    $instructions = null;
    if (!$isPropertyTask && isset($task->pivot) && !empty($task->pivot->instructions)) {
        $instructions = $task->pivot->instructions;
    } elseif (!empty($task->instructions)) {
        $instructions = $task->instructions;
    }

    $hasMedia = method_exists($task, 'media') && $task->media->count() > 0;
    $hasInstructions = !empty($instructions);
    $showDetails = $hasMedia || $hasInstructions;

    // DO NOT auto-expand - only show "READ IMPORTANT NOTES" button
    $autoExpandDetails = false;
@endphp

<div
    data-task-item
    class="group relative bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition-all duration-200 {{ $completed ? 'opacity-90' : '' }}"
    x-data="{
        detailsOpen: false,
        galleryOpen: false,
        gallerySrc: null,
        noteValue: '{{ $item?->note ?? '' }}',
        noteSaving: false,
        noteModalOpen: false,
        photoModalOpen: false,
        photoNote: '',
        photoFile: null,
        photoPreview: null,
        photoUploading: false
    }"
>
    <div class="p-4">
        <div class="flex items-start gap-4">
            {{-- Toggle Checkbox --}}
            <div class="flex-shrink-0 pt-0.5">
                <button
                    type="button"
                    data-checklist-toggle
                    data-toggle-url="{{ $toggleRoute }}"
                    data-checked="{{ $completed ? 'true' : 'false' }}"
                    class="relative w-6 h-6 rounded-md border-2 flex items-center justify-center transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 {{ $disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:scale-110' }} {{ $completed ? 'bg-green-600 border-green-600 text-white' : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600' }}"
                    {{ $disabled ? 'disabled' : '' }}
                    aria-label="{{ $completed ? 'Mark as incomplete' : 'Mark as complete' }}"
                >
                    @if($completed)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                        </svg>
                    @endif
                </button>
            </div>

            {{-- Task Content --}}
            <div class="flex-1 min-w-0">
                <div class="flex flex-col gap-1">
                    <h3
                        data-task-name
                        class="text-lg font-bold text-gray-900 dark:text-gray-100 transition-all {{ $completed ? 'line-through text-gray-400 dark:text-gray-500 font-medium' : '' }}"
                    >
                        {{ $task->name }}
                    </h3>

                    @php
                        $isMandatory = Str::contains(strtolower($task->name), ['photo', 'picture']) ||
                                      Str::contains(strtolower($instructions ?? ''), ['take a photo', 'mandatory photo']);
                        $hasPhotos = $item && $item->photos->count() > 0;
                    @endphp

                    @if($isMandatory && !$hasPhotos)
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-orange-600 uppercase tracking-tight">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            Photo Required
                        </span>
                    @endif

                    @if($showDetails)
                        <div class="mt-3">
                            <button
                                type="button"
                                @click="detailsOpen = !detailsOpen"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200 border-2 border-amber-300 dark:border-amber-700 hover:bg-amber-200 dark:hover:bg-amber-900/60 transition-all shadow-sm active:scale-95"
                            >
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span x-text="detailsOpen ? 'Hide Instructions' : 'Read Instructions'"></span>
                                <svg class="w-4 h-4 transition-transform duration-300" :class="{ 'rotate-180': detailsOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                        </div>
                    @endif
                </div>

                {{-- Task Actions Footer --}}
                <div class="mt-4 flex items-center justify-between border-t border-gray-100 dark:border-gray-700/50 pt-3" data-note-container>
                    <div class="flex items-center gap-3">
                        {{-- Notes Icon Button --}}
                        <button
                            type="button"
                            @click="noteModalOpen = true"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-full border {{ $item?->note ? 'border-blue-200 bg-blue-50 text-blue-600' : 'border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400' }} hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:text-blue-600 transition-all active:scale-95"
                            {{ $disabled ? 'disabled' : '' }}
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            <span class="text-xs font-bold uppercase tracking-tight">Note</span>
                        </button>

                        {{-- Photo Upload Icon Button --}}
                        <button
                            type="button"
                            @click="photoModalOpen = true"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-full border {{ $hasPhotos ? 'border-green-200 bg-green-50 text-green-600' : 'border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400' }} hover:bg-green-50 dark:hover:bg-green-900/20 hover:text-green-600 transition-all active:scale-95"
                            {{ $disabled ? 'disabled' : '' }}
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span class="text-xs font-bold uppercase tracking-tight">Photo</span>
                            @if($hasPhotos)
                                <span class="bg-green-600 text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center">{{ $item->photos->count() }}</span>
                            @endif
                        </button>
                    </div>

                    {{-- Hidden input for note saving --}}
                    <input type="hidden" data-note-input x-model="noteValue" />
                </div>

                {{-- Collapsible Details --}}
                <div
                    x-show="detailsOpen"
                    x-collapse
                    x-cloak
                    class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700"
                >
                    @if($hasInstructions)
                        <div class="mb-3">
                            <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1.5">Instructions</div>
                            <div class="prose dark:prose-invert prose-sm max-w-none text-gray-600 dark:text-gray-400 leading-relaxed">
                                {!! nl2br(e($instructions)) !!}
                            </div>
                        </div>
                    @endif

                    @if($hasMedia)
                        <div class="mb-3">
                            <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1.5">Examples</div>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach($task->media as $media)
                                    <div class="relative rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 group bg-gray-100 dark:bg-gray-800">
                                        @if($media->type === 'image')
                                            <button type="button" @click="galleryOpen = true; gallerySrc = '{{ $media->url }}'" class="block w-full">
                                                <img src="{{ $media->thumbnail ?? $media->url }}" alt="{{ $media->caption ?? 'Task media' }}"
                                                     class="w-full h-24 object-cover transition-transform group-hover:scale-105"
                                                     onerror="this.src='https://placehold.co/400x300?text=Image+Not+Found'; this.onerror=null;"
                                                     loading="lazy" />
                                            </button>
                                        @else
                                            <video src="{{ $media->url }}" class="w-full h-24 object-cover" controls muted></video>
                                        @endif
                                        @if($media->caption)
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/50 p-1">
                                                <p class="text-[10px] text-white truncate text-center">{{ $media->caption }}</p>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($item && $item->photos->count() > 0)
                        <div class="mb-1">
                            <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1.5">Your Photos</div>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach($item->photos as $photo)
                                    <div class="relative rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 group bg-gray-100 dark:bg-gray-800">
                                        <button type="button" @click="galleryOpen = true; gallerySrc = '{{ $photo->url }}'" class="block w-full">
                                            <img src="{{ $photo->url }}" alt="Attached photo"
                                                 class="w-full h-24 object-cover transition-transform group-hover:scale-105"
                                                 onerror="this.src='https://placehold.co/400x300?text=Photo+Missing'; this.onerror=null;"
                                                 loading="lazy" />
                                        </button>
                                        @if($photo->note)
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/50 p-1">
                                                <p class="text-[10px] text-white truncate text-center">{{ $photo->note }}</p>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Image Gallery Modal --}}
    <div
        x-show="galleryOpen"
        x-cloak
        @click.self="galleryOpen = false"
        @keydown.escape.window="galleryOpen = false"
        class="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4"
    >
        <img
            :src="gallerySrc"
            class="max-h-[90vh] max-w-[90vw] rounded-lg shadow-2xl"
            alt="Gallery view"
        />
        <button
            type="button"
            @click="galleryOpen = false"
            class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300 transition-colors"
        >
            ×
        </button>
    </div>

    {{-- Note Modal --}}
    <div
        x-show="noteModalOpen"
        x-cloak
        @click.self="noteModalOpen = false"
        @keydown.escape.window="noteModalOpen = false"
        class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
    >
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full p-6" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Add Note</h3>
                <button
                    type="button"
                    @click="noteModalOpen = false"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <textarea
                x-model="noteValue"
                rows="4"
                placeholder="Enter your note here..."
                class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
            ></textarea>

            <div class="flex justify-end gap-3 mt-4">
                <button
                    type="button"
                    @click="noteModalOpen = false"
                    class="px-4 py-2 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    data-checklist-note-save="true"
                    data-note-url="{{ $noteRoute }}"
                    @click="noteSaving = true; $el.textContent = 'Saving...'"
                    class="px-4 py-2 text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition-colors"
                >
                    Save Note
                </button>
            </div>
        </div>
    </div>

    {{-- Photo Upload Modal --}}
    <div
        x-show="photoModalOpen"
        x-cloak
        @click.self="photoModalOpen = false"
        @keydown.escape.window="photoModalOpen = false"
        class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
    >
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full p-6" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Upload Photo</h3>
                <button
                    type="button"
                    @click="photoModalOpen = false; photoFile = null; photoPreview = null; photoNote = '';"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form
                method="POST"
                action="{{ $photoRoute }}"
                enctype="multipart/form-data"
                @submit.prevent="
                    if (!photoFile) { alert('Please take a photo'); return; }
                    if (!photoNote.trim()) { alert('Please add a note describing the photo'); return; }
                    photoUploading = true;
                    const formData = new FormData($el);
                    window.api.post($el.action, formData).then((data) => {
                        photoUploading = false;
                        photoModalOpen = false;
                        photoFile = null;
                        photoPreview = null;
                        photoNote = '';
                        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: 'Photo uploaded successfully' } }));
                    }).catch((error) => {
                        photoUploading = false;
                        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: error.message || 'Failed to upload photo' } }));
                    });
                "
            >
                @csrf

                {{-- Photo Input - Camera Only --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Take Photo</label>
                    <div
                        class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4 text-center cursor-pointer hover:border-blue-500 transition-colors"
                        @click="$refs.photoInput.click()"
                    >
                        <template x-if="!photoPreview">
                            <div class="text-gray-500">
                                <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <p class="mt-2 text-sm font-medium">Tap to take photo</p>
                            </div>
                        </template>
                        <template x-if="photoPreview">
                            <img :src="photoPreview" class="max-h-48 mx-auto rounded-lg" alt="Photo preview" />
                        </template>
                    </div>
                    <input
                        type="file"
                        name="photo"
                        accept="image/*"
                        capture="environment"
                        class="hidden"
                        x-ref="photoInput"
                        @change="
                            photoFile = $event.target.files[0];
                            if (photoFile) {
                                const reader = new FileReader();
                                reader.onload = (e) => photoPreview = e.target.result;
                                reader.readAsDataURL(photoFile);
                            }
                        "
                    />
                </div>

                {{-- Note Input (Required) --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Note <span class="text-red-500">*</span>
                        <span class="text-xs text-gray-500 font-normal">(Required when uploading photo)</span>
                    </label>
                    <textarea
                        name="note"
                        x-model="photoNote"
                        rows="3"
                        placeholder="Describe what this photo shows..."
                        required
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                    ></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <button
                        type="button"
                        @click="photoModalOpen = false; photoFile = null; photoPreview = null; photoNote = '';"
                        class="px-4 py-2 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="photoUploading || !photoFile || !photoNote.trim()"
                        class="px-4 py-2 text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span x-show="!photoUploading">Upload Photo</span>
                        <span x-show="photoUploading">Uploading...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
