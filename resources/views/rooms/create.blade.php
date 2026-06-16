{{-- resources/views/rooms/create.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl flex items-center gap-2">
            Add Room
        </h2>
    </x-slot>

    <x-card>
        <form method="post" action="{{ route('rooms.store') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Name --}}
                <div class="md:col-span-2">
                    <x-form.label for="name" value="Name" />
                    <x-form.input id="name" name="name" class="w-full" required :value="old('name')"
                        placeholder="e.g. Bedroom, Kitchen, Bathroom" />
                    <x-form.error :messages="$errors->get('name')" />
                </div>

                {{-- Mark as default template --}}
                <div class="md:col-span-2">
                    @role('admin')
                    <div>
                        <label for="is_default" class="inline-flex items-center gap-2">
                            <input id="is_default" type="checkbox" name="is_default" value="1"
                                @checked(old('is_default')) class="rounded border-gray-300 dark:border-gray-700">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                Mark as default template
                            </span>
                        </label>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Default rooms can be auto-assigned when new properties are created.
                        </p>
                    </div>
                    @endrole
                </div>
            </div>

            <div class="flex gap-2">
                <x-button type="submit">Save</x-button>
                <x-button variant="secondary" href="{{ route('rooms.index') }}">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
