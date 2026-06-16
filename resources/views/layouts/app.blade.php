<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="base-path" content="{{ request()->getBaseUrl() }}">

    <title>{{ $siteName ?? config('app.name', 'HK Checklist') }}</title>

    <!-- Favicon -->
    @php
        $faviconPath = \App\Models\Setting::get('favicon_path');
        if ($faviconPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($faviconPath)) {
            $faviconUrl = url('file/' . ltrim($faviconPath, '/'));
            $faviconExt = strtolower(pathinfo($faviconPath, PATHINFO_EXTENSION));
            $faviconType = match($faviconExt) {
                'ico' => 'image/x-icon',
                'png' => 'image/png',
                'svg' => 'image/svg+xml',
                'jpg', 'jpeg' => 'image/jpeg',
                default => 'image/x-icon',
            };
        }
    @endphp
    @if (isset($faviconUrl))
        <link rel="icon" type="{{ $faviconType }}" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" type="{{ $faviconType }}" href="{{ $faviconUrl }}">
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @endif

    <!-- Fonts -->
    <!-- External fonts removed for self-hosted compliance -->

    <!-- Styles -->
    <style>
        [x-cloak] {
            display: none !important;
        }

        /* Dynamic Theme Color */
        :root {
            --theme-primary: {!! \App\Models\Setting::get('theme_color', '#842eb8') !!};
            --button-primary-color: {!! \App\Models\Setting::get('button_primary_color') ?: \App\Models\Setting::get('theme_color', '#842eb8') !!};
        }
    </style>

    <!-- Scripts -->
    <script>
        (function() {
            // Theme setup
            const dark = localStorage.getItem('dark');
            if (dark === 'true' || (dark === null && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            
            // Sidebar anti-flicker setup
            // On page load, if window is > 1024px, we want the sidebar open.
            // If it's <= 1024px, we want it closed. We apply a class to the HTML tag
            // so our initial DOM state matches the Alpine state *before* Alpine mounts.
            if (window.innerWidth > 1024) {
                document.documentElement.classList.add('sidebar-open');
                document.documentElement.classList.remove('sidebar-closed');
            } else {
                document.documentElement.classList.add('sidebar-closed');
                document.documentElement.classList.remove('sidebar-open');
            }
        })();
    </script>
    <style>
        /* Anti-flicker styles applied before Alpine starts */
        html.sidebar-open .page-wrapper-base { margin-left: 16rem; }
        html.sidebar-closed .page-wrapper-base { margin-left: 4rem; } 
        @media (max-width: 768px) { html.sidebar-closed .page-wrapper-base { margin-left: 0; } }

        html.sidebar-open .sidebar-base { transform: translateX(0); width: 16rem; }
        html.sidebar-closed .sidebar-base { transform: translateX(-100%); width: 16rem; }
        @media (min-width: 768px) { html.sidebar-closed .sidebar-base { transform: translateX(0); width: 4rem; } }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Preload GTranslate so it downloads early -->
    <link rel="preload" href="https://cdn.gtranslate.net/widgets/latest/dropdown.js" as="script">

    <!-- Anti-flicker: hide page while GTranslate translates -->
    <script>
    (function(){
        var c = document.cookie;
        if (c && c.indexOf('googtrans=') !== -1 && c.indexOf('googtrans=/en/en') === -1) {
            document.documentElement.style.opacity = '0';
            document.documentElement.style.transition = 'opacity 0.2s ease';
            var revealed = false;
            function reveal() {
                if (revealed) return;
                revealed = true;
                document.documentElement.style.opacity = '1';
            }
            // Reveal when Google Translate class appears on html element
            var mo = new MutationObserver(function(mutations) {
                for (var i = 0; i < mutations.length; i++) {
                    if (document.documentElement.classList.contains('translated-ltr') ||
                        document.documentElement.classList.contains('translated-rtl')) {
                        mo.disconnect();
                        reveal();
                        return;
                    }
                }
            });
            mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
            // Safety timeout — never hide longer than 1.5s
            setTimeout(reveal, 1500);
        }
    })();
    </script>

    <style>
        .gtranslate_wrapper select {
            width: 100% !important;
            padding: 0.375rem 0.5rem !important;
            font-size: 0.875rem !important;
            border-radius: 0.375rem !important;
            border: 1px solid #d1d5db !important;
            background-color: #ffffff !important;
            color: #1f2937 !important;
            outline: none !important;
            box-shadow: none !important;
        }
        html.dark .gtranslate_wrapper select {
            background-color: #1f2937 !important;
            border-color: #4b5563 !important;
            color: #e5e7eb !important;
        }
        html.dark .gtranslate_wrapper option {
            background-color: #1f2937 !important;
            color: #e5e7eb !important;
        }
    </style>
</head>

<body class="font-sans antialiased" x-data="mainState" x-on:resize.window="handleWindowResize">
    <div class="min-h-screen text-gray-900 bg-gray-100 dark:bg-dark-eval-0 dark:text-gray-200">
            <!-- Sidebar -->
            <x-sidebar.sidebar />

            <!-- Page Wrapper -->
            <div class="page-wrapper-base flex flex-col min-h-screen"
                :class="{
                    'lg:ml-64': isSidebarOpen === true,
                    'md:ml-16': isSidebarOpen === false,
                    'page-wrapper-base': isSidebarOpen === undefined
                }"
                style="transition-property: margin; transition-duration: 150ms;">

                <!-- Navbar -->
                <x-navbar />

                <!-- Page Heading -->
                <header class="min-w-0">
                    <div class="p-4 sm:p-6 min-w-0">
                        {{ $header }}
                    </div>
                </header>

                <!-- Page Content -->
                <main class="px-4 sm:px-6 flex-1 min-w-0 pb-20 md:pb-6">
                    <x-flash.ok :timeout="6000" />
                    <x-flash.error :timeout="6000" />
                    {{ $slot }}
                </main>

                <!-- Toast Notification Container -->
                <div
                    x-data="{
                        toasts: [],
                        addToast(type, message) {
                            const id = Date.now()
                            this.toasts.push({ id, type, message })
                            setTimeout(() => this.removeToast(id), 5000)
                        },
                        removeToast(id) {
                            this.toasts = this.toasts.filter(t => t.id !== id)
                        }
                    }"
                    x-on:toast.window="addToast($event.detail.type, $event.detail.message)"
                    class="fixed bottom-6 right-6 z-50 space-y-3"
                    style="max-width: 400px;"
                >
                    <template x-for="toast in toasts" :key="toast.id">
                        <div
                            x-show="true"
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 translate-y-2"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 translate-y-2"
                            :class="{
                                'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-800 dark:text-red-300': toast.type === 'error',
                                'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-800 dark:text-green-300': toast.type === 'success',
                                'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-900/30 dark:border-blue-800 dark:text-blue-300': toast.type === 'info',
                                'bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/30 dark:border-amber-800 dark:text-amber-300': toast.type === 'warning'
                            }"
                            class="rounded-lg border shadow-lg px-4 py-3 flex items-start gap-3"
                            role="alert"
                        >
                            <svg
                                x-show="toast.type === 'error'"
                                class="h-5 w-5 mt-0.5 flex-none"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 6a1 1 0 112 0v5a1 1 0 11-2 0V6zm1 9a1.25 1.25 0 100-2.5A1.25 1.25 0 0010 15z" clip-rule="evenodd" />
                            </svg>
                            <svg
                                x-show="toast.type === 'success'"
                                class="h-5 w-5 mt-0.5 flex-none"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 10-1.214-.882l-3.22 4.43L7.4 10.4a.75.75 0 10-1.06 1.06l2.5 2.5a.75.75 0 001.14-.09l3.877-5.68z" clip-rule="evenodd" />
                            </svg>
                            <svg
                                x-show="toast.type === 'info'"
                                class="h-5 w-5 mt-0.5 flex-none"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                            <svg
                                x-show="toast.type === 'warning'"
                                class="h-5 w-5 mt-0.5 flex-none"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            <div class="flex-1 text-sm font-medium" x-text="toast.message"></div>
                            <button
                                type="button"
                                @click="removeToast(toast.id)"
                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                aria-label="Dismiss"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 8.586L4.293 2.879A1 1 0 102.879 4.293L8.586 10l-5.707 5.707a1 1 0 001.414 1.414L10 11.414l5.707 5.707a1 1 0 001.414-1.414L11.414 10l5.707-5.707A1 1 0 0015.707 2.88L10 8.586z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Page Footer -->
                <x-footer />
            </div>

            <!-- Global Modal Portal — lives OUTSIDE the margin-animated Page Wrapper so
                 position:fixed children are always relative to the viewport, never to a
                 CSS-composited layer created by the Page Wrapper's margin transition. -->
            <div x-data="globalModal()" x-cloak>

                <!-- Note Modal -->
                <div x-show="show && type === 'note'"
                     class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                     @click.self="close()"
                     @keydown.escape.window="close()">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md p-6">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Add Note</h3>

                        <!-- NEW: Preview existing data -->
                        <template x-if="(existingPhotos && existingPhotos.length > 0) || existingNote">
                            <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-100 dark:border-gray-700">
                                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Previously Saved</div>
                                <template x-if="existingNote">
                                    <p class="text-sm text-gray-700 dark:text-gray-300 italic mb-3" x-text="'&quot;' + existingNote + '&quot;'"></p>
                                </template>
                                <template x-if="existingPhotos && existingPhotos.length > 0">
                                    <div class="flex gap-2 overflow-x-auto pb-1">
                                        <template x-for="photo in existingPhotos" :key="photo.id || photo.url">
                                            <div class="w-16 h-16 flex-shrink-0 rounded shadow-sm overflow-hidden border border-gray-200 dark:border-gray-600">
                                                <img :src="photo.thumbnail || photo.url" class="w-full h-full object-cover" />
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <textarea x-model="noteValue"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  rows="4"
                                  placeholder="Enter your note here..."></textarea>
                        <div class="flex justify-end gap-3 mt-4">
                            <button type="button" @click="close()"
                                    class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                                Cancel
                            </button>
                            <button type="button" @click="saveNote()"
                                    :disabled="noteSaving"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                <span x-show="!noteSaving">Save Note</span>
                                <span x-show="noteSaving">Saving...</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Photo Upload Modal -->
                <div x-show="show && type === 'photo'"
                     class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                     @click.self="close()"
                     @keydown.escape.window="close()">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">
                            <span x-text="isPhotoRequired ? 'Upload Photo' : 'Add Note / Photo'"></span>
                        </h3>

                        <!-- NEW: Preview existing data -->
                        <template x-if="(existingPhotos && existingPhotos.length > 0) || existingNote">
                            <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-100 dark:border-gray-700">
                                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Previously Saved</div>
                                <template x-if="existingNote">
                                    <p class="text-sm text-gray-700 dark:text-gray-300 italic mb-3" x-text="'&quot;' + existingNote + '&quot;'"></p>
                                </template>
                                <template x-if="existingPhotos && existingPhotos.length > 0">
                                    <div class="flex gap-2 overflow-x-auto pb-1">
                                        <template x-for="photo in existingPhotos" :key="photo.id || photo.url">
                                            <div class="w-16 h-16 flex-shrink-0 rounded shadow-sm overflow-hidden border border-gray-200 dark:border-gray-600">
                                                <img :src="photo.thumbnail || photo.url" class="w-full h-full object-cover" />
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- File Input -->
                        <div class="mb-4">
                            <template x-if="!previewUrl">
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <svg class="w-8 h-8 mb-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"/>
                                        </svg>
                                        <p class="mb-2 text-sm text-gray-500 dark:text-gray-400"><span class="font-semibold">Click to upload</span></p>
                                    </div>
                                    <input type="file" class="hidden" accept="image/*" capture="environment"
                                           @change="handleFileChange($event)"
                                           id="global-task-photo-input" />
                                </label>
                            </template>
                            <template x-if="previewUrl">
                                <div class="relative">
                                    <img :src="previewUrl" class="w-full h-48 object-cover rounded-lg" />
                                    <button @click="clearPreview()"
                                            class="absolute top-2 right-2 bg-red-600 text-white rounded-full p-1 hover:bg-red-700">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                            </template>
                        </div>

                        <!-- Note Input -->
                        <div class="mb-4">
                            <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                Note <span x-show="previewUrl && !isPhotoRequired" class="text-red-500 text-xs">(required when photo is attached)</span>
                            </label>
                            <textarea x-model="photoNoteValue"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                      rows="2"
                                      placeholder="Describe this photo..."></textarea>
                        </div>

                        <div class="flex justify-end gap-3">
                            <button type="button" @click="close()"
                                    class="px-4 py-2 min-h-[44px] text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                                Cancel
                            </button>
                            <button type="button"
                                    :disabled="!canSubmitPhoto || photoUploading"
                                    @click="uploadPhoto()"
                                    class="px-4 py-2 min-h-[44px] bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span x-show="!photoUploading" x-text="isPhotoRequired ? 'Upload' : (previewUrl ? 'Upload' : 'Save Note')"></span>
                                <span x-show="photoUploading">Uploading...</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Inventory Modal (Qty + Photo) -->
                <div x-show="show && type === 'inventory'"
                     class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                     @click.self="close()"
                     @keydown.escape.window="close()">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4" x-text="'Inventory: ' + inventoryTaskName"></h3>

                        <!-- NEW: Preview existing data -->
                        <template x-if="(existingPhotos && existingPhotos.length > 0) || existingNote">
                            <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-100 dark:border-gray-700">
                                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Previously Saved</div>
                                <template x-if="existingNote">
                                    <p class="text-sm text-gray-700 dark:text-gray-300 italic mb-3" x-text="'&quot;' + existingNote + '&quot;'"></p>
                                </template>
                                <template x-if="existingPhotos && existingPhotos.length > 0">
                                    <div class="flex gap-2 overflow-x-auto pb-1">
                                        <template x-for="photo in existingPhotos" :key="photo.id || photo.url">
                                            <div class="w-16 h-16 flex-shrink-0 rounded shadow-sm overflow-hidden border border-gray-200 dark:border-gray-600">
                                                <img :src="photo.thumbnail || photo.url" class="w-full h-full object-cover" />
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Step 1: Quantity -->
                        <div x-show="inventoryStep === 1">
                            <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-100">Enter Quantity</label>
                            <input type="number" x-model="inventoryQuantity" min="0" step="1"
                                   @keydown.enter.prevent="inventoryNextStep()"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mb-2"
                                   placeholder="e.g. 5" />
                            <p x-show="inventoryError !== ''" x-text="inventoryError" class="text-sm text-red-600 dark:text-red-400 mb-4"></p>
                            
                            <div class="flex justify-end gap-3 mt-4">
                                <button type="button" @click="close()"
                                        class="px-4 py-2 min-h-[44px] text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                                    Cancel
                                </button>
                                <button type="button" @click="inventoryNextStep()"
                                        class="px-4 py-2 min-h-[44px] bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Next: Take Photo
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Photo Upload -->
                        <div x-show="inventoryStep === 2" x-cloak>
                            <div class="mb-4">
                                <template x-if="!previewUrl">
                                    <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                            <svg class="w-8 h-8 mb-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"/>
                                            </svg>
                                            <p class="mb-2 text-sm text-gray-500 dark:text-gray-400"><span class="font-semibold">Click to capture/upload</span></p>
                                        </div>
                                        <input type="file" class="hidden" accept="image/*" capture="environment"
                                               @change="handleFileChange($event)" />
                                    </label>
                                </template>
                                <template x-if="previewUrl">
                                    <div class="relative mt-2">
                                        <img :src="previewUrl" class="w-full h-48 object-cover rounded-lg" />
                                        <button @click="clearPreview()"
                                                class="absolute top-2 right-2 bg-red-600 text-white rounded-full p-1 hover:bg-red-700">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                            
                            <div class="flex justify-between mt-4">
                                <button type="button" @click="inventoryStep = 1; clearPreview()"
                                        class="px-4 py-2 min-h-[44px] text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                                    &larr; Back
                                </button>
                                <button type="button"
                                        :disabled="!previewUrl || photoUploading"
                                        @click="inventoryUploadAndComplete()"
                                        class="px-4 py-2 min-h-[44px] bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span x-show="!photoUploading">Upload & Save</span>
                                    <span x-show="photoUploading">Saving...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gallery / Fullscreen Modal -->
                <div x-show="show && type === 'gallery'"
                     class="fixed inset-0 z-[200] bg-black/90 flex items-center justify-center p-4"
                     @click.self="close()"
                     @keydown.escape.window="close()">
                    <img :src="gallerySrc" class="max-h-[90vh] max-w-[90vw] rounded-lg shadow-2xl" alt="Gallery view" />
                    <button type="button" @click="close()"
                            class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300 transition-colors">×</button>
                </div>
            </div>
    </div>
    <!-- GTranslate: https://gtranslate.io/ -->
    <script>window.gtranslateSettings = {"default_language":"en","detect_browser_language":true,"languages":["en","es","pt"],"wrapper_selector":".gtranslate_wrapper","switcher_horizontal_position":"inline","alt_flags":{"en":"usa"}};</script>
    <script src="https://cdn.gtranslate.net/widgets/latest/dropdown.js"></script>
</body>

</html>
