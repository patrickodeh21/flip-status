@props([
    'class' => 'h-5 w-5',
    'title' => 'Delete',
])

{{--
    Trash icon component using an inline SVG path from Iconify/Material design icons.
    The tooltip shows on hover using Alpine.js. Customize the size and color via the
    "class" attribute when using this component. Set the tooltip text via the
    "title" attribute. Example:

        <x-icons.trash class="h-6 w-6 text-red-600" title="Delete property" />

--}}

<span x-data="{ tooltip: false }" class="relative inline-block"
      @mouseenter="tooltip = true" @mouseleave="tooltip = false">
    <svg xmlns="http://www.w3.org/2000/svg" class="{{ $class }}" viewBox="0 0 24 24" fill="currentColor">
        {{-- Path sourced from Material Design Icons (mdi:trash-can). --}}
        <path d="M9 3v2H4v2h16V5h-5V3h-4zM5 7v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7H5zm2 2h10v10H7V9z" />
    </svg>
    <div x-show="tooltip" x-cloak
         class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-1 text-xs rounded-md
                bg-gray-700 text-white whitespace-nowrap z-10">
        {{ $title }}
    </div>
</span>