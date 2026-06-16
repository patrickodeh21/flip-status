<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl">Add Task — {{ $property->name }} / {{ $room->name }}</h2>
            <a class="text-sm underline text-gray-600 dark:text-gray-300" href="{{ route('properties.tasks.index', [$property, $room]) }}">← Back</a>
        </div>
    </x-slot>

    @php $suggestUrl = route('tasks.suggest'); @endphp

    <x-card>
        <form method="post" action="{{ route('properties.tasks.store', [$property, $room]) }}"
              class="space-y-6" enctype="multipart/form-data">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2" x-data="taskAutocomplete({ suggestUrl: @js($suggestUrl) })">
                    <x-form.label for="name" value="Task Name" />
                    <div class="relative mt-1">
                        <input x-model="q" name="name" id="name" type="text" required autocomplete="off"
                               placeholder="e.g., Wipe counters, Make bed"
                               class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                               @input="onInput" @focus="onFocus" @keydown="keyDown"
                               aria-autocomplete="list" aria-expanded="open" aria-controls="task-suggest"/>
                        <div x-cloak x-show="open"
                             class="absolute z-20 mt-1 w-full rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-lg overflow-hidden"
                             id="task-suggest" role="listbox">
                            <div x-show="loading" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">Searching…</div>
                            <template x-if="hasResults">
                                <ul class="max-h-60 overflow-y-auto">
                                    <template x-for="(item,idx) in items" :key="item.id">
                                        <li class="px-3 py-2 cursor-pointer text-sm hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center justify-between"
                                            :class="{'bg-gray-50 dark:bg-gray-800': focusedIndex===idx}"
                                            @mouseenter="focusedIndex=idx" @mouseleave="focusedIndex=-1" @click="choose(item)">
                                            <span x-text="item.name" class="text-gray-800 dark:text-gray-100"></span>
                                            <span class="text-[10px] uppercase ml-2 px-1.5 py-0.5 rounded"
                                                  :class="item.type==='inventory' ? 'bg-blue-100 text-blue-800 dark:bg-blue-400/20 dark:text-blue-300'
                                                                                   : 'bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-300'">
                                                <span x-text="item.type"></span>
                                            </span>
                                        </li>
                                    </template>
                                    <li x-show="q && !items.some(i=>i.name.toLowerCase()===q.toLowerCase())"
                                        class="px-3 py-2 cursor-pointer text-sm text-indigo-700 dark:text-indigo-300 bg-indigo-50/60 dark:bg-indigo-900/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/50"
                                        :class="{'ring-1 ring-indigo-400': focusedIndex===items.length}"
                                        @mouseenter="focusedIndex=items.length" @mouseleave="focusedIndex=-1" @click="open=false">
                                        Create "<span x-text="q"></span>"
                                    </li>
                                </ul>
                            </template>
                            <div x-show="!loading && !hasResults && q" class="px-3 py-2 text-sm text-indigo-700 dark:text-indigo-300 bg-indigo-50/60 dark:bg-indigo-900/30">
                                Create "<span x-text="q"></span>"
                            </div>
                        </div>
                    </div>
                    @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror

                    <div class="mt-4">
                        <x-form.label for="type" value="Type" />
                        <select name="type" id="type" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                            <option value="room">Room</option>
                            <option value="inventory">Inventory</option>
                            <option value="verify">Verify</option>
                        </select>
                    </div>

                    <div class="mt-4">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                            <x-form.checkbox name="is_sporadic" value="1" :checked="old('is_sporadic')" />
                            Occasional Task
                        </label>
                        <p class="text-xs text-gray-500 mt-1 ml-6">Only included if specifically checked when scheduling a session.</p>
                    </div>

                    <div class="mt-4">
                        <x-form.label for="instructions" value="Instructions (optional, visible to staff)" />
                        <textarea name="instructions" id="instructions" rows="4"
                                  class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                  placeholder="Short steps, tips, or link to SOP..."></textarea>
                    </div>

                    <input type="hidden" name="visible_to_owner" value="1" />
                    <input type="hidden" name="visible_to_housekeeper" value="1" />
                </div>

                {{-- Uploader (optional) --}}
                <div x-data="{ files: [], previews: [] }" x-init="
                    $watch('files', f => {
                        previews = [...f].map(file => ({ url: URL.createObjectURL(file), type: file.type.startsWith('video') ? 'video':'image' }))
                    })
                " class="bg-gray-50 dark:bg-gray-900/40 rounded-md p-4">
                    <div class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Instructional Media (optional)</div>
                    <input type="file" name="media[]" multiple accept="image/*,video/*"
                           class="block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-3 file:rounded file:border-0 file:bg-gray-200 dark:file:bg-gray-800"
                           x-on:change="files = $event.target.files"
                    />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Images or videos up to 20MB each.</p>

                    <div class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-3" x-show="previews.length" x-cloak>
                        <template x-for="(p, i) in previews" :key="i">
                            <div class="rounded border dark:border-gray-800 overflow-hidden">
                                <template x-if="p.type==='image'">
                                    <img :src="p.url" class="w-full h-32 object-cover" />
                                </template>
                                <template x-if="p.type==='video'">
                                    <video :src="p.url" class="w-full h-32 object-cover" muted controls></video>
                                </template>
                                <input type="text" name="captions[]" placeholder="Caption (optional)"
                                       class="w-full border-t dark:border-gray-800 px-2 py-1 text-xs dark:bg-gray-900 dark:text-gray-100"/>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="flex gap-2">
                <x-button>Save</x-button>
                <x-button variant="secondary" href="{{ route('properties.tasks.index', [$property, $room]) }}">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
