<x-app-layout>
<div x-data="{
    drawerHtml: '',
    isLoading: false,
    openDrawer(taskId) {
        if (window.innerWidth < 768) {
            // Mobile redirect to full page
            window.location.href = '{{ url('tasks') }}/' + taskId + '/edit';
            return;
        }
        
        // Find the hidden preloaded form for this task
        const template = document.getElementById('task-edit-template-' + taskId);
        
        if (template) {
            this.drawerHtml = template.innerHTML;
            this.isLoading = false;
        } else {
            this.drawerHtml = '<div class=\'text-red-500 p-4\'>Failed to load form.</div>';
        }
        
        // Dispatch event to open the preview panel immediately
        this.$dispatch('open-preview-panel', 'edit-task-panel');
    }
}" @close-preview-panel.window="if ($event.detail === 'edit-task-panel') setTimeout(() => { drawerHtml = '' }, 300)">
    <x-slot name="header">
        <h2 class="text-lg sm:text-xl font-semibold flex items-center gap-2">
            Tasks
        </h2>
    </x-slot>

    {{-- Preload all edit templates for instant drawer opening --}}
    <div style="display: none;" aria-hidden="true">
        @foreach($tasks as $t)
            <div id="task-edit-template-{{ $t->id }}">
                @include('tasks.edit-partial', ['task' => $t])
            </div>
        @endforeach
    </div>

    {{-- Toolbar: search + type filter + create --}}
    <div class="mb-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-3 px-1 sm:px-0">
        <form method="get" action="{{ route('tasks.index') }}" class="flex-1 flex flex-col sm:flex-row items-stretch sm:items-center gap-2 min-w-0">
            <x-form.input name="q" placeholder="Search tasks…" value="{{ request('q') }}" class="w-full sm:flex-1 min-w-0 max-w-full" />

            <x-form.select name="type" class="w-full sm:w-auto sm:min-w-[140px] sm:max-w-[200px] !py-1">
                <option value="">All types</option>
                <option value="room" @selected(request('type') === 'room')>Room</option>
                <option value="inventory" @selected(request('type') === 'inventory')>Inventory</option>
                <option value="verify" @selected(request('type') === 'verify')>Verify</option>
            </x-form.select>

            <x-button type="submit" class="w-full sm:w-auto whitespace-nowrap">Filter</x-button>

            @if (request()->hasAny(['q', 'type', 'room_id']))
                <a href="{{ route('tasks.index') }}"
                    class="text-xs sm:text-sm underline text-gray-600 dark:text-gray-300 text-center sm:text-left">Reset</a>
            @endif
        </form>

        <x-button variant="primary" href="{{ route('tasks.create') }}" class="w-full sm:w-auto whitespace-nowrap">
            + New Task
        </x-button>
    </div>

    <x-card class="!px-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="uppercase text-xs tracking-wide">
                    <tr class="text-gray-600 dark:text-gray-300">
                        <th class="px-4 py-1 text-left">#</th>
                        <th class="px-4 py-1 text-left">Name</th>
                        <th class="px-4 py-1 text-center">Type</th>
                        @role('admin')
                        <th class="px-4 py-1 text-center">Default?</th>
                        @endrole
                        <th class="px-4 py-1 text-center">Created</th>
                        <th class="px-4 py-1 w-40 text-right">Action</th>
                    </tr>
                </thead>

                <tbody class="divide-y dark:divide-gray-700">
                    @forelse($tasks as $t)
                        <tr>
                            <td class="px-4 py-1 text-left">{{ ($tasks->firstItem() ?? 0) + $loop->index }}</td>
                            <td class="px-4 py-1 font-medium text-gray-900 dark:text-gray-100">
                                {{ $t->name }}
                            </td>
                            <td class="px-4 py-1 text-center">
                                <span
                                    class="px-2 py-0.5 rounded text-xs
                                    {{ ([
                                        'inventory' => 'bg-blue-100 text-blue-800 dark:bg-blue-400/20 dark:text-blue-300',
                                        'verify' => 'bg-amber-100 text-amber-800 dark:bg-amber-400/20 dark:text-amber-300',
                                        'instructions' => 'bg-slate-100 text-slate-800 dark:bg-slate-400/20 dark:text-slate-300',
                                    ][$t->type] ?? 'bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-300') }}">
                                    {{ ucfirst($t->type) }}
                                </span>
                            </td>
                            @role('admin')
                            <td class="px-4 py-1 text-center">
                                <span
                                    class="px-2 py-0.5 rounded text-xs
                                    {{ $t->is_default
                                        ? 'bg-green-100 text-green-800 dark:bg-green-400/20 dark:text-green-300'
                                        : 'bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-300' }}">
                                    {{ $t->is_default ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            @endrole
                            <td class="px-4 py-1 text-center">
                                {{ $t->created_at?->format('Y-m-d') }}
                            </td>
                            <td class="px-4 py-1 text-right whitespace-nowrap">
                                <x-action-dropdown align="right" width="w-48" label="Task actions">
                                    <x-dropdown.item href="{{ route('tasks.edit', $t) }}" @click.prevent="openDrawer('{{ $t->id }}')">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M3 17.25V21h3.75l11-11-3.75-3.75-11 11zM20.71 7.04a1.003 1.003 0 0 0 0-1.42L18.37 3.29a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.83z" />
                                        </svg>
                                        <span>Edit</span>
                                    </x-dropdown.item>

                                    <x-dropdown.item as="form" method="POST" href="{{ route('tasks.destroy', $t) }}"
                                        onclick="return confirm('Delete this task?')">
                                        @csrf
                                        @method('DELETE')
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M9 3a1 1 0 0 0-1 1v1H4a1 1 0 1 0 0 2h1v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7h1a1 1 0 1 0 0-2h-4V4a1 1 0 0 0-1-1H9zm2 4a1 1 0 1 0-2 0v10a1 1 0 1 0 2 0V7zm4 0a1 1 0 1 0-2 0v10a1 1 0 1 0 2 0V7z" />
                                        </svg>
                                        <span>Delete</span>
                                    </x-dropdown.item>
                                </x-action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-10 text-center text-gray-500 dark:text-gray-400" @if(auth()->user()->hasRole('admin')) colspan="6" @else colspan="5" @endif>
                                No tasks found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (method_exists($tasks, 'links'))
            <div class="px-4 py-3">
                {{ $tasks->links() }}
            </div>
        @endif
    </x-card>

    {{-- Slide-out Drawer (Desktop Only) --}}
    <x-preview-panel
        name="edit-task-panel"
        :overlay="true"
        side="right"
        initialWidth="32rem"
        minWidth="24rem"
        title="Edit Task"
        subtitle="Manage task details">

        <div x-show="isLoading" class="flex flex-col items-center justify-center p-12 text-gray-500">
             <svg class="animate-spin h-8 w-8 mb-4 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
             </svg>
             Loading task details...
        </div>
        <div x-show="!isLoading" x-html="drawerHtml"></div>
    </x-preview-panel>
</div>
</x-app-layout>
