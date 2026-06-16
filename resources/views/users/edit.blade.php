<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Edit User
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Update user information and profile settings.
                </p>
            </div>
            <x-button variant="secondary" href="{{ route('users.index') }}">
                Back to Users
            </x-button>
        </div>
    </x-slot>

    {{-- Success Message --}}
    <x-flash.ok :message="session('success')" />

    <x-card>
        <form x-data="userEditForm()" method="post" action="{{ route('users.update', $user) }}"
            enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Left column: Profile Photo --}}
                <div class="lg:col-span-1">
                    <x-form.label value="Profile Photo" />
                    <div class="mt-1 border-2 border-dashed rounded-2xl p-6 flex flex-col items-center justify-center text-center bg-gray-50/40 dark:bg-gray-800/40">
                        @php
                            $currentPhotoUrl = $user->profile_photo_url;
                        @endphp

                        <template x-if="!previewUrl">
                            <div class="w-full">
                                <img src="{{ $currentPhotoUrl }}" alt="Current photo"
                                    class="rounded-full object-cover h-40 w-40 mx-auto shadow-lg border-4 border-white dark:border-gray-700" />
                                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                    Current profile photo
                                </p>
                            </div>
                        </template>

                        <template x-if="previewUrl">
                            <div class="w-full">
                                <img :src="previewUrl" alt="Preview"
                                    class="rounded-full object-cover h-40 w-40 mx-auto shadow-lg border-4 border-white dark:border-gray-700" />
                                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                    New photo preview
                                </p>
                            </div>
                        </template>

                        <input type="file" name="profile_photo" class="hidden" x-ref="file" @change="preview($event)"
                            accept="image/*" />

                        <div class="mt-4 flex flex-col items-center gap-2">
                            <x-button type="button" variant="secondary" @click="$refs.file.click()">
                                Choose New Photo
                            </x-button>

                            @if ($user->profile_photo_path)
                                <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer">
                                    <x-form.checkbox name="remove_profile_photo" value="1" />
                                    Remove photo
                                </label>
                            @endif
                        </div>

                        @error('profile_photo')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Right column: Form fields --}}
                <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Name --}}
                    <div class="md:col-span-2">
                        <x-form.label value="Full Name" />
                        <x-form.input name="name" class="w-full" required :value="old('name', $user->name)"
                            placeholder="e.g. John Doe" />
                        <x-form.error :messages="$errors->get('name')" />
                    </div>

                    {{-- Email --}}
                    <div class="md:col-span-2">
                        <x-form.label value="Email Address" />
                        <x-form.input name="email" type="email" class="w-full" required :value="old('email', $user->email)"
                            placeholder="e.g. john@example.com" />
                        <x-form.error :messages="$errors->get('email')" />
                    </div>

                    {{-- Phone Number --}}
                    <div>
                        <x-form.label value="Phone Number (optional)" />
                        <x-form.input name="phone_number" type="tel" class="w-full" :value="old('phone_number', $user->phone_number)"
                            placeholder="e.g. +1 234 567 8900" />
                        <x-form.error :messages="$errors->get('phone_number')" />
                    </div>

                    {{-- Master Owner (Primary association) --}}
                    @if (auth()->user()->hasAnyRole(['admin', 'owner', 'company']))
                        @php
                            $userRole = $user->roles->first()?->name;
                            $showOwnerSelection = ($userRole === 'housekeeper');
                        @endphp
                        
                        <div x-show="selectedRole === 'housekeeper' || selectedRole === 'company'" class="md:col-span-2 space-y-4">
                            <div>
                                <x-form.label value="Primary Owner / Company" />
                                <x-form.select name="owner_id" class="w-full">
                                    <option value="">— Direct (Managed by you) —</option>
                                    @foreach ($owners ?? [] as $owner)
                                        <option value="{{ $owner->id }}" @selected(old('owner_id', $user->owner_id) == $owner->id)>
                                            {{ $owner->name }} ({{ ucfirst($owner->roles->first()?->name) }})
                                        </option>
                                    @endforeach
                                </x-form.select>
                            </div>

                            {{-- Multiple Owner Assignment --}}
                            <div>
                                <x-form.label value="Also attach to these Owners (Optional)" />
                                <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700">
                                    @foreach ($owners ?? [] as $owner)
                                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer hover:text-indigo-600 transition-colors">
                                            <input type="checkbox" name="owner_ids[]" value="{{ $owner->id }}" 
                                                   class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500"
                                                   @checked(in_array($owner->id, old('owner_ids', $user->managedOwners->pluck('id')->toArray())))>
                                            {{ $owner->name }}
                                        </label>
                                    @endforeach
                                </div>
                                <p class="mt-2 text-xs text-gray-500">
                                    Housekeepers and Company users will be able to manage properties/sessions for all selected owners.
                                </p>
                            </div>
                        </div>
                    @endif

                    {{-- Role (admin can change any role, owner can assign housekeeper to their housekeepers) --}}
                    @php
                        $authUser = auth()->user();
                        $canChangeRole = false;
                        $roleOptions = [];

                        if ($authUser->hasRole('admin') && $authUser->id !== $user->id) {
                            $canChangeRole = true;
                            $roleOptions = ['admin', 'owner', 'company', 'housekeeper'];
                        } elseif ($authUser->hasRole('company') && !$authUser->hasRole('admin') && $authUser->id !== $user->id) {
                            // Company can assign owner and housekeeper roles
                            $isDirectlyOwned = $user->owner_id === $authUser->id;
                            if ($isDirectlyOwned) {
                                $canChangeRole = true;
                                $roleOptions = ['owner', 'housekeeper'];
                            }
                        } elseif ($authUser->hasRole('owner') && !$authUser->hasRole('admin') && !$authUser->hasRole('company') && $authUser->id !== $user->id) {
                            // Owner can only assign housekeeper role
                            if ($user->owner_id === $authUser->id) {
                                $canChangeRole = true;
                                $roleOptions = ['housekeeper'];
                            }
                        }
                    @endphp

                    @if ($canChangeRole)
                        <div>
                            <x-form.label value="Role" />
                            <x-form.select name="role" class="w-full" x-model="selectedRole">
                                <option value="">— Keep Current Role —</option>
                                @foreach ($roleOptions as $roleOption)
                                    <option value="{{ $roleOption }}" @selected(old('role', $user->roles->first()?->name) === $roleOption)>
                                        {{ ucfirst($roleOption) }}
                                    </option>
                                @endforeach
                            </x-form.select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Current: <span class="font-medium">{{ $user->roles->first()?->name ?? 'No role' }}</span>
                                @if ($authUser->hasRole('owner') && !$authUser->hasRole('admin'))
                                    <br>You can only assign the housekeeper role.
                                @endif
                            </p>
                            <x-form.error :messages="$errors->get('role')" />
                        </div>
                    @else
                        <div>
                            <x-form.label value="Role" />
                            <x-form.input type="text" class="w-full bg-gray-100 dark:bg-gray-700"
                                :value="$user->roles->first()?->name ?? 'No role'" disabled />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                @if ($authUser->id === $user->id)
                                    You cannot change your own role.
                                @else
                                    You do not have permission to change this user's role.
                                @endif
                            </p>
                        </div>
                    @endif

                    {{-- Password (optional) --}}
                    <div>
                        <x-form.label value="New Password (optional)" />
                        <x-form.input name="password" type="password" class="w-full"
                            placeholder="Leave blank to keep current" autocomplete="new-password" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Leave blank if you don't want to change the password.
                        </p>
                        <x-form.error :messages="$errors->get('password')" />
                    </div>

                    {{-- Password Confirmation --}}
                    <div>
                        <x-form.label value="Confirm New Password" />
                        <x-form.input name="password_confirmation" type="password" class="w-full"
                            placeholder="Re-enter new password" autocomplete="new-password" />
                        <x-form.error :messages="$errors->get('password_confirmation')" />
                    </div>

                    {{-- Property Assignment --}}
                    @if(isset($properties) && $properties->count() > 0)
                        <div class="md:col-span-2 pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Assigned Properties</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                                Select which properties this user can access and manage.
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700">
                                @foreach ($properties as $property)
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer hover:text-indigo-600 transition-colors p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50">
                                        <input type="checkbox" name="property_ids[]" value="{{ $property->id }}"
                                               class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500"
                                               @checked(in_array($property->id, old('property_ids', $user->properties->pluck('id')->toArray())))>
                                        <span>{{ $property->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Preferences --}}
                    @php
                        $preferences = $user->preferences ?? [];
                        $autoSaveEnabled = $preferences['auto_save_enabled'] ?? 1;
                        $autoSaveDelay = $preferences['auto_save_delay'] ?? 400;
                        $notifySessionStarted = $preferences['notify_session_started'] ?? 1;
                        $notifySessionCompleted = $preferences['notify_session_completed'] ?? 1;
                        $notifyAssignments = $preferences['notify_assignments'] ?? 1;
                    @endphp
                    
                    <div class="md:col-span-2 pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">User Preferences</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Auto-save Settings --}}
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Auto-save Settings</h4>
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
                                        <x-form.label for="auto_save_delay" value="Auto-save Delay (ms)" class="text-xs" />
                                        <x-form.input id="auto_save_delay" name="preferences[auto_save_delay]" type="number" min="100" max="5000" step="100"
                                            :value="old('preferences.auto_save_delay', $autoSaveDelay)" class="w-full mt-1" />
                                    </div>
                                </div>
                            </div>

                            {{-- Notification Preferences --}}
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Notification Preferences</h4>
                                <div class="space-y-3">
                                    <label class="flex items-center">
                                        <input type="hidden" name="preferences[notify_session_started]" value="0">
                                        <x-form.checkbox name="preferences[notify_session_started]" value="1"
                                            :checked="old('preferences.notify_session_started', $notifySessionStarted) == 1" />
                                        <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                            Notify when a session starts
                                        </span>
                                    </label>

                                    <label class="flex items-center">
                                        <input type="hidden" name="preferences[notify_session_completed]" value="0">
                                        <x-form.checkbox name="preferences[notify_session_completed]" value="1"
                                            :checked="old('preferences.notify_session_completed', $notifySessionCompleted) == 1" />
                                        <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                            Notify when a session completes
                                        </span>
                                    </label>

                                    <label class="flex items-center">
                                        <input type="hidden" name="preferences[notify_assignments]" value="0">
                                        <x-form.checkbox name="preferences[notify_assignments]" value="1"
                                            :checked="old('preferences.notify_assignments', $notifyAssignments) == 1" />
                                        <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                            Notify on new assignments
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap justify-end gap-3">
                <x-button type="submit" class="bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500">
                    Update User
                </x-button>
                <x-button variant="secondary" href="{{ route('users.index') }}">
                    Cancel
                </x-button>
            </div>
        </form>
    </x-card>

    {{-- Alpine.js helpers --}}
    <script>
        function userEditForm() {
            return {
                previewUrl: null,
                selectedRole: @json(old('role', $user->roles->first()?->name ?? '')),

                preview(event) {
                    const file = event.target.files?.[0];
                    if (!file) return;
                    this.previewUrl = URL.createObjectURL(file);
                },
            }
        }
    </script>
</x-app-layout>

