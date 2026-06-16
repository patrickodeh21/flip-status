<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Preferences') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __("Update your auto-save and notification preferences.") }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.preferences.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        @php
            $preferences = $user->preferences ?? [];
            $autoSaveEnabled = $preferences['auto_save_enabled'] ?? 1;
            $autoSaveDelay = $preferences['auto_save_delay'] ?? 400;
            $notifySessionStarted = $preferences['notify_session_started'] ?? 1;
            $notifySessionCompleted = $preferences['notify_session_completed'] ?? 1;
            $notifyAssignments = $preferences['notify_assignments'] ?? 1;
        @endphp

        {{-- Auto-save Settings --}}
        <div>
            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Auto-save Settings</h3>
            <div class="space-y-4">
                <label class="flex items-center">
                    <input type="hidden" name="preferences[auto_save_enabled]" value="0">
                    <x-form.checkbox name="preferences[auto_save_enabled]" value="1"
                        :checked="old('preferences.auto_save_enabled', $autoSaveEnabled) == 1" />
                    <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                        Enable auto-save for forms
                    </span>
                </label>
                
                <div class="ml-8 mt-2">
                    <x-form.label for="auto_save_delay" value="Auto-save Delay (milliseconds)" class="text-xs" />
                    <x-form.input id="auto_save_delay" name="preferences[auto_save_delay]" type="number" min="100" max="5000" step="100"
                        :value="old('preferences.auto_save_delay', $autoSaveDelay)" class="max-w-xs block mt-1" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Delay before auto-saving changes (100-5000ms). Lower values save more frequently.
                    </p>
                </div>
            </div>
        </div>

        {{-- Notification Preferences --}}
        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Notification Preferences</h3>
            <div class="space-y-3">
                <label class="flex items-center">
                    <input type="hidden" name="preferences[notify_session_started]" value="0">
                    <x-form.checkbox name="preferences[notify_session_started]" value="1"
                        :checked="old('preferences.notify_session_started', $notifySessionStarted) == 1" />
                    <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                        Notify when a cleaning session is started
                    </span>
                </label>

                <label class="flex items-center">
                    <input type="hidden" name="preferences[notify_session_completed]" value="0">
                    <x-form.checkbox name="preferences[notify_session_completed]" value="1"
                        :checked="old('preferences.notify_session_completed', $notifySessionCompleted) == 1" />
                    <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                        Notify when a cleaning session is completed
                    </span>
                </label>

                <label class="flex items-center">
                    <input type="hidden" name="preferences[notify_assignments]" value="0">
                    <x-form.checkbox name="preferences[notify_assignments]" value="1"
                        :checked="old('preferences.notify_assignments', $notifyAssignments) == 1" />
                    <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                        Notify when new assignments are created
                    </span>
                </label>
            </div>
        </div>

        <div class="flex items-center gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <x-button>
                {{ __('Save') }}
            </x-button>

            @if (session('status') === 'preferences-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Saved.') }}
                </p>
            @endif
        </div>
    </form>
</section>
