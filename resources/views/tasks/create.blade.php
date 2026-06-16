<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl flex items-center gap-2">
            New Task
        </h2>
    </x-slot>

    <x-card>
        <form method="post" action="{{ route('tasks.store') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Name --}}
                <div class="md:col-span-2">
                    <x-form.label for="name" value="Name" />
                    <x-form.input id="name" name="name" class="w-full" required
                                  :value="old('name')" placeholder="e.g. Mop floor, Restock soap" />
                    <x-form.error :messages="$errors->get('name')" />
                </div>

                {{-- Type --}}
                <div>
                    <x-form.label for="type" value="Type" />
                    <select id="type" name="type"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                        <option value="room" @selected(old('type')==='room')>Task (Standard checklist item)</option>
                        <option value="inventory" @selected(old('type')==='inventory')>Inventory (Prompt housekeeper for input quantity)</option>
                        <option value="verify" @selected(old('type')==='verify')>Verify (Requires a photo upload to complete)</option>
                    </select>
                    <x-form.error :messages="$errors->get('type')" />
                </div>

                @role('admin')
                {{-- Default template --}}
                <div class="flex items-center gap-2 pt-6">
                    <x-form.checkbox id="is_default" name="is_default" value="1" :checked="old('is_default')" />
                    <label for="is_default">Default Task</label>
                </div>
                @endrole

                {{-- Occasional Task --}}
                <div class="flex items-center gap-2 pt-6">
                    <x-form.checkbox id="is_sporadic" name="is_sporadic" value="1" :checked="old('is_sporadic')" />
                    <div>
                        <label for="is_sporadic">Occasional Task</label>
                        <p class="text-xs text-gray-500">Only included if specifically checked when scheduling a session</p>
                    </div>
                </div>

                {{-- Instructions --}}
                <div class="md:col-span-2">
                    <x-form.label for="instructions" value="Instructions (optional)" />
                    <textarea id="instructions" name="instructions" rows="6"
                              class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                              placeholder="Write step-by-step guidance for performing this task...">{{ old('instructions') }}</textarea>
                    <x-form.error :messages="$errors->get('instructions')" />
                </div>
            </div>

            <div class="flex gap-2">
                <x-button type="submit">Save</x-button>
                <x-button variant="secondary" href="{{ route('tasks.index') }}">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
