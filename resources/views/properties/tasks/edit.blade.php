<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl">Edit Task — {{ $property->name }} / {{ $room->name }}</h2>
            <a class="text-sm underline text-gray-600 dark:text-gray-300"
               href="{{ route('properties.tasks.index', [$property, $room]) }}">← Back</a>
        </div>
    </x-slot>

    @php $suggestUrl = route('tasks.suggest'); @endphp

    <x-card>
        {{-- MAIN UPDATE FORM (only this wraps fields + Save/Cancel) --}}
        <form method="post"
              action="{{ route('properties.tasks.update', [$property, $room, $task]) }}"
              class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Left: identity + pivot fields --}}
                <div class="lg:col-span-2"
                     x-data="taskAutocomplete({ suggestUrl: @js($suggestUrl) })"
                     x-init="q = @js($task->name)">
                    <x-form.label for="name" value="Task Name" />
                    <div class="relative mt-1">
                        <input x-model="q" name="name" id="name" type="text" required autocomplete="off"
                               class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                               @input="onInput" @focus="onFocus" @keydown="keyDown" aria-autocomplete="list"
                               aria-expanded="open" aria-controls="task-suggest" />
                        <div x-cloak x-show="open"
                             class="absolute z-20 mt-1 w-full rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-lg overflow-hidden"
                             id="task-suggest">
                            <div x-show="loading" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">Searching…</div>
                            <template x-if="hasResults">
                                <ul class="max-h-60 overflow-y-auto">
                                    <template x-for="(item,idx) in items" :key="item.id">
                                        <li class="px-3 py-2 cursor-pointer text-sm hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center justify-between"
                                            :class="{ 'bg-gray-50 dark:bg-gray-800': focusedIndex === idx }"
                                            @mouseenter="focusedIndex=idx" @mouseleave="focusedIndex=-1"
                                            @click="choose(item)">
                                            <span x-text="item.name" class="text-gray-800 dark:text-gray-100"></span>
                                            <span class="text-[10px] uppercase ml-2 px-1.5 py-0.5 rounded"
                                                  :class="item.type === 'inventory'
                                                    ? 'bg-blue-100 text-blue-800 dark:bg-blue-400/20 dark:text-blue-300'
                                                    : 'bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-300'">
                                                <span x-text="item.type"></span>
                                            </span>
                                        </li>
                                    </template>
                                    <li x-show="q && !items.some(i=>i.name.toLowerCase()===q.toLowerCase())"
                                        class="px-3 py-2 cursor-pointer text-sm text-indigo-700 dark:text-indigo-300 bg-indigo-50/60 dark:bg-indigo-900/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/50"
                                        :class="{ 'ring-1 ring-indigo-400': focusedIndex === items.length }"
                                        @mouseenter="focusedIndex=items.length" @mouseleave="focusedIndex=-1"
                                        @click="open=false">
                                        Create "<span x-text="q"></span>"
                                    </li>
                                </ul>
                            </template>
                        </div>
                    </div>

                    <div class="mt-4">
                        <x-form.label for="type" value="Type" />
                        <select name="type" id="type"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                            <option value="room" @selected($task->type === 'room')>Room</option>
                            <option value="inventory" @selected($task->type === 'inventory')>Inventory</option>
                            <option value="verify" @selected($task->type === 'verify')>Verify</option>
                        </select>
                    </div>

                    <div class="mt-4">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                            <x-form.checkbox name="is_sporadic" value="1" :checked="(bool) old('is_sporadic', $task->is_sporadic)" />
                            Occasional Task
                        </label>
                        <p class="text-xs text-gray-500 mt-1 ml-6">Only included if specifically checked when scheduling a session.</p>
                    </div>

                    <div class="mt-4">
                        <x-form.label for="instructions" value="Instructions (pivot)" />
                        <textarea name="instructions" id="instructions" rows="4"
                                  class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                  placeholder="Steps for this task in this specific room...">{{ old('instructions', $pivot->instructions) }}</textarea>
                    </div>

                    <input type="hidden" name="visible_to_owner" value="1" />
                    <input type="hidden" name="visible_to_housekeeper" value="1" />
                </div>

                {{-- Right: media panel (modern dropzone + existing media). NO FORMS INSIDE. --}}
                <div class="bg-gray-50 dark:bg-gray-900/40 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm font-semibold text-gray-700 dark:text-gray-200">Instructional Media</div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Optional</span>
                    </div>

                    {{-- New uploads — this component must render inputs with form="media-upload-form" --}}
                    {{-- Ensure it includes: <input type="file" name="media[]" multiple accept="image/*,video/*" form="media-upload-form"> --}}
                    <x-media.uploader form-id="media-upload-form" />

                    {{-- Existing media (each Remove button points to an external form via form="media-delete-XX") --}}
                    @if ($task->media->count())
                        <div class="mt-4">
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Existing</div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                @foreach ($task->media as $m)
                                    <div class="rounded-lg overflow-hidden border dark:border-gray-800 bg-white dark:bg-gray-900">
                                        @if ($m->type === 'image')
                                            <img src="{{ $m->thumbnail ?? $m->url }}" alt="{{ $m->caption }}"
                                                 class="w-full h-36 object-cover" />
                                        @else
                                            <video src="{{ $m->url }}" class="w-full h-36 object-cover" controls muted></video>
                                        @endif
                                        <div class="p-2 border-t dark:border-gray-800 text-xs flex items-center justify-between">
                                            <span class="truncate">{{ $m->caption }}</span>

                                            {{-- IMPORTANT: This button does not live inside a form; it targets the external form below --}}
                                            <button type="submit"
                                                    class="text-rose-600 hover:underline"
                                                    form="media-delete-{{ $m->id }}"
                                                    onclick="return confirm('Remove this media?');">
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Footer actions --}}
            <div class="flex gap-2">
                <x-button type="submit">Save Changes</x-button>
                <x-button variant="secondary" href="{{ route('properties.tasks.index', [$property, $room]) }}">Cancel</x-button>

                {{-- Detach is an external form; this button only submits that external form --}}
                <x-button class="ml-auto bg-rose-600 hover:bg-rose-700 focus:ring-rose-500" form="detach-form" type="submit">
                    Detach Task
                </x-button>
            </div>
        </form>
    </x-card>

    {{-- EXTERNAL MEDIA UPLOAD FORM (separate, never nested) --}}
    <form id="media-upload-form"
          action="{{ route('properties.tasks.media.store', $task) }}"
          method="post"
          enctype="multipart/form-data"
          class="hidden">
        @csrf
        {{-- file inputs are rendered in <x-media.uploader> with form="media-upload-form" --}}
    </form>

    {{-- EXTERNAL MEDIA DELETE FORMS (one per media). Placed OUTSIDE the main form. --}}
    @foreach ($task->media as $m)
        <form id="media-delete-{{ $m->id }}"
              method="post"
              action="{{ route('properties.tasks.media.destroy', [$task, $m]) }}"
              class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endforeach

    {{-- EXTERNAL DETACH FORM --}}
    <form id="detach-form"
          method="post"
          action="{{ route('properties.tasks.detach', [$property, $room, $task]) }}"
          class="hidden">
        @csrf
        @method('DELETE')
    </form>
</x-app-layout>
