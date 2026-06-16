<x-perfect-scrollbar as="nav" aria-label="main" class="flex flex-col flex-1 gap-4 px-3">

    {{-- Primary --}}
    <x-sidebar.link title="Command Center"
        href="{{ route('dashboard') }}"
        :isActive="request()->routeIs('dashboard') || request()->routeIs('welcome')">
        <x-slot name="icon"><x-icons.dashboard class="w-6 h-6" aria-hidden="true" /></x-slot>
    </x-sidebar.link>

    {{-- Scheduling (All authenticated users) --}}
    @auth
        <x-sidebar.dropdown title="Scheduling"
            :active="request()->routeIs('manage.sessions.*') || request()->routeIs('sessions.*') || request()->routeIs('calendar.*')">
            <x-slot name="icon"><x-icons.assignment class="w-6 h-6" /></x-slot>

            <x-sidebar.sublink title="Hub"
                href="{{ route('calendar.index') }}"
                :active="request()->routeIs('calendar.index')" />

            <x-sidebar.sublink title="Jobs"
                href="{{ route('manage.sessions.index') }}"
                :active="request()->routeIs('manage.sessions.index')" />

            <x-sidebar.sublink title="New Job"
                href="{{ route('manage.sessions.create') }}"
                :active="request()->routeIs('manage.sessions.create')" />

            {{-- Jump to HK list view --}}
            <x-sidebar.sublink title="My Jobs"
                href="{{ route('sessions.index') }}"
                :active="request()->routeIs('sessions.*')" />
        </x-sidebar.dropdown>
    @endauth

    {{-- Standalone "My Jobs" for housekeepers without admin/owner role --}}
    @if(auth()->user()->hasRole('housekeeper') && !auth()->user()->hasAnyRole(['admin', 'owner']))
        <x-sidebar.link title="My Jobs"
            href="{{ route('sessions.index') }}"
            :isActive="request()->routeIs('sessions.*')">
            <x-slot name="icon"><x-icons.assignment class="w-6 h-6" aria-hidden="true" /></x-slot>
        </x-sidebar.link>
    @endif

    {{-- Properties --}}
    @role('admin|owner|company')
        <x-sidebar.dropdown title="Properties"
            :active="request()->routeIs('properties.*')">
            <x-slot name="icon"><x-heroicon-o-home class="w-6 h-6" aria-hidden="true" /></x-slot>

            <x-sidebar.sublink title="All Properties"
                href="{{ route('properties.index') }}"
                :active="request()->routeIs('properties.index')" />

            @role('admin|owner|company')
                <x-sidebar.sublink title="Add New"
                    href="{{ route('properties.create') }}"
                    :active="request()->routeIs('properties.create')" />
            @endrole
        </x-sidebar.dropdown>
    @endrole

    {{-- Resources --}}
    @role('admin|owner|company')
        <x-sidebar.dropdown title="Resources"
            :active="request()->routeIs('videos.*')">
            <x-slot name="icon"><x-heroicon-o-video-camera class="w-6 h-6" aria-hidden="true" /></x-slot>

            <x-sidebar.sublink title="Instructional Videos"
                href="{{ route('videos.index') }}"
                :active="request()->routeIs('videos.index')" />

            <x-sidebar.sublink title="Upload Video"
                href="{{ route('videos.create') }}"
                :active="request()->routeIs('videos.create')" />
        </x-sidebar.dropdown>
    @endrole

    {{-- Resources for housekeepers --}}
    @if(auth()->user()->hasRole('housekeeper') && !auth()->user()->hasAnyRole(['admin', 'owner', 'company']))
        <x-sidebar.link title="Videos"
            href="{{ route('videos.index') }}"
            :isActive="request()->routeIs('videos.*')">
            <x-slot name="icon"><x-heroicon-o-video-camera class="w-6 h-6" aria-hidden="true" /></x-slot>
        </x-sidebar.link>
    @endif

    {{-- Users (Admin/Owner/Company) --}}
    @role('owner|admin|company')
        <x-sidebar.dropdown title="Users"
            :active="request()->routeIs('users.*')">
            <x-slot name="icon"><x-heroicon-o-users class="w-6 h-6" aria-hidden="true" /></x-slot>

            <x-sidebar.sublink title="Create User"
                href="{{ route('users.create') }}"
                :active="request()->routeIs('users.create')" />

            {{-- All (no role filter) --}}
            <x-sidebar.sublink title="All Users"
                href="{{ route('users.index') }}"
                :active="request()->routeIs('users.index') && !request()->filled('role')" />

            @role('admin')
                <x-sidebar.sublink title="Admins"
                    href="{{ route('users.index', ['role' => 'admin']) }}"
                    :active="request()->routeIs('users.index') && request('role') === 'admin'" />
            @endrole

            <x-sidebar.sublink title="Owners"
                href="{{ route('users.index', ['role' => 'owner']) }}"
                :active="request()->routeIs('users.index') && request('role') === 'owner'" />

            @role('admin')
                <x-sidebar.sublink title="Companies"
                    href="{{ route('users.index', ['role' => 'company']) }}"
                    :active="request()->routeIs('users.index') && request('role') === 'company'" />
            @endrole

            <x-sidebar.sublink title="Housekeepers"
                href="{{ route('users.index', ['role' => 'housekeeper']) }}"
                :active="request()->routeIs('users.index') && request('role') === 'housekeeper'" />
        </x-sidebar.dropdown>
    @endrole

    {{-- System / Audit --}}
    @role('admin')
        <x-sidebar.link title="Settings"
            href="{{ route('settings.index') }}"
            :isActive="request()->routeIs('settings.*')">
            <x-slot name="icon">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </x-slot>
        </x-sidebar.link>

{{--
        <x-sidebar.link title="Activity Log"
            href="{{ route('activity.index') }}"
            :isActive="request()->routeIs('activity.*')">
            <x-slot name="icon"><x-icons.activity class="w-6 h-6" aria-hidden="true" /></x-slot>
        </x-sidebar.link>
--}}
    @endrole

</x-perfect-scrollbar>
