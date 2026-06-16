<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Edit Assignment</h2>
    </x-slot>

    <x-card>
        {{-- UPDATE FORM --}}
        <form id="assignment-update-form" method="post" action="{{ route('manage.sessions.update', $session) }}"
            class="grid grid-cols-1 md:grid-cols-2 gap-4"
            x-data="sporadicTasksForm({
                initialProperty: '{{ old('property_id', $session->property_id) }}',
                initialTasks: @js(old('sporadic_tasks', $session->sporadic_tasks ?? []))
            })">
            @csrf
            @method('put')

            <div>
                <x-form.label value="Property" />
                <x-form.select name="property_id" class="w-full rounded border-gray-300" required x-model="propertyId" @change="fetchTasks">
                    @foreach ($properties as $p)
                        <option value="{{ $p->id }}" >{{ $p->name }}</option>
                    @endforeach
                </x-form.select>
                <x-form.error :messages="$errors->get('property_id')" />
            </div>

            <div x-data="{
                propertyCleaners: @js($propertyCleaners),
                get availableHousekeepers() {
                    const hkList = @js($housekeepers);
                    if (!this.propertyId) return [];
                    const allowedIds = this.propertyCleaners[this.propertyId] || [];
                    return hkList.filter(hk => allowedIds.includes(hk.id));
                }
            }">
                <x-form.label value="Housekeeper" />
                <x-form.select name="housekeeper_id" class="w-full rounded border-gray-300" required>
                    <template x-for="hk in availableHousekeepers" :key="hk.id">
                        <option :value="hk.id" x-text="hk.name" :selected="{{ old('housekeeper_id', $session->housekeeper_id) }} == hk.id"></option>
                    </template>
                </x-form.select>
                <div x-show="propertyId && availableHousekeepers.length === 0" class="text-xs text-amber-500 mt-1">
                    No housekeepers explicitly assigned to this property.
                </div>
                <x-form.error :messages="$errors->get('housekeeper_id')" />
            </div>

            <div>
                <x-form.label value="Scheduled date" />
                <x-form.input type="date" name="scheduled_date"
                    value="{{ old('scheduled_date', $session->scheduled_date->toDateString()) }}" required />
                <x-form.error :messages="$errors->get('scheduled_date')" />
            </div>

            <div>
                <x-form.label value="Scheduled time" />
                <x-form.input type="time" name="scheduled_time"
                    value="{{ old('scheduled_time', $session->scheduled_time ? (is_string($session->scheduled_time) ? substr($session->scheduled_time, 0, 5) : $session->scheduled_time->format('H:i')) : '') }}" />
                <x-form.error :messages="$errors->get('scheduled_time')" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Optional: Set the time for this assignment</p>
            </div>

            <div>
                <x-form.label value="Status" />
                <x-form.select name="status" class="w-full rounded border-gray-300" required>
                    @foreach (['pending', 'in_progress', 'completed'] as $st)
                        <option value="{{ $st }}" @selected(old('status', $session->status) === $st)>
                            {{ ucfirst(str_replace('_', ' ', $st)) }}
                        </option>
                    @endforeach
                </x-form.select>
                <x-form.error :messages="$errors->get('status')" />
            </div>

            {{-- Sporadic Tasks --}}
            <div class="md:col-span-2" x-show="propertyId" x-cloak>
                <div class="h-px w-full bg-gray-200 dark:bg-gray-700 my-4"></div>
                <x-form.label value="Include Occasional Tasks" />
                <p class="text-xs text-gray-500 mb-3">Select any extra periodic tasks to include in this specific session checklist.</p>

                
                <div class="text-sm text-gray-500" x-show="loading">Loading tasks...</div>
                <div class="text-sm text-gray-500 italic" x-show="!loading && tasks.length === 0">No occasional tasks available for this property.</div>
                
                <div class="mt-2 border border-gray-200 dark:border-gray-700 rounded-lg divide-y divide-gray-200 dark:divide-gray-700" x-show="!loading && tasks.length > 0">
                    <template x-for="task in tasks" :key="task.id">
                        <label class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer transition-colors w-full">
                            <input type="checkbox" name="sporadic_tasks[]" :value="task.id" x-model="selectedTasks" class="w-5 h-5 rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-900 border-gray-300 dark:border-gray-700">
                            <div class="flex-1 flex items-center">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="task.name"></div>
                            </div>
                        </label>
                    </template>
                </div>
            </div>

            <div class="md:col-span-2 flex items-center gap-2">
                {{-- Submit the UPDATE form explicitly --}}
                <x-button type="submit" form="assignment-update-form">Update</x-button>

                <x-button href="{{ route('manage.sessions.index') }}">Back</x-button>

                {{-- Trigger the DELETE form (defined below) --}}
                <x-button type="submit" variant="danger" form="delete_assignment"
                    onclick="return confirm('Delete assignment?')">
                    Delete
                </x-button>
            </div>
        </form>

        {{-- DELETE FORM (separate, not nested) --}}
        <form id="delete_assignment" method="post" action="{{ route('manage.sessions.destroy', $session) }}"
            class="hidden">
            @csrf
            @method('delete')
        </form>
    </x-card>
</x-app-layout>





