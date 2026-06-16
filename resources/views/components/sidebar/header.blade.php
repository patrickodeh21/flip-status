@php
  use App\Models\Setting;
  $logoAlignment = Setting::get('logo_alignment', 'center');
  $justifyClass = match ($logoAlignment) {
      'left' => 'justify-start',
      'right' => 'justify-end',
      default => 'justify-center',
  };
@endphp

<div class="flex items-center {{ $justifyClass }} flex-shrink-0 px-3 py-1 my-1">
  <!-- Logo -->
  <a href="{{ route('dashboard') }}" class="flex justify-center items-center gap-2">
    <template x-if="isSidebarOpen || isSidebarHovered">
        <x-application-logo aria-hidden="true" class="w-full max-w-[12rem] h-12 object-contain hidden md:block" />
    </template>
    
    <template x-if="!isSidebarOpen && !isSidebarHovered">
        <x-application-logo :icon-only="true" aria-hidden="true" class="w-8 h-8 object-cover object-left hidden md:block" style="width: 32px; height: 32px;" />
    </template>

    <span class="sr-only">Dashboard</span>
  </a>
</div>
