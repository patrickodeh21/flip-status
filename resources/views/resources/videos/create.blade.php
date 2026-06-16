<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg sm:text-xl font-semibold leading-tight">Upload Instructional Video</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto">
        <x-card>
            <form method="POST" action="{{ route('videos.store') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf

                {{-- Title --}}
                <div>
                    <x-form.label for="title" value="Title" />
                    <x-form.input
                        id="title"
                        name="title"
                        type="text"
                        value="{{ old('title') }}"
                        required
                        class="w-full mt-1"
                    />
                    @error('title')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Description --}}
                <div>
                    <x-form.label for="description" value="Description" />
                    <textarea
                        id="description"
                        name="description"
                        rows="4"
                        class="w-full mt-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                    >{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Video Source: Upload OR URL --}}
                <div class="grid gap-6 md:grid-cols-2">
                    {{-- File upload --}}
                    <div>
                        <x-form.label for="video_file" value="Upload Video File" />
                        <input
                            id="video_file"
                            name="video_file"
                            type="file"
                            accept="video/*"
                            class="w-full mt-1 text-sm text-gray-600 dark:text-gray-300 file:mr-4 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium dark:file:bg-gray-700 dark:file:text-gray-300"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Max 200MB. MP4, MOV, WebM, AVI.</p>
                        @error('video_file')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- OR separator --}}
                    <div class="relative flex items-center justify-center md:hidden">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300 dark:border-gray-700"></div>
                        </div>
                        <span class="relative px-2 bg-white dark:bg-gray-800 text-xs text-gray-500 uppercase">OR</span>
                    </div>

                    {{-- Video URL --}}
                    <div>
                        <x-form.label for="video_url" value="Video URL (YouTube, Vimeo, etc.)" />
                        <x-form.input
                            id="video_url"
                            name="video_url"
                            type="url"
                            value="{{ old('video_url') }}"
                            placeholder="https://example.com/video.mp4"
                            class="w-full mt-1"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Paste a link to an external video.</p>
                        @error('video_url')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @if ($errors->has('video_url') && !$errors->has('video_file'))
                    <p class="text-sm text-red-600">{{ $errors->first('video_url') }}</p>
                @endif

                {{-- Properties --}}
                <div x-data="{
                    allProperties: {{ json_encode($properties->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->toArray()) }},
                    selectedIds: {{ json_encode(array_map('intval', old('properties', []))) }},
                    search: ''
                }" x-init="">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Assign to Properties</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Select which properties this video is available to:</p>

                    {{-- Selected chips --}}
                    <div class="flex flex-wrap gap-2 mb-3 min-h-[1.5rem]">
                        <template x-for="id in selectedIds" :key="id">
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-indigo-100 text-indigo-800 text-sm dark:bg-indigo-900/40 dark:text-indigo-200">
                                <span x-text="(allProperties.find(p => p.id === id)?.name ?? id)"></span>
                                <button type="button" @click="selectedIds = selectedIds.filter(i => i !== id)" class="hover:text-indigo-600 dark:hover:text-indigo-300" aria-label="Remove">&times;</button>
                            </span>
                        </template>
                        <span x-show="selectedIds.length === 0" class="text-sm text-gray-500 dark:text-gray-400 italic">No properties selected.</span>
                    </div>

                    {{-- Select All / Deselect All --}}
                    <div class="flex items-center gap-3 mb-2">
                        <button type="button" @click="selectedIds = allProperties.map(p => p.id)" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Select All</button>
                        <span class="text-gray-300 dark:text-gray-600">|</span>
                        <button type="button" @click="selectedIds = []" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">Deselect All</button>
                    </div>

                    {{-- Search --}}
                    <div class="relative mb-3">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-4 w-4 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
                            </svg>
                        </span>
                        <input type="text" x-model="search" placeholder="Search properties..."
                            class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>

                    {{-- Property list --}}
                    <div class="max-h-64 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg divide-y dark:divide-gray-700">
                        <template x-for="prop in allProperties.filter(p => p.name.toLowerCase().includes(search.toLowerCase()))" :key="prop.id">
                            <label class="flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors">
                                <input type="checkbox" :value="prop.id" x-model="selectedIds" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700" />
                                <span class="text-sm text-gray-700 dark:text-gray-300" x-text="prop.name"></span>
                            </label>
                        </template>
                        <div x-show="allProperties.filter(p => p.name.toLowerCase().includes(search.toLowerCase())).length === 0" class="p-4 text-sm text-gray-500 dark:text-gray-400 text-center">
                            No properties match your search.
                        </div>
                    </div>

                    <!-- Hidden inputs for ALL selected properties -->
                    <template x-for="id in selectedIds" :key="'_hidden_'+id">
                        <input type="hidden" name="properties[]" :value="id">
                    </template>
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <x-button type="submit">Upload Video</x-button>
                    <a href="{{ route('videos.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
