{{-- resources/views/properties/edit.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 max-w-full overflow-hidden">
            <div class="flex-1 min-w-0 max-w-full">
                <h2 class="font-semibold text-lg sm:text-xl text-gray-800 leading-tight break-words">
                    Edit Property
                </h2>
                <p class="mt-1 text-xs sm:text-sm text-gray-500 break-words">
                    Update this property. Latitude &amp; longitude will be updated automatically from the address if
                    left empty.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 flex-shrink-0 w-full sm:w-auto">
                <x-button variant="secondary" href="{{ route('properties.index') }}" class="w-full sm:w-auto text-center whitespace-nowrap">
                    Back to List
                </x-button>
                <x-button variant="secondary" href="{{ route('properties.rooms.index', $property) }}" class="w-full sm:w-auto text-center whitespace-nowrap">
                    Rooms
                </x-button>
                <x-button variant="secondary" href="{{ route('properties.property-tasks.index', $property) }}" class="w-full sm:w-auto text-center whitespace-nowrap">
                    <span class="hidden sm:inline">Property Tasks</span>
                    <span class="sm:hidden">Tasks</span>
                </x-button>
            </div>
        </div>
    </x-slot>

    <x-card class="max-w-full">
        <form x-data="propertyEditForm()" x-init="init()" method="post" action="{{ route('properties.update', $property) }}"
            enctype="multipart/form-data" @submit.prevent="handleSubmit($event)" class="max-w-full">
            @csrf
            @method('PUT')

            {{-- If the current user is an owner (not admin), lock owner_id --}}
            @if (!($isAdmin ?? false) && auth()->user()->hasRole('owner'))
                <input type="hidden" name="owner_id" value="{{ auth()->id() }}">
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 max-w-full">
                {{-- Left column: Image (current + replace/remove) --}}
                <div class="lg:col-span-1 max-w-full overflow-hidden">
                    <x-form.label value="Property Photo" />
                    <div class="mt-1 border-2 border-dashed dark:border-gray-700 rounded-xl sm:rounded-2xl p-3 sm:p-4 text-center bg-gray-50/40 dark:bg-gray-800/40 max-w-full overflow-hidden">
                        @php
                            $photoUrl = method_exists($property, 'getPhotoUrlAttribute')
                                ? $property->photo_url
                                : ($property->photo_path
                                    ? (Str::startsWith($property->photo_path, ['http://', 'https://'])
                                        ? $property->photo_path
                                        : url('file/' . ltrim($property->photo_path, '/')))
                                    : asset('images/placeholders/property.png'));
                        @endphp

                        <template x-if="!previewUrl">
                            <img src="{{ $photoUrl }}" alt="Current photo"
                                class="rounded-xl object-cover h-48 w-full shadow-sm max-w-full" />
                        </template>

                        <template x-if="previewUrl">
                            <div class="w-full max-w-full overflow-hidden">
                                <img :src="previewUrl" alt="Preview"
                                    class="rounded-xl object-cover h-48 w-full shadow-sm max-w-full" />
                                <template x-if="previewUrl && !hasFileSelected">
                                    <div class="mt-2 p-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-800 max-w-full overflow-hidden">
                                        <p class="font-semibold break-words">⚠️ Image preview restored</p>
                                        <p class="mt-1 break-words">Please click "Choose File" to re-select the image file. Browser security requires this after a page refresh.</p>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <input type="file" name="photo" class="hidden" x-ref="file" @change="preview($event)"
                            accept="image/*" />

                        <div class="mt-3 flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-2 sm:gap-3 max-w-full overflow-hidden">
                            <x-button type="button" variant="secondary" @click="$refs.file.click()" class="w-full sm:w-auto whitespace-nowrap">
                                Choose File
                            </x-button>

                            @if ($property->photo_path)
                                <label class="inline-flex items-center justify-center gap-2 text-sm text-gray-600 cursor-pointer break-words">
                                    <x-form.checkbox name="remove_photo" value="1" />
                                    <span class="break-words">Remove photo</span>
                                </label>
                            @endif
                        </div>

                        @error('photo')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Right column: Fields --}}
                <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4 max-w-full overflow-visible">
                    {{-- Admin & Company owner select --}}
                    @hasanyrole('admin|company')
                        <div class="md:col-span-2">
                            <x-form.label value="Owner" />
                            <x-form.select name="owner_id" class="w-full">
                                <option value="" disabled>— Select Owner —</option>
                                @foreach ($owners ?? [] as $id => $name)
                                    <option value="{{ $id }}" @selected(old('owner_id', $property->owner_id) == $id)>{{ $name }}
                                    </option>
                                @endforeach
                            </x-form.select>
                            <x-form.error :messages="$errors->get('owner_id')" />
                        </div>
                    @endhasanyrole

                    {{-- Name --}}
                    <div class="md:col-span-2">
                        <x-form.label value="Name" />
                        <x-form.input name="name" class="w-full" required :value="old('name', $property->name)"
                            placeholder="e.g. Seaside Apartment 3B" />
                        <x-form.error :messages="$errors->get('name')" />
                    </div>

                    {{-- Address --}}
                    <div class="md:col-span-2 relative">
                        <x-form.label value="Address (optional)" />
                        <x-form.input
                            name="address"
                            id="address-autocomplete"
                            class="w-full"
                            x-model="address"
                            @input="handleAddressInput()"
                            @focus="showSuggestions = true"
                            @blur="setTimeout(() => showSuggestions = false, 200)"
                            :value="old('address', $property->address)"
                            placeholder="Start typing address..."
                            autocomplete="off"
                        />

                        {{-- Address Autocomplete Dropdown --}}
                        <div
                            x-show="showSuggestions && suggestions.length > 0"
                            x-cloak
                            class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                            style="top: 100%;"
                        >
                            <template x-for="(suggestion, index) in suggestions" :key="index">
                                <button
                                    type="button"
                                    class="w-full px-3 py-1.5 text-left text-xs hover:bg-gray-100 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 last:border-0 flex items-start gap-2"
                                    @click="selectSuggestion(suggestion)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-gray-100" x-text="suggestion.display_name || suggestion.structured_formatting?.main_text || suggestion.description"></p>
                                        <p class="text-[10px] text-gray-500 dark:text-gray-400" x-text="suggestion.type || suggestion.structured_formatting?.secondary_text || ''"></p>
                                    </div>
                                </button>
                            </template>
                        </div>

                        <template x-if="isLoadingSuggestions">
                            <div class="absolute right-3 top-9">
                                <svg class="animate-spin h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </template>

                        <div class="mt-1 space-y-1">
                            <p class="text-xs text-gray-500 flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 9v3m0 4h.01M12 3a9 9 0 100 18 9 9 0 000-18z" />
                                </svg>
                                <span>
                                    If you provide an address and leave Latitude/Longitude empty,
                                    we’ll auto-fill them for you.
                                </span>
                            </p>

                            <p class="text-xs text-gray-500">
                                You do <span class="font-semibold">not</span> need to fetch latitude/longitude manually.
                                They’re updated automatically when you save.
                            </p>

                            <template x-if="isGeocoding">
                                <p class="text-xs text-indigo-600 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 animate-spin"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 3a9 9 0 019 9m-9 9a9 9 0 01-9-9" />
                                    </svg>
                                    Fetching coordinates&hellip; please wait.
                                </p>
                            </template>

                            <template x-if="geocodeError">
                                <p class="text-xs text-red-600" x-text="geocodeError"></p>
                            </template>
                        </div>

                        <x-form.error :messages="$errors->get('address')" />
                    </div>

                    {{-- Latitude --}}
                    <div>
                        <x-form.label value="Latitude (optional)" />
                        <x-form.input name="latitude" class="w-full" x-model="latitude" :value="old('latitude', $property->latitude)"
                            inputmode="decimal"
                            x-on:input="
                                $event.target.value = ($event.target.value || '')
                                    .replace(/[^-0-9.]/g, '')
                                    .replace(/(\..*)\./g, '$1')
                                    .replace(/(?!^)-/g, '');
                                latitude = $event.target.value;
                            "
                            placeholder="Auto-filled from address" />
                        <x-form.error :messages="$errors->get('latitude')" />
                    </div>

                    {{-- Longitude --}}
                    <div>
                        <x-form.label value="Longitude (optional)" />
                        <x-form.input name="longitude" class="w-full" x-model="longitude" :value="old('longitude', $property->longitude)"
                            inputmode="decimal"
                            x-on:input="
                                $event.target.value = ($event.target.value || '')
                                    .replace(/[^-0-9.]/g, '')
                                    .replace(/(\..*)\./g, '$1')
                                    .replace(/(?!^)-/g, '');
                                longitude = $event.target.value;
                            "
                            placeholder="Auto-filled from address" />
                        <x-form.error :messages="$errors->get('longitude')" />
                    </div>

                    {{-- Timezone --}}
                    <div class="md:col-span-2">
                        <x-form.label value="Property Timezone" />
                        @php
                            $currentTz = old('timezone', $property->timezone ?? config('app.timezone', '+00:00'));
                        @endphp
                        <x-form.select name="timezone" class="w-full" required x-ref="timezoneSelect">
                            <option value="-12:00" @selected($currentTz === '-12:00')>Eniwetok, Kwajalein</option>
                            <option value="-11:00" @selected($currentTz === '-11:00')>Midway Island, Samoa</option>
                            <option value="-10:00" @selected($currentTz === '-10:00')>Hawaii</option>
                            <option value="-09:50" @selected($currentTz === '-09:50')>Taiohae</option>
                            <option value="-09:00" @selected($currentTz === '-09:00')>Alaska</option>
                            <option value="-08:00" @selected($currentTz === '-08:00')>Pacific Time (US &amp; Canada)</option>
                            <option value="-07:00" @selected($currentTz === '-07:00')>Mountain Time (US &amp; Canada)</option>
                            <option value="-06:00" @selected($currentTz === '-06:00')>Central Time (US &amp; Canada), Mexico City</option>
                            <option value="-05:00" @selected($currentTz === '-05:00')>Eastern Time (US &amp; Canada), Bogota, Lima</option>
                            <option value="-04:50" @selected($currentTz === '-04:50')>Caracas</option>
                            <option value="-04:00" @selected($currentTz === '-04:00')>Atlantic Time (Canada), Caracas, La Paz</option>
                            <option value="-03:50" @selected($currentTz === '-03:50')>Newfoundland</option>
                            <option value="-03:00" @selected($currentTz === '-03:00')>Brazil, Buenos Aires, Georgetown</option>
                            <option value="-02:00" @selected($currentTz === '-02:00')>Mid-Atlantic</option>
                            <option value="-01:00" @selected($currentTz === '-01:00')>Azores, Cape Verde Islands</option>
                            <option value="+00:00" @selected($currentTz === '+00:00')>Western Europe Time, London, Lisbon, Casablanca</option>
                            <option value="+01:00" @selected($currentTz === '+01:00')>Brussels, Copenhagen, Madrid, Paris</option>
                            <option value="+02:00" @selected($currentTz === '+02:00')>Kaliningrad, South Africa</option>
                            <option value="+03:00" @selected($currentTz === '+03:00')>Baghdad, Riyadh, Moscow, St. Petersburg</option>
                            <option value="+03:50" @selected($currentTz === '+03:50')>Tehran</option>
                            <option value="+04:00" @selected($currentTz === '+04:00')>Abu Dhabi, Muscat, Baku, Tbilisi</option>
                            <option value="+04:50" @selected($currentTz === '+04:50')>Kabul</option>
                            <option value="+05:00" @selected($currentTz === '+05:00')>Ekaterinburg, Islamabad, Karachi, Tashkent</option>
                            <option value="+05:50" @selected($currentTz === '+05:50')>Bombay, Calcutta, Madras, New Delhi</option>
                            <option value="+05:75" @selected($currentTz === '+05:75')>Kathmandu, Pokhara</option>
                            <option value="+06:00" @selected($currentTz === '+06:00')>Almaty, Dhaka, Colombo</option>
                            <option value="+06:50" @selected($currentTz === '+06:50')>Yangon, Mandalay</option>
                            <option value="+07:00" @selected($currentTz === '+07:00')>Bangkok, Hanoi, Jakarta</option>
                            <option value="+08:00" @selected($currentTz === '+08:00')>Beijing, Perth, Singapore, Hong Kong</option>
                            <option value="+08:75" @selected($currentTz === '+08:75')>Eucla</option>
                            <option value="+09:00" @selected($currentTz === '+09:00')>Tokyo, Seoul, Osaka, Sapporo, Yakutsk</option>
                            <option value="+09:50" @selected($currentTz === '+09:50')>Adelaide, Darwin</option>
                            <option value="+10:00" @selected($currentTz === '+10:00')>Eastern Australia, Guam, Vladivostok</option>
                            <option value="+10:50" @selected($currentTz === '+10:50')>Lord Howe Island</option>
                            <option value="+11:00" @selected($currentTz === '+11:00')>Magadan, Solomon Islands, New Caledonia</option>
                            <option value="+11:50" @selected($currentTz === '+11:50')>Norfolk Island</option>
                            <option value="+12:00" @selected($currentTz === '+12:00')>Auckland, Wellington, Fiji, Kamchatka</option>
                            <option value="+12:75" @selected($currentTz === '+12:75')>Chatham Islands</option>
                            <option value="+13:00" @selected($currentTz === '+13:00')>Apia, Nukualofa</option>
                            <option value="+14:00" @selected($currentTz === '+14:00')>Line Islands, Tokelau</option>
                        </x-form.select>
                        <x-form.error :messages="$errors->get('timezone')" />
                        <p class="mt-1 text-xs text-gray-500">Determines when "today"'s cleaning sessions unlock for the housekeeper.</p>
                    </div>

                    {{-- Integrations Section --}}
                    <div class="md:col-span-2 mt-4">
                        <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider mb-4 flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                            </svg>
                            Calendar Sync (Airbnb / Vrbo)
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-blue-50/50 dark:bg-blue-900/10 rounded-xl p-4 border border-blue-100 dark:border-blue-900/30">
                            {{-- Airbnb iCal --}}
                            <div>
                                <x-form.label value="Airbnb iCal URL" />
                                <x-form.input name="airbnb_ical_url" class="w-full bg-white dark:bg-gray-800" :value="old('airbnb_ical_url', $property->airbnb_ical_url ?? $property->ical_url)"
                                    placeholder="https://www.airbnb.com/calendar/ical/..." />
                            </div>

                            {{-- Vrbo iCal --}}
                            <div>
                                <x-form.label value="Vrbo iCal URL" />
                                <x-form.input name="vrbo_ical_url" class="w-full bg-white dark:bg-gray-800" :value="old('vrbo_ical_url', $property->vrbo_ical_url)"
                                    placeholder="https://www.vrbo.com/icalendar/..." />
                            </div>

                            <div class="md:col-span-2">
                                <p class="mt-1 text-xs text-gray-500 leading-relaxed">
                                    Paste your property's iCal links to automatically sync bookings. This ensures rooms and sessions are aligned with your actual guest arrivals.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-2 mt-6 sm:mt-8 sm:justify-end max-w-full">
                <x-button x-bind:disabled="isGeocoding"
                    x-bind:class="isGeocoding ? 'opacity-60 cursor-not-allowed' : ''" class="w-full sm:w-auto whitespace-nowrap">
                    <span x-show="!isGeocoding">Update</span>
                    <span x-show="isGeocoding">Fetching coordinates…</span>
                </x-button>

                <x-button variant="secondary" href="{{ route('properties.index') }}" class="w-full sm:w-auto whitespace-nowrap">
                    Cancel
                </x-button>
            </div>
        </form>
    </x-card>

    {{-- Google Places API --}}
    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.places_api_key') }}&libraries=places"></script>

    {{-- Alpine helpers (kept as before; do not modify photo uploader behavior/UI) --}}
    <script>
        function propertyEditForm() {
            return {
                address: @json(old('address', $property->address)),
                latitude: @json(old('latitude', $property->latitude)),
                longitude: @json(old('longitude', $property->longitude)),
                previewUrl: null,
                hasFileSelected: false,

                // UX state
                isGeocoding: false,
                geocodeError: '',
                isSubmitting: false,

                // Geocoding provider: forced to google
                geocodingProvider: 'google',

                // Address Autocomplete
                suggestions: [],
                showSuggestions: false,
                isLoadingSuggestions: false,
                autocompleteService: null,
                placesService: null,
                searchTimeout: null,

                init() {
                    // Restore preview
                    const savedPreview = sessionStorage.getItem('property_photo_preview_edit');
                    if (savedPreview) {
                        this.previewUrl = savedPreview;
                        this.hasFileSelected = false;
                    }

                    // Check file input
                    this.$nextTick(() => {
                        if (this.$refs.file && this.$refs.file.files.length > 0) {
                            this.hasFileSelected = true;
                            const file = this.$refs.file.files[0];
                            if (file) {
                                const reader = new FileReader();
                                reader.onload = (e) => {
                                    this.previewUrl = e.target.result;
                                    sessionStorage.setItem('property_photo_preview_edit', e.target.result);
                                };
                                reader.readAsDataURL(file);
                            }
                        }
                    });

                    @if($errors->isEmpty())
                        setTimeout(() => {
                            if (!this.hasFileSelected) {
                                sessionStorage.removeItem('property_photo_preview_edit');
                            }
                        }, 100);
                    @endif

                    // Initialize Google Places Autocomplete
                    if (typeof google !== 'undefined' && google.maps && google.maps.places) {
                        this.autocompleteService = new google.maps.places.AutocompleteService();
                        const mapDiv = document.createElement('div');
                        this.placesService = new google.maps.places.PlacesService(mapDiv);
                    }
                },

                handleAddressInput() {
                    clearTimeout(this.searchTimeout);

                    if (!this.address || this.address.length < 3) {
                        this.suggestions = [];
                        return;
                    }

                    this.searchTimeout = setTimeout(() => {
                        this.fetchPlaceSuggestions();
                    }, 300);
                },

                async fetchPlaceSuggestions() {
                    this.isLoadingSuggestions = true;

                    // STRICT: Google Places Only
                    if (!this.autocompleteService) {
                         if (typeof google === 'undefined') {
                             console.error('Google Maps API not loaded. Check API key.');
                             this.geocodeError = 'Google Maps API not loaded.';
                         }
                         this.isLoadingSuggestions = false;
                         return;
                    }

                    try {
                        const request = {
                            input: this.address,
                            // 'geocode' prevents finding businesses if used alone, but 'establishment' helps.
                            // To be safe and broad like user wants: use both or empty.
                            types: ['geocode', 'establishment']
                        };

                        this.autocompleteService.getPlacePredictions(request, (predictions, status) => {
                            this.isLoadingSuggestions = false;

                            if (status === google.maps.places.PlacesServiceStatus.OK && predictions) {
                                this.suggestions = predictions;
                                this.showSuggestions = true;
                            } else {
                                this.suggestions = [];
                                if (status !== google.maps.places.PlacesServiceStatus.ZERO_RESULTS) {
                                    console.warn('Google Autocomplete status:', status);
                                }
                            }
                        });
                    } catch (error) {
                        console.warn('Google Places autocomplete error:', error);
                        this.isLoadingSuggestions = false;
                        this.suggestions = [];
                    }
                },

                selectSuggestion(suggestion) {
                    this.showSuggestions = false;
                    this.suggestions = [];

                    // STRICT: Google Places Only
                    this.address = suggestion.description;

                    if (this.placesService && suggestion.place_id) {
                        this.isGeocoding = true;
                        this.placesService.getDetails(
                            { placeId: suggestion.place_id, fields: ['geometry', 'formatted_address'] },
                            (place, status) => {
                                this.isGeocoding = false;
                                if (status === google.maps.places.PlacesServiceStatus.OK && place.geometry) {
                                    this.latitude = place.geometry.location.lat();
                                    this.longitude = place.geometry.location.lng();
                                    
                                     // Use the official formatted address if available
                                    if (place.formatted_address) {
                                         this.address = place.formatted_address;
                                    }

                                    // Auto-detect timezone using Google Time Zone API (uses rawOffset = standard, no DST)
                                    this.detectTimezone(this.latitude, this.longitude);
                                }
                            }
                        );
                    }
                },

                async detectTimezone(lat, lng) {
                  try {
                    const timestamp = Math.floor(Date.now() / 1000);
                    const apiKey = @js(config('services.google.places_api_key'));
                    const url = `https://maps.googleapis.com/maps/api/timezone/json?location=${lat},${lng}&timestamp=${timestamp}&key=${apiKey}`;
                    const response = await fetch(url);
                    const data = await response.json();

                    if (data.status === 'OK') {
                      // rawOffset = standard offset in seconds (no DST)
                      const rawOffsetSeconds = data.rawOffset;
                      const totalMinutes = rawOffsetSeconds / 60;
                      this.setTimezoneFromOffset(totalMinutes);
                    }
                  } catch (error) {
                    console.warn('Timezone auto-detect failed:', error);
                  }
                },

                setTimezoneFromOffset(offsetMinutes) {
                  const sign = offsetMinutes >= 0 ? '+' : '-';
                  const absMinutes = Math.abs(offsetMinutes);
                  const hours = Math.floor(absMinutes / 60);
                  const mins = absMinutes % 60;

                  // Convert real minutes to the dropdown's decimal format: 30min=50, 45min=75
                  let decimalPart = '00';
                  if (mins === 30) decimalPart = '50';
                  else if (mins === 45) decimalPart = '75';
                  else if (mins > 0) decimalPart = String(Math.round((mins / 60) * 100)).padStart(2, '0');

                  const value = sign + String(hours).padStart(2, '0') + ':' + decimalPart;

                  // Try x-ref first, then fallback to querySelector
                  let select = this.$refs.timezoneSelect;
                  if (!select || !select.options) {
                    select = document.querySelector('select[name="timezone"]');
                  }
                  if (select) {
                    // Check if this value exists in the dropdown
                    const optionExists = Array.from(select.options).some(o => o.value === value);
                    if (optionExists) {
                      select.value = value;
                      select.dispatchEvent(new Event('change', { bubbles: true }));
                    } else {
                      // Try nearest whole-hour match
                      const fallback = sign + String(hours).padStart(2, '0') + ':00';
                      const fallbackExists = Array.from(select.options).some(o => o.value === fallback);
                      if (fallbackExists) {
                        select.value = fallback;
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                      }
                    }
                  }
                },

                preview(event) {
                    const file = event.target.files?.[0];
                    if (!file) {
                        this.hasFileSelected = false;
                        return;
                    }

                    this.hasFileSelected = true;
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        this.previewUrl = e.target.result;
                        // Save to sessionStorage to preserve on page refresh
                        sessionStorage.setItem('property_photo_preview_edit', e.target.result);
                    };
                    reader.readAsDataURL(file);
                },

                async handleSubmit(event) {
                    const form = event.target; // Capture form reference immediately

                    // If we're already submitting (after geocoding attempt), let it proceed
                    if (this.isSubmitting) {
                        return;
                    }

                    this.geocodeError = '';

                    if (this.isGeocoding) {
                        event.preventDefault();
                        return;
                    }

                    const needsGeocode = this.address && (!this.latitude || !this.longitude);

                    if (needsGeocode) {
                        event.preventDefault();
                        
                        try {
                            await this.geocodeIfNeeded(true); // force on submit
                        } catch (e) {
                            console.error('Geocoding error during submit:', e);
                            // Continue anyway
                        }

                        // If geocoding fails, show a warning but allow submission to continue
                        // since coordinates are optional
                        if (this.address && (!this.latitude || !this.longitude)) {
                            this.geocodeError =
                                'Could not find coordinates for this address. You can continue without them or enter them manually.';
                        }

                        // Set flag and submit the form directly
                        this.isSubmitting = true;
                        form.submit();
                        return;
                    }

                    // Default submission if no geocoding needed
                    this.isSubmitting = true;
                    // form.submit() bypasses HTML5 validation, so we can check validity if desired,
                    // but usually backend validation is the source of truth.
                    // If we want to trigger browser validation visuals:
                    if (form.reportValidity && !form.reportValidity()) {
                        this.isSubmitting = false;
                        return;
                    }
                    
                    form.submit();
                },

                async geocodeIfNeeded(force = false) {
                    if (!this.address) return;

                    // Skip if not forced and both coords already exist
                    if (!force && this.latitude && this.longitude) return;

                    this.isGeocoding = true;
                    this.geocodeError = '';

                    const apiKey = "{{ config('services.google.places_api_key') }}";
                    
                    if (!apiKey) {
                        this.geocodeError = 'Google API Key missing.';
                        this.isGeocoding = false;
                        return;
                    }
                    
                    if (typeof google === 'undefined') {
                        this.geocodeError = 'Google Maps API not loaded.';
                        this.isGeocoding = false;
                        return;
                    }

                    // Use Google Geocoding API
                    try {
                        const geocoder = new google.maps.Geocoder();
                        // Wrap in promise for await compatibility
                        await new Promise((resolve, reject) => {
                             geocoder.geocode({ 'address': this.address }, (results, status) => {
                                if (status === 'OK' && results[0]) {
                                    this.latitude = results[0].geometry.location.lat();
                                    this.longitude = results[0].geometry.location.lng();
                                    resolve();
                                } else {
                                    if (status === 'ZERO_RESULTS') {
                                        this.geocodeError = 'Address not found on Google Maps.';
                                    } else {
                                        this.geocodeError = 'Geocoding error: ' + status;
                                    }
                                    resolve(); // Resolve anyway to stop loading state
                                }
                            });
                        });
                    } catch (error) {
                        console.warn('Geocoding failed', error);
                        this.geocodeError =
                            'Unable to fetch coordinates right now. Please try again or enter them manually.';
                    } finally {
                        this.isGeocoding = false;
                    }
                },
            }
        }
    </script>

    {{-- Assigned Rooms + Property Tasks (AJAX) --}}
    <div class="mt-6">
        <div
            x-data="propertyAssignmentsPanel({
                roomsUrl: @js(route('api.properties.assigned-rooms', $property)),
                tasksUrl: @js(route('api.properties.assigned-property-tasks', $property)),
            })"
            x-init="init()"
        >
            <x-card class="max-w-full">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">Assigned Items</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Loaded via API/AJAX — use the buttons to manage assignments.
                        </p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-2">
                        <x-button type="button" variant="secondary" @click="refreshAll()" class="w-full sm:w-auto whitespace-nowrap">
                            Refresh
                        </x-button>
                        <x-button variant="secondary" href="{{ route('properties.rooms.index', $property) }}" class="w-full sm:w-auto whitespace-nowrap">
                            Manage Rooms
                        </x-button>
                        <x-button variant="secondary" href="{{ route('properties.property-tasks.index', $property) }}" class="w-full sm:w-auto whitespace-nowrap">
                            Manage Property Tasks
                        </x-button>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-4">
                    {{-- Rooms --}}
                    <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-4 bg-gray-50/40 dark:bg-gray-800/40">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <h4 class="font-semibold text-gray-800 dark:text-gray-200">Rooms</h4>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:border-indigo-500/20"
                                    x-text="rooms.length"></span>
                            </div>
                            <template x-if="loadingRooms">
                                <span class="text-xs text-indigo-600 dark:text-indigo-300">Loading…</span>
                            </template>
                        </div>

                        <template x-if="roomsError">
                            <p class="mt-2 text-xs text-red-600" x-text="roomsError"></p>
                        </template>

                        <template x-if="!loadingRooms && !roomsError && rooms.length === 0">
                            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No rooms assigned yet.</p>
                        </template>

                        <ul class="mt-3 space-y-2" x-show="rooms.length > 0">
                            <template x-for="room in rooms" :key="room.id">
                                <li class="flex items-center justify-between gap-3 p-2 rounded-lg bg-white dark:bg-gray-900/60 border border-gray-100 dark:border-gray-700">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate" x-text="room.name"></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            <span x-text="'Tasks: ' + (room.tasks_count ?? 0)"></span>
                                            <span class="mx-1">•</span>
                                            <span x-text="'Order: ' + (room.sort_order ?? 0)"></span>
                                        </p>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </div>

                    {{-- Property Tasks --}}
                    <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-4 bg-gray-50/40 dark:bg-gray-800/40">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <h4 class="font-semibold text-gray-800 dark:text-gray-200">Property Tasks</h4>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:border-indigo-500/20"
                                    x-text="tasks.length"></span>
                            </div>
                            <template x-if="loadingTasks">
                                <span class="text-xs text-indigo-600 dark:text-indigo-300">Loading…</span>
                            </template>
                        </div>

                        <template x-if="tasksError">
                            <p class="mt-2 text-xs text-red-600" x-text="tasksError"></p>
                        </template>

                        <template x-if="!loadingTasks && !tasksError && tasks.length === 0">
                            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No property tasks assigned yet.</p>
                        </template>

                        <ul class="mt-3 space-y-2" x-show="tasks.length > 0">
                            <template x-for="task in tasks" :key="task.id">
                                <li class="flex items-center justify-between gap-3 p-2 rounded-lg bg-white dark:bg-gray-900/60 border border-gray-100 dark:border-gray-700">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate" x-text="task.name"></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            <span x-text="phaseLabel(task.phase) || '—'"></span>
                                            <span class="mx-1">•</span>
                                            <span x-text="'Order: ' + (task.sort_order ?? 0)"></span>
                                        </p>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
