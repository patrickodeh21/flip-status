@php $suggestUrl = route('tasks.suggest'); @endphp

<div class="h-full flex flex-col">
    <div class="flex-1 overflow-y-auto p-4 sm:p-6">
        <form id="edit-task-form-{{ $task->id }}" method="post" action="{{ route('rooms.tasks.update', [$room, $task]) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                {{-- Task Name with Autocomplete --}}
                <div x-data="taskAutocomplete({ suggestUrl: @js($suggestUrl) })" x-init="q = @js($task->name)">
                    <x-form.label for="name-{{ $task->id }}" value="Task Name" />
                    <div class="relative mt-1">
                        <input x-model="q" name="name" id="name-{{ $task->id }}" type="text" required autocomplete="off"
                               class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                               @input="onInput" @focus="onFocus" @keydown="keyDown" aria-autocomplete="list"
                               aria-expanded="open" aria-controls="task-suggest-{{ $task->id }}" />
                        <div x-cloak x-show="open" id="task-suggest-{{ $task->id }}" 
                             class="absolute z-50 mt-1 w-full rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-lg overflow-hidden">
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
                </div>

                {{-- Type --}}
                <div>
                    <x-form.label for="type-{{ $task->id }}" value="Type" />
                    <select name="type" id="type-{{ $task->id }}" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                        <option value="room" @selected($task->type === 'room')>Room</option>
                        <option value="inventory" @selected($task->type === 'inventory')>Inventory</option>
                        <option value="verify" @selected($task->type === 'verify')>Verify</option>
                        <option value="instruction" @selected($task->type === 'instruction')>Instruction</option>
                    </select>
                </div>

                {{-- Occasional Task --}}
                <div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                        <x-form.checkbox name="is_sporadic" value="1" :checked="(bool) old('is_sporadic', $task->is_sporadic)" />
                        Occasional Task
                    </label>
                    <p class="text-xs text-gray-500 mt-1 ml-6">Only included if specifically checked when scheduling a session.</p>
                </div>

                {{-- Instructions --}}
                <div>
                    <x-form.label for="instructions-{{ $task->id }}" value="Instructions (optional)" />
                    <textarea name="instructions" id="instructions-{{ $task->id }}" rows="3"
                              class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                              placeholder="Steps for this task in this specific room...">{{ old('instructions', $pivot->instructions) }}</textarea>
                </div>

                {{-- Visibility: always visible to both --}}
                <input type="hidden" name="visible_to_owner" value="1" />
                <input type="hidden" name="visible_to_housekeeper" value="1" />

                {{-- Media --}}
                <div class="bg-gray-50 dark:bg-gray-900/40 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-sm font-semibold text-gray-700 dark:text-gray-200">Instructional Media</div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Optional</span>
                    </div>

                    <x-media.uploader />

                    @if ($task->media->count())
                        <div class="mt-6" x-data="{
                            existingMedia: [
                                @foreach ($task->media as $m)
                                    {
                                        id: {{ $m->id }},
                                        type: '{{ $m->type }}',
                                        url: '{{ $m->url }}',
                                        thumbnail: '{{ $m->thumbnail ?? $m->url }}',
                                        caption: '{{ addslashes($m->caption ?: 'Media') }}',
                                        deleteUrl: '{{ route('properties.tasks.media.destroy', [$task, $m]) }}'
                                    }{{ !$loop->last ? ',' : '' }}
                                @endforeach
                            ],
                            async deleteMedia(index) {
                                if(!confirm('Remove this media?')) return;
                                const media = this.existingMedia[index];
                                try {
                                    const res = await fetch(media.deleteUrl, {
                                        method: 'DELETE',
                                        headers: {
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                            'Accept': 'application/json'
                                        }
                                    });
                                    if(res.ok) {
                                        this.existingMedia.splice(index, 1);
                                    } else {
                                        alert('Failed to delete media');
                                    }
                                } catch(e) {
                                    console.error(e);
                                    alert('Failed to delete media');
                                }
                            }
                        }">
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Existing Media</div>
                            <div class="grid grid-cols-2 gap-3" x-show="existingMedia.length > 0">
                                <template x-for="(m, idx) in existingMedia" :key="m.id">
                                    <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                        <template x-if="m.type === 'image'">
                                            <img :src="m.thumbnail" :alt="m.caption" class="w-full h-32 object-cover" />
                                        </template>
                                        <template x-if="m.type === 'video'">
                                            <video :src="m.url" class="w-full h-32 object-cover" controls muted></video>
                                        </template>
                                        <div class="p-2 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                                            <span class="text-xs truncate mr-2" :title="m.caption" x-text="m.caption"></span>
                                            <button type="button" 
                                                    class="text-xs text-rose-600 hover:text-rose-700 dark:text-rose-400"
                                                    @click="deleteMedia(idx)">
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </form>

        // Hidden Delete Forms for existing media have been replaced by async fetch
        
        <form id="detach-task-form-{{ $task->id }}" method="post" action="{{ route('rooms.tasks.detach', [$room, $task]) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    </div>

    {{-- Fixed Footer Bottom Actions --}}
    <div class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-4 sm:p-6 flex flex-row-reverse flex-wrap items-center justify-between gap-3 shrink-0">
        <div class="flex gap-2">
            <x-button variant="secondary" @click="$dispatch('close-preview-panel')">Cancel</x-button>
            <x-button type="submit" form="edit-task-form-{{ $task->id }}">Save</x-button>
        </div>
        <x-button variant="secondary" form="detach-task-form-{{ $task->id }}" type="submit" 
                 class="!text-rose-600 dark:!text-rose-400 !border-rose-200 dark:!border-rose-900/50 hover:!bg-rose-50 dark:hover:!bg-rose-900/30"
                 onclick="return confirm('Detach this task from the room?')">
            Detach Task
        </x-button>
    </div>
</div>
