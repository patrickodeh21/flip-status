
<nav
    aria-label="secondary"
    x-data="{ open: false }"
    class="sticky top-0 z-10 flex items-center justify-between px-4 py-4 sm:px-6 bg-white dark:bg-dark-eval-1">

    <div class="flex items-center gap-3">
        <x-button
            type="button"
            class="md:hidden"
            icon-only
            variant="secondary"
            sr-text="Toggle dark mode"
            x-on:click="toggleTheme"
        >
            <x-heroicon-o-moon
                x-show="!isDarkMode"
                aria-hidden="true"
                class="w-6 h-6"
            />

            <x-heroicon-o-sun
                x-show="isDarkMode"
                aria-hidden="true"
                class="w-6 h-6"
            />
        </x-button>
    </div>

    <div class="flex items-center gap-3">
        <!-- Desktop Sidebar Toggle -->
        <x-button
            type="button"
            class="hidden md:inline-flex"
            icon-only
            variant="secondary"
            sr-text="Toggle sidebar"
            x-on:click="toggleSidebar()"
        >
            <x-heroicon-o-menu
                x-show="!isSidebarOpen"
                aria-hidden="true"
                class="w-6 h-6"
            />
            <x-heroicon-o-menu-alt-2
                x-show="isSidebarOpen"
                aria-hidden="true"
                class="w-6 h-6"
            />
        </x-button>

        <!-- Desktop Dark Mode Toggle -->
        <x-button
            type="button"
            class="hidden md:inline-flex"
            icon-only
            variant="secondary"
            sr-text="Toggle dark mode"
            x-on:click="toggleTheme"
        >
            <x-heroicon-o-moon
                x-show="!isDarkMode"
                aria-hidden="true"
                class="w-6 h-6"
            />

            <x-heroicon-o-sun
                x-show="isDarkMode"
                aria-hidden="true"
                class="w-6 h-6"
            />
        </x-button>

        <x-dropdown align="right" width="48">
            <x-slot name="trigger">
                <button
                    data-focus-ring="theme-primary"
                    class="flex items-center p-2 text-sm font-medium text-gray-500 rounded-md transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none focus:ring focus:ring-offset-1 focus:ring-offset-white dark:focus:ring-offset-dark-eval-1 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    <div>{{ Auth::user()->name }}</div>

                    <div class="ml-1">
                        <svg
                            class="w-4 h-4 fill-current"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </div>
                </button>
            </x-slot>

            <x-slot name="content">
                {{-- Profile --}}
                <x-dropdown-link
                    :href="route('profile.edit')"
                >
                    {{ __('Profile') }}
                </x-dropdown-link>

                {{-- Language Selector --}}
                <div @click.stop class="px-4 py-2 border-t border-b border-gray-100 dark:border-gray-700">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Language</label>
                    <div class="gtranslate_wrapper"></div>
                </div>

                {{-- Authentication --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-dropdown-link
                        :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();"
                    >
                        {{ __('Log Out') }}
                    </x-dropdown-link>
                </form>
            </x-slot>
        </x-dropdown>
    </div>
</nav>

<!-- Mobile bottom bar -->
<div
    class="fixed inset-x-0 bottom-0 flex items-center justify-between px-4 py-4 sm:px-6 transition-transform duration-500 bg-white md:hidden dark:bg-dark-eval-1 z-50 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)]"
>
    <x-button
        type="button"
        icon-only
        variant="secondary"
        sr-text="Search"
    >
        <x-heroicon-o-search aria-hidden="true" class="w-6 h-6" />
    </x-button>

    <a href="{{ route('dashboard') }}" class="flex items-center justify-center">
        <x-application-logo aria-hidden="true" class="w-auto max-w-[10rem] h-12 object-contain" />

        <span class="sr-only">Dashboard</span>
    </a>

    <x-button
        type="button"
        icon-only
        variant="secondary"
        sr-text="Open main menu"
        x-on:click="toggleSidebar()"
    >
        <x-heroicon-o-menu
            x-show="!isSidebarOpen"
            aria-hidden="true"
            class="w-6 h-6"
        />

        <x-heroicon-o-x
            x-show="isSidebarOpen"
            aria-hidden="true"
            class="w-6 h-6"
        />
    </x-button>
</div>

<style>
    [data-focus-ring="theme-primary"]:focus {
        --tw-ring-color: var(--theme-primary) !important;
    }
</style>
