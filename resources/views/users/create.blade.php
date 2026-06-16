<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Create New User
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Add a new user to the system. Only administrators can create users.
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
        <form x-data="userForm()" method="post" action="{{ route('users.store') }}"
            enctype="multipart/form-data">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Left column: Profile Photo Upload --}}
                <div class="lg:col-span-1">
                    <x-form.label value="Profile Photo" />
                    <div class="mt-1 border-2 border-dashed rounded-2xl p-6 flex flex-col items-center justify-center text-center cursor-pointer transition-colors"
                        :class="dragOver ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-900/20' :
                            'border-gray-300 dark:border-gray-600 bg-gray-50/40 dark:bg-gray-800/40'"
                        @click="$refs.file.click()" @dragover.prevent="dragOver = true"
                        @dragleave.prevent="dragOver = false" @drop.prevent="handleDrop($event)">
                        <template x-if="!previewUrl">
                            <div class="text-gray-500 dark:text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-16 w-16" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <p class="mt-3 text-sm font-medium">Drag &amp; drop or click to upload</p>
                                <p class="mt-1 text-xs text-gray-400">
                                    JPG, PNG, WebP — up to 5MB
                                </p>
                            </div>
                        </template>

                        <template x-if="previewUrl">
                            <div class="w-full">
                                <img :src="previewUrl" alt="Preview"
                                    class="rounded-full object-cover h-40 w-40 mx-auto shadow-lg border-4 border-white dark:border-gray-700" />
                                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                    Click or drop a new file to replace the photo.
                                </p>
                            </div>
                        </template>

                        <input type="file" name="profile_photo" x-ref="file" class="hidden" @change="preview($event)"
                            accept="image/*" />
                    </div>

                    @error('profile_photo')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Right column: Form fields --}}
                <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Name --}}
                    <div class="md:col-span-2">
                        <x-form.label value="Full Name" />
                        <x-form.input name="name" class="w-full" required :value="old('name')"
                            placeholder="e.g. John Doe" autofocus />
                        <x-form.error :messages="$errors->get('name')" />
                    </div>

                    {{-- Email --}}
                    <div class="md:col-span-2">
                        <x-form.label value="Email Address" />
                        <x-form.input name="email" type="email" class="w-full" required :value="old('email')"
                            placeholder="e.g. john@example.com" />
                        <x-form.error :messages="$errors->get('email')" />
                    </div>

                    {{-- Phone Number --}}
                    <div>
                        <x-form.label value="Phone Number (optional)" />
                        <x-form.input name="phone_number" type="tel" class="w-full" :value="old('phone_number')"
                            placeholder="e.g. +1 234 567 8900" />
                        <x-form.error :messages="$errors->get('phone_number')" />
                    </div>

                    {{-- Role --}}
                    <div>
                        <x-form.label value="Role" />
                        <x-form.select name="role" class="w-full" required x-model="selectedRole">
                            <option value="">— Select Role —</option>
                            @if (auth()->user()->hasRole('admin'))
                                <option value="admin" @selected(old('role') === 'admin')>Administrator</option>
                                <option value="owner" @selected(old('role') === 'owner')>Owner</option>
                                <option value="company" @selected(old('role') === 'company')>Company</option>
                                <option value="housekeeper" @selected(old('role') === 'housekeeper')>Housekeeper</option>
                            @elseif (auth()->user()->hasRole('company'))
                                <option value="owner" @selected(old('role') === 'owner')>Owner</option>
                                <option value="housekeeper" @selected(old('role') === 'housekeeper')>Housekeeper</option>
                            @else
                                <option value="housekeeper" @selected(old('role') === 'housekeeper')>Housekeeper</option>
                            @endif
                        </x-form.select>
                        @if (auth()->user()->hasRole('owner') && !auth()->user()->hasRole('admin') && !auth()->user()->hasRole('company'))
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Owners can only create housekeeper users.
                            </p>
                        @elseif (auth()->user()->hasRole('company') && !auth()->user()->hasRole('admin'))
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Companies can create owner and housekeeper users.
                            </p>
                        @endif
                        <x-form.error :messages="$errors->get('role')" />
                    </div>

                    {{-- Master Owner (Primary association) --}}
                    @if (auth()->user()->hasRole('admin') || auth()->user()->hasRole('company'))
                        <div x-show="selectedRole === 'housekeeper' || selectedRole === 'company'" x-cloak class="md:col-span-2">
                            <x-form.label value="Primary Owner / Company" />
                            <x-form.select name="owner_id" class="w-full">
                                <option value="">— Direct (Managed by you) —</option>
                                @foreach ($owners ?? [] as $owner)
                                    <option value="{{ $owner->id }}" @selected(old('owner_id') == $owner->id)>
                                        {{ $owner->name }} ({{ ucfirst($owner->roles->first()?->name) }})
                                    </option>
                                @endforeach
                            </x-form.select>
                        </div>

                        {{-- Multiple Owner Assignment --}}
                        <div x-show="selectedRole === 'housekeeper' || selectedRole === 'company'" x-cloak class="md:col-span-2 mt-2">
                            <x-form.label value="Also attach to these Owners (Optional)" />
                            <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700">
                                @foreach ($owners ?? [] as $owner)
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer hover:text-indigo-600 transition-colors">
                                        <input type="checkbox" name="owner_ids[]" value="{{ $owner->id }}" 
                                               class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500"
                                               @checked(in_array($owner->id, old('owner_ids', [])))>
                                        {{ $owner->name }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="mt-2 text-xs text-gray-500">
                                Housekeepers and Company users will be able to manage properties/sessions for all selected owners.
                            </p>
                        </div>
                    @endif

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
                                               @checked(in_array($property->id, old('property_ids', [])))>
                                        <span>{{ $property->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Password --}}
                    <div>
                        <x-form.label value="Password" />
                        <x-form.input name="password" type="password" class="w-full" required
                            placeholder="Minimum 8 characters" autocomplete="new-password" />
                        <x-form.error :messages="$errors->get('password')" />
                    </div>

                    {{-- Password Confirmation --}}
                    <div>
                        <x-form.label value="Confirm Password" />
                        <x-form.input name="password_confirmation" type="password" class="w-full" required
                            placeholder="Re-enter password" autocomplete="new-password" />
                        <x-form.error :messages="$errors->get('password_confirmation')" />
                    </div>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap justify-end gap-3">
                <x-button type="submit" class="bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500">
                    Create User
                </x-button>
                <x-button variant="secondary" href="{{ route('users.index') }}">
                    Cancel
                </x-button>
            </div>
        </form>
    </x-card>

    {{-- Alpine.js helpers --}}
    <script>
        function userForm() {
            return {
                previewUrl: null,
                dragOver: false,
                selectedRole: @json(old('role', '')),

                preview(event) {
                    const file = event.target.files?.[0];
                    if (!file) return;
                    this.previewUrl = URL.createObjectURL(file);
                },

                handleDrop(evt) {
                    this.dragOver = false;
                    const file = evt.dataTransfer.files?.[0];
                    if (!file) return;

                    this.$refs.file.files = evt.dataTransfer.files;
                    this.preview({
                        target: {
                            files: this.$refs.file.files
                        }
                    });
                },
            }
        }
    </script>
</x-app-layout>

