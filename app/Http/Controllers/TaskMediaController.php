<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskMediaController extends Controller
{
    public function store(Request $request, Task $task)
    {
        // Validate the array and each file
        $request->validate([
            'media'      => ['required', 'array', 'min:1'],
            'media.*'    => ['file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/x-msvideo', 'max:20480'],
            'captions'   => ['nullable', 'array'],
            'captions.*' => ['nullable', 'string', 'max:255'],
        ]);

        // Collect files and remove duplicates within THIS request.
        // We use a stable key: name + size + content hash (md5 of tmp path).
        $files = collect($request->file('media', []))
            ->filter() // keep actual files
            ->unique(function (\Illuminate\Http\UploadedFile $f) {
                // hashing tmp file is cheap for typical sizes; robust for duplicates
                return implode('|', [
                    $f->getClientOriginalName(),
                    $f->getSize(),
                    md5_file($f->getRealPath()),
                ]);
            })
            ->values();

        // Determine next sort index
        $start = (int) ($task->media()->max('sort_order') ?? 0);

        foreach ($files as $i => $file) {
            $path = $file->store('task-media', 'public');

            $mime = $file->getMimeType() ?? '';
            $type = str_starts_with($mime, 'video') ? 'video' : 'image';

            $task->media()->create([
                'type'       => $type,
                'url'        => $path,
                'thumbnail'  => $type === 'image' ? $path : null,
                'caption'    => $request->input("captions.$i"),
                'sort_order' => $start + $i + 1,
            ]);
        }

        return back()->with('status', 'Media uploaded');
    }

    public function destroy(Task $task, TaskMedia $media)
    {
        $relPath = str_replace(Storage::disk('public')->url(''), '', $media->url);
        Storage::disk('public')->delete($relPath);

        $media->delete();
        return back()->with('status', 'Media removed');
    }
}
