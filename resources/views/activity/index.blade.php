{{-- resources/views/activity/index.blade.php --}}
@php use Illuminate\Support\Str; @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg sm:text-xl font-semibold">Activity Log</h2>
    </x-slot>

    <x-card class="mb-4">
        <form method="get" action="{{ route('activity.index') }}" class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-end gap-3 px-1 sm:px-0">

            {{-- Grows to fill remaining space; allows overflow text without breaking layout --}}
            <div class="flex-1 min-w-0">
                <x-form.label value="Search" />
                <x-form.input name="q" :value="request('q')" placeholder="Text in description/properties..." class="w-full max-w-full" />
            </div>

            {{-- Compact fields: keep intrinsic width, don't shrink; wrap when not enough room --}}
            <div class="w-full sm:w-auto sm:shrink-0">
                <x-form.label value="Event" />
                <x-form.select name="event"
                    class="w-full sm:w-auto !py-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                    <option value="">All</option>
                    @foreach ($distinctEvents as $ev)
                        <option value="{{ $ev }}" @selected(request('event') === $ev)>{{ $ev }}</option>
                    @endforeach
                </x-form.select>
            </div>

            <div class="w-full sm:w-auto sm:shrink-0">
                <x-form.label value="Causer" />
                <x-form.select name="causer_id"
                    class="w-full sm:w-auto !py-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                    <option value="">Anyone</option>
                    @foreach ($causers as $u)
                        <option value="{{ $u->id }}" @selected((string) request('causer_id') === (string) $u->id)>{{ $u->name }}</option>
                    @endforeach
                </x-form.select>
            </div>

            {{-- Also allowed to grow; shares leftover space with Search --}}
            <div class="flex-1 min-w-0">
                <x-form.label value="Subject Type" />
                <x-form.input name="subject_type" :value="request('subject_type')" placeholder="e.g. Room or FQCN" class="w-full max-w-full" />
            </div>

            <div class="w-full sm:w-auto sm:shrink-0">
                <x-form.label value="Subject ID" />
                <x-form.input name="subject_id" :value="request('subject_id')" class="w-full sm:w-auto max-w-full" />
            </div>

            <div class="w-full sm:w-auto sm:shrink-0">
                <x-form.label value="From" />
                <x-form.input type="date" name="from" :value="request('from')" class="w-full sm:w-auto max-w-full" />
            </div>

            <div class="w-full sm:w-auto sm:shrink-0">
                <x-form.label value="To" />
                <x-form.input type="date" name="to" :value="request('to')" class="w-full sm:w-auto max-w-full" />
            </div>

            {{-- Actions: compact, won't shrink into unreadable sizes; wrap to next line if needed --}}
            <div class="w-full sm:w-auto sm:shrink-0 flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                <x-button type="submit" class="w-full sm:w-auto whitespace-nowrap">Filter</x-button>
                @if (request()->query())
                    <a href="{{ route('activity.index') }}" class="text-xs sm:text-sm underline text-gray-600 dark:text-gray-300 text-center sm:text-left">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </x-card>




    {{-- Results --}}
    <x-card class="!px-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="uppercase text-xs tracking-wide">
                    <tr class="text-gray-600 dark:text-gray-300">
                        <th class="px-4 py-2 text-left">When</th>
                        <th class="px-4 py-2 text-left">Event</th>
                        <th class="px-4 py-2 text-left">Description</th>
                        <th class="px-4 py-2 text-left">Subject</th>
                        <th class="px-4 py-2 text-left">Causer</th>
                        <th class="px-4 py-2 text-left">Changes</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    @forelse($activities as $a)
                        @php
                            $props = $a->properties ?? collect();
                            // Spatie helpers: $a->changes() returns a collection with 'attributes' and 'old'
                            $changes = method_exists($a, 'changes') ? $a->changes() : collect();
                            $newAttrs = $changes->get('attributes') ?? $props->get('attributes');
                            $oldAttrs = $changes->get('old') ?? $props->get('old');

                            $subjectLabel =
                                class_basename($a->subject_type ?? '') . ($a->subject_id ? " #{$a->subject_id}" : '');
                            $causerLabel = $a->causer?->name ?? '—';
                        @endphp
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $a->created_at->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="px-2 py-0.5 rounded text-xs
                                    {{ $a->event === 'created'
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-400/20 dark:text-emerald-300'
                                        : ($a->event === 'updated'
                                            ? 'bg-amber-100 text-amber-800 dark:bg-amber-400/20 dark:text-amber-300'
                                            : ($a->event === 'deleted'
                                                ? 'bg-rose-100 text-rose-800 dark:bg-rose-400/20 dark:text-rose-300'
                                                : 'bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-300')) }}">
                                    {{ $a->event ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                {{ Str::limit($a->description ?? '—', 120) }}
                            </td>
                            <td class="px-4 py-3">
                                {{ $subjectLabel }}
                                @if ($a->subject)
                                    {{-- Optional: link to known routes if exist --}}
                                    @if ($a->subject_type === \App\Models\CleaningSession::class)
                                        <a class="ml-1 text-indigo-600 dark:text-indigo-400 underline"
                                            href="{{ route('sessions.show', $a->subject_id) }}">view</a>
                                    @endif
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                {{ $causerLabel }}
                            </td>
                            <td class="px-4 py-3 align-top">
                                @if ($newAttrs || $oldAttrs)
                                    <details class="group open:!bg-transparent">
                                        <summary
                                            class="cursor-pointer text-xs text-gray-600 dark:text-gray-300 hover:underline">
                                            View diff
                                        </summary>
                                        <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div class="rounded border dark:border-gray-800 p-2">
                                                <div class="text-[11px] font-semibold mb-1">New</div>
                                                <pre class="text-[11px] whitespace-pre-wrap">{{ json_encode($newAttrs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            </div>
                                            <div class="rounded border dark:border-gray-800 p-2">
                                                <div class="text-[11px] font-semibold mb-1">Old</div>
                                                <pre class="text-[11px] whitespace-pre-wrap">{{ json_encode($oldAttrs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            </div>
                                        </div>
                                    </details>
                                @else
                                    <span class="text-xs text-gray-500 dark:text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-10 text-center text-gray-500 dark:text-gray-400" colspan="6">
                                No activity found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3">
            {{ $activities->links() }}
        </div>
    </x-card>
</x-app-layout>
