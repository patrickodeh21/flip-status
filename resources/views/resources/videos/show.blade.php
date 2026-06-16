<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="text-lg sm:text-xl font-semibold leading-tight">
                {{ $video->title }}
            </h2>
            <x-button href="{{ route('videos.index') }}" class="inline-flex items-center justify-center px-3 py-2 rounded bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 w-full sm:w-auto whitespace-nowrap hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                Back to Videos
            </x-button>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Video Player --}}
        <x-card class="overflow-hidden">
            <div class="rounded-lg overflow-hidden bg-black">
                <video controls class="w-full aspect-video max-h-[60vh]" playsinline>
                    <source src="{{ $video->url }}">
                    Your browser does not support the video tag.
                </video>
            </div>
            @if ($video->description)
                <div class="mt-4 px-1">
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $video->description }}</p>
                </div>
            @endif
        </x-card>

        {{-- Properties Section --}}
        <x-card>
            <x-slot name="header">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Assigned Properties
                        <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">
                            ({{ $video->properties->count() }} total)
                        </span>
                    </h3>
                </div>
            </x-slot>

            @if ($video->properties->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400 italic">No properties assigned to this video.</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach ($video->properties as $property)
                        <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700">
                            <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                    {{ $property->name }}
                                </p>
                                @if ($property->address)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $property->address }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>

        {{-- Admin Actions --}}
        @if (!$isHousekeeper)
            <div class="flex gap-3 justify-end">
                <x-button href="{{ route('videos.edit', $video) }}" class="inline-flex items-center justify-center px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3 17.25V21h3.75l11-11-3.75-3.75-11 11zM20.71 7.04a1.003 1.003 0 0 0 0-1.42L18.37 3.29a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.83z" />
                    </svg>
                    Edit Video
                </x-button>
            </div>
        @endif
    </div>
</x-app-layout>
