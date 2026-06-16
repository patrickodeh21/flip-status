{{-- resources/views/properties/create.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 max-w-full overflow-hidden">
      <div class="flex-1 min-w-0 max-w-full">
        <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-400 leading-tight break-words">
          New Property
        </h2>
        <p class="mt-1 text-xs sm:text-sm text-gray-500 break-words">
          Add a new property. Latitude &amp; longitude will be updated automatically from the address.
        </p>
      </div>

      {{-- Trigger --}}
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-4 flex-shrink-0 w-full sm:w-auto">
        <div x-data class="w-full sm:w-auto">
          <x-button variant="secondary" class="w-full sm:w-auto text-center whitespace-nowrap"
            @click="$dispatch('open-preview-panel', 'rooms-preview')">
            <span class="hidden sm:inline">Preview Room list</span>
            <span class="sm:hidden">Preview Rooms</span>
          </x-button>
        </div>

        <x-button variant="secondary" href="{{ route('properties.index') }}"
          class="w-full sm:w-auto text-center whitespace-nowrap">
          Back to List
        </x-button>
      </div>
    </div>
  </x-slot>


  {{-- Preview panel --}}
  @include('properties.__preview_panel', ['rooms' => $rooms])

  <x-card class="max-w-full">
    <form x-data="propertyForm()" x-init="init()" method="post" action="{{ route('properties.store') }}"
      enctype="multipart/form-data" @submit.prevent="handleSubmit($event)" class="max-w-full">
      @csrf

      {{-- Note: owner_id is automatically set in PropertyStoreRequest::prepareForValidation() for owners --}}

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 max-w-full">
        {{-- Left column: Image uploader --}}
        <div class="lg:col-span-1 max-w-full overflow-hidden">
          <x-form.label value="Property Photo" />

          <div
            class="mt-1 border-2 border-dashed rounded-xl sm:rounded-2xl p-3 sm:p-4 flex flex-col items-center justify-center text-center cursor-pointer transition-colors max-w-full overflow-hidden"
            :class="dragOver ? 'border-indigo-500 bg-indigo-50/40' :
                'border-gray-300 dark:border-gray-500 bg-gray-50/40 dark:bg-gray-800 dark:hover:bg-gray-900'"
            @click="$refs.file.click()" @dragover.prevent="dragOver = true" @dragleave.prevent="dragOver = false"
            @drop.prevent="handleDrop($event)">
            <template x-if="!previewUrl">
              <div class="text-gray-500 max-w-full">
                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7M3 7l5.5 5.5M21 7l-5.5 5.5M12 3v12" />
                </svg>
                <p class="mt-2 text-sm font-medium break-words">Drag &amp; drop or click to upload</p>
                <p class="mt-1 text-xs text-gray-400 break-words">
                  JPG, PNG, WebP — up to ~5MB
                </p>
              </div>
            </template>

            <template x-if="previewUrl">
              <div class="w-full max-w-full overflow-hidden">
                <img :src="previewUrl" alt="Preview"
                  class="rounded-xl object-cover h-48 w-full shadow-sm max-w-full" />
                <p class="mt-2 text-xs text-gray-500 break-words">
                  Click or drop a new file to replace the photo.
                </p>
                <template x-if="previewUrl && !hasFileSelected">
                  <div
                    class="mt-2 p-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-800 max-w-full overflow-hidden">
                    <p class="font-semibold break-words">⚠️ Image preview restored</p>
                    <p class="mt-1 break-words">Please click above to re-select the image file. Browser security
                      requires this after a page refresh.</p>
                  </div>
                </template>
              </div>
            </template>

            <input type="file" name="photo" x-ref="file" class="hidden" @change="preview($event)"
              accept="image/*" />
          </div>

          @error('photo')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
          @enderror
        </div>

        {{-- Right column: Form fields --}}
        <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4 max-w-full overflow-visible">
          {{-- Admin & Company can assign owner --}}
          @hasanyrole('admin|company')
            <div class="md:col-span-2">
              <x-form.label value="Owner" />
              {{-- Expecting $owners = [id => name] from controller --}}
              @php
                $selectedOwnerId = old('owner_id', $defaultOwnerId ?? null);
              @endphp
              <x-form.select name="owner_id" class="w-full">
                <option value="" disabled @selected($selectedOwnerId === null || $selectedOwnerId === '')>— Select Owner —</option>
                @foreach ($owners ?? [] as $id => $name)
                  <option value="{{ $id }}" @selected((string) $selectedOwnerId === (string) $id)>{{ $name }}
                  </option>
                @endforeach
              </x-form.select>
              <x-form.error :messages="$errors->get('owner_id')" />
            </div>
          @endhasanyrole

          {{-- Name --}}
          <div class="md:col-span-2">
            <x-form.label value="Name" />
            <x-form.input name="name" class="w-full" required :value="old('name')"
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
              :value="old('address')"
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
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-gray-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 9v3m0 4h.01M12 3a9 9 0 100 18 9 9 0 000-18z" />
                </svg>
                <span>
                  If you provide an address and leave Latitude/Longitude empty,
                  we’ll auto-fill them for you.
                </span>
              </p>

              <template x-if="isGeocoding">
                <p class="text-xs text-indigo-600 flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 animate-spin" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3a9 9 0 019 9m-9 9a9 9 0 01-9-9" />
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

          {{-- Timezone --}}
          <div class="md:col-span-2">
            <x-form.label value="Property Timezone" />
            @php
              $currentTz = old('timezone', '');
            @endphp
            <x-form.select name="timezone" class="w-full" required x-ref="timezoneSelect">
              <option value="" disabled @selected($currentTz === '')>Select Timezone</option>
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
            <h4 class="text-xs font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider mb-4 flex items-center gap-2">
              <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
              </svg>
              Calendar Sync (Airbnb / Vrbo)
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-blue-50/50 dark:bg-blue-900/10 rounded-xl p-4 border border-blue-100 dark:border-blue-900/30">
              {{-- Airbnb iCal --}}
              <div>
                <x-form.label value="Airbnb iCal URL" />
                <x-form.input name="airbnb_ical_url" class="w-full bg-white dark:bg-gray-800" :value="old('airbnb_ical_url')"
                  placeholder="https://www.airbnb.com/calendar/ical/..." />
              </div>

              {{-- Vrbo iCal --}}
              <div>
                <x-form.label value="Vrbo iCal URL" />
                <x-form.input name="vrbo_ical_url" class="w-full bg-white dark:bg-gray-800" :value="old('vrbo_ical_url')"
                  placeholder="https://www.vrbo.com/icalendar/..." />
              </div>

              <div class="md:col-span-2">
                <p class="mt-1 text-xs text-gray-500 leading-relaxed">
                  Paste your property's iCal links to automatically sync bookings. This ensures rooms and sessions are aligned with your actual guest arrivals.
                </p>
              </div>
            </div>
          </div>

          {{-- Latitude --}}
          <div>
            <x-form.label value="Latitude (optional)" />
            <x-form.input name="latitude" class="w-full" x-model="latitude" :value="old('latitude')" inputmode="decimal"
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
            <x-form.input name="longitude" class="w-full" x-model="longitude" :value="old('longitude')" inputmode="decimal"
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
        </div>
      </div>

      <div class="mt-6 sm:mt-8 flex flex-col sm:flex-row gap-2 sm:justify-end max-w-full">
        {{-- Plain save --}}
        <x-button type="submit" name="attach" value="none" x-bind:disabled="isGeocoding"
          x-bind:class="isGeocoding ? 'opacity-60 cursor-not-allowed' : ''" class="w-full sm:w-auto whitespace-nowrap">
          <span x-show="!isGeocoding">Save</span>
          <span x-show="isGeocoding">Fetching coordinates…</span>
        </x-button>

        {{-- Save + default rooms --}}
        <x-button type="submit" name="attach" value="rooms"
          class="bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500 w-full sm:w-auto whitespace-nowrap"
          x-bind:disabled="isGeocoding" x-bind:class="isGeocoding ? 'opacity-60 cursor-not-allowed' : ''">
          <span class="hidden sm:inline">Save + Assign Default Rooms</span>
          <span class="sm:hidden">Save + Default Rooms</span>
        </x-button>

        <x-button variant="secondary" href="{{ route('properties.index') }}"
          class="w-full sm:w-auto whitespace-nowrap">
          Cancel
        </x-button>
      </div>
    </form>
  </x-card>

  {{-- Google Places API --}}
  <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.places_api_key') }}&libraries=places"></script>

  {{-- Alpine helpers --}}
  <script>
    function propertyForm() {
      return {
        address: @json(old('address', '')),
        latitude: @json(old('latitude', '')),
        longitude: @json(old('longitude', '')),
        previewUrl: null,
        dragOver: false,
        hasFileSelected: false,

        // UX state
        isGeocoding: false,
        geocodeError: '',
        isSubmitting: false,
        geocodeTimeout: null,

        // Geocoding provider: forced to google for this feature
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
          const savedPreview = sessionStorage.getItem('property_photo_preview');
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
                  sessionStorage.setItem('property_photo_preview', e.target.result);
                };
                reader.readAsDataURL(file);
              }
            }
          });

          @if ($errors->isEmpty())
            setTimeout(() => {
              if (!this.hasFileSelected) {
                sessionStorage.removeItem('property_photo_preview');
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
            // Check if API loaded
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
              // 'geocode' helps find addresses; remove types for broader search if needed
              types: ['geocode', 'establishment']
            };

            this.autocompleteService.getPlacePredictions(request, (predictions, status) => {
              this.isLoadingSuggestions = false;

              if (status === google.maps.places.PlacesServiceStatus.OK && predictions) {
                this.suggestions = predictions;
                this.showSuggestions = true;
              } else {
                this.suggestions = [];
                // ZERO_RESULTS is common, don't scream error unless it's something else
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

          // STRICT: Handle Google Places suggestion only
          // suggestion.description is standard for Autocomplete predictions
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
            sessionStorage.setItem('property_photo_preview', e.target.result);
          };
          reader.readAsDataURL(file);
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

        // Clean up redundant fetchGeocode method as it is replaced by unified geocodeIfNeeded
        async fetchGeocode() {
             await this.geocodeIfNeeded(true);
        },
      }
    }
  </script>
</x-app-layout>
