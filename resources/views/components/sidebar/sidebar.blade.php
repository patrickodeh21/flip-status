<x-sidebar.overlay />

<aside class="sidebar-base fixed inset-y-0 z-20 flex flex-col space-y-6 bg-white shadow-lg dark:bg-dark-eval-1"
  :class="{
      'translate-x-0 w-64': isSidebarOpen === true || isSidebarHovered === true,
      '-translate-x-full w-64 md:w-16 md:translate-x-0': isSidebarOpen === false && !isSidebarHovered,
      'sidebar-base': isSidebarOpen === undefined
  }"
  style="transition-property: width, transform; transition-duration: 150ms; max-width: 16rem;"
  x-on:mouseenter="clearTimeout($data.hoverTimeout); handleSidebarHover(true)"
  x-on:mouseleave="$data.hoverTimeout = setTimeout(() => handleSidebarHover(false), 200)">
  <x-sidebar.header />

  <x-sidebar.content />

  <x-sidebar.footer />
</aside>
