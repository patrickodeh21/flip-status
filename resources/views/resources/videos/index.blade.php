<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg sm:text-xl font-semibold leading-tight">
            {{ $isHousekeeper ? 'My Videos' : 'Instructional Videos' }}
        </h2>
    </x-slot>

    <div class="space-y-4 w-full max-w-full" x-data="{ videoModalOpen: false, currentVideoUrl: null, selectedTitle: null }">
        @if (!$isHousekeeper)
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 px-1 sm:px-0">
                <x-button href="{{ route('videos.create') }}" class="inline-flex items-center justify-center px-3 py-2 rounded bg-indigo-600 text-white w-full sm:w-auto whitespace-nowrap">
                    + Upload Video
                </x-button>
            </div>
        @endif

        <x-card class="!px-0 overflow-hidden w-full">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="uppercase text-xs tracking-wide">
                        <tr class="text-gray-600 dark:text-gray-300">
                            <th class="px-4 py-1 text-left">Video</th>
                            <th class="px-4 py-1 text-left">Title</th>
                            <th class="px-4 py-1 text-left">Description</th>
                            <th class="px-4 py-1 text-left">Properties</th>
                            @if (!$isHousekeeper)
                                <th class="px-4 py-1 w-40 text-right">Action</th>
                            @endif
                        </tr>
                    </thead>

                    <tbody class="divide-y dark:divide-gray-700">
                        @forelse ($videos as $video)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3">
                                    <button type="button"
                                        @click="currentVideoUrl = '{{ $video->url }}'; selectedTitle = '{{ addslashes($video->title) }}'; videoModalOpen = true"
                                        class="flex-shrink-0 group relative cursor-pointer border-0 p-0 bg-transparent">
                                        @if (Str::startsWith($video->url, 'http'))
                                            <div class="relative rounded-xl overflow-hidden h-16 w-24 bg-gray-200 dark:bg-gray-700">
                                                <video class="h-full w-full" preload="metadata">
                                                    <source src="{{ $video->url }}#t=0.001" type="video/mp4">
                                                </video>
                                                <div class="absolute inset-0 flex items-center justify-center bg-black/30 group-hover:bg-black/50 transition-all pointer-events-none">
                                                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M8 5v14l11-7z"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                        @else
                                            <div class="h-16 w-24 rounded-xl bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                                <svg class="w-8 h-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 012-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                </svg>
                                            </div>
                                        @endif
                                    </button>
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $video->title }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                    {{ Str::limit($video->description, 80) }}
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $propertyCount = $video->properties->count();
                                        $allProperties = $video->properties->pluck('name');
                                    @endphp

                                    @if ($propertyCount === 0)
                                        {{-- No properties assigned --}}
                                        <span class="text-xs text-gray-500 dark:text-gray-400 italic">No properties assigned</span>
                                    @elseif ($allProperties->contains(fn($name) => $name === 'All Properties') || $propertyCount <= 5)
                                        {{-- Show all property names directly in the table --}}
                                        <span class="text-xs text-gray-600 dark:text-gray-400">
                                            {{ $allProperties->join(', ') }}
                                        </span>
                                    @else
                                        {{-- Too many properties: show count with link to view page --}}
                                        <a href="{{ route('videos.show', $video) }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                                            {{ $propertyCount }} Properties Assigned
                                        </a>
                                    @endif
                                </td>
                                @if (!$isHousekeeper)
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <x-action-dropdown align="right" width="w-48" label="Video actions">
                                            <x-dropdown.item @click.prevent="currentVideoUrl = '{{ $video->url }}'; selectedTitle = '{{ addslashes($video->title) }}'; videoModalOpen = true">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M8 5v14l11-7z"></path>
                                                </svg>
                                                <span>Play Video</span>
                                            </x-dropdown.item>
                                            <x-dropdown.item href="{{ route('videos.edit', $video) }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M3 17.25V21h3.75l11-11-3.75-3.75-11 11zM20.71 7.04a1.003 1.003 0 0 0 0-1.42L18.37 3.29a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.83z" />
                                                </svg>
                                                <span>Edit</span>
                                            </x-dropdown.item>

                                            <x-dropdown.item as="form" method="POST" href="{{ route('videos.destroy', $video) }}"
                                                onclick="return confirm('Delete this video?')">
                                                @csrf
                                                @method('DELETE')
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M9 3a1 1 0 0 0-1 1v1H4a1 1 0 1 0 0 2h1v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7h1a1 1 0 1 0 0-2h-4V4a1 1 0 0 0-1-1H9zm2 4a1 1 0 1 0-2 0v10a1 1 0 1 0 2 0V7zm4 0a1 1 0 1 0-2 0v10a1 1 0 1 0 2 0V7z" />
                                                </svg>
                                                <span>Delete</span>
                                            </x-dropdown.item>
                                        </x-action-dropdown>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-10 text-center text-gray-500 dark:text-gray-400" @if (!$isHousekeeper) colspan="5" @else colspan="4" @endif>
                                    No videos yet.
                                    @if (!$isHousekeeper)
                                        <a href="{{ route('videos.create') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline ml-1">Upload one now</a>.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- ISO Media, MP4 v2 --}}
        <div
            x-show="videoModalOpen"
            x-cloak
            @click.self="videoModalOpen = false"
            @keydown.escape.window="videoModalOpen = false"
            class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
        >
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-3xl w-full p-4" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 x-text="selectedTitle" class="text-lg font-semibold text-gray-900 dark:text-gray-100 truncate"></h3>
                    <button
                        type="button"
                        @click="videoModalOpen = false"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="rounded-lg overflow-hidden">
                    <video :src="currentVideoUrl" controls class="w-full aspect-video" playsinline>
                        Your browser does not support the video tag.
                    </video>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
