<section>
    <header>
        <h2 class="text-lg font-medium">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form
        method="post"
        action="{{ route('profile.update') }}"
        class="mt-6 space-y-6"
        enctype="multipart/form-data"
        x-data="profilePhotoForm()"
    >
        @csrf
        @method('patch')

        {{-- Profile Photo --}}
        <div class="space-y-2">
            <x-form.label value="Profile Photo" />
            <div class="flex items-center gap-6">
                <div class="flex-shrink-0">
                    <img :src="previewUrl || '{{ $user->profile_photo_url }}'" 
                        alt="Profile photo"
                        class="h-24 w-24 rounded-full object-cover ring-4 ring-gray-200 dark:ring-gray-700 shadow-lg"
                        :class="previewUrl ? 'ring-indigo-500' : ''">
                </div>
                <div class="flex-1">
                    <input type="file" name="profile_photo" x-ref="file" class="hidden" 
                        @change="preview($event)" accept="image/*">
                    <div class="flex flex-col gap-2">
                        <x-button type="button" variant="secondary" @click="$refs.file.click()">
                            Choose New Photo
                        </x-button>
                        @if ($user->profile_photo_path)
                            <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer">
                                <x-form.checkbox name="remove_profile_photo" value="1" />
                                Remove photo
                            </label>
                        @endif
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            JPG, PNG, WebP — up to 5MB
                        </p>
                    </div>
                </div>
            </div>
            <x-form.error :messages="$errors->get('profile_photo')" />
        </div>

        <div class="space-y-2">
            <x-form.label
                for="name"
                :value="__('Name')"
            />

            <x-form.input
                id="name"
                name="name"
                type="text"
                class="block w-full"
                :value="old('name', $user->name)"
                required
                autofocus
                autocomplete="name"
            />

            <x-form.error :messages="$errors->get('name')" />
        </div>

        <div class="space-y-2">
            <x-form.label
                for="email"
                :value="__('Email')"
            />

            <x-form.input
                id="email"
                name="email"
                type="email"
                class="block w-full"
                :value="old('email', $user->email)"
                required
                autocomplete="email"
            />

            <x-form.error :messages="$errors->get('email')" />
        </div>

        <div class="space-y-2">
            <x-form.label
                for="phone_number"
                :value="__('Phone Number')"
            />

            <x-form.input
                id="phone_number"
                name="phone_number"
                type="tel"
                class="block w-full"
                :value="old('phone_number', $user->phone_number)"
                autocomplete="tel"
                placeholder="e.g. +1 234 567 8900"
            />

            <x-form.error :messages="$errors->get('phone_number')" />
        </div>

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800 dark:text-gray-300">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500  dark:text-gray-400 dark:hover:text-gray-200 dark:focus:ring-offset-gray-800">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-button>
                {{ __('Save') }}
            </x-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600 dark:text-gray-400"
                >
                    {{ __('Saved.') }}
                </p>
            @endif
        </div>
    </form>

    <script>
        function profilePhotoForm() {
            return {
                previewUrl: null,
                preview(event) {
                    const file = event.target.files?.[0];
                    if (!file) return;
                    this.previewUrl = URL.createObjectURL(file);
                }
            }
        }
    </script>
</section>
