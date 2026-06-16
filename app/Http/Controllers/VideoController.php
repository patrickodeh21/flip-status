<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Video;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Housekeepers see only videos for their assigned properties
        if ($user->hasRole('housekeeper')) {
            $propertyIds = $user->properties->pluck('id')->toArray();

            // Also include properties from cleaning sessions
            $sessionPropertyIds = \App\Models\CleaningSession::where('housekeeper_id', $user->id)
                ->distinct()
                ->pluck('property_id')
                ->toArray();

            $propertyIds = array_unique(array_merge($propertyIds, $sessionPropertyIds));

            $videos = empty($propertyIds)
                ? collect()
                : Video::with('properties')
                    ->whereHas('properties', fn ($q) => $q->whereIn('properties.id', $propertyIds))
                    ->latest()
                    ->get();

            return view('resources.videos.index', ['videos' => $videos, 'isHousekeeper' => true]);
        }

        abort_unless($user->hasAnyRole(['admin', 'owner', 'company']), 403);

        $videos = Video::with('properties')->latest()->get();

        return view('resources.videos.index', ['videos' => $videos, 'isHousekeeper' => false]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'owner', 'company']), 403);

        $properties = Property::orderBy('name')->get();

        return view('resources.videos.create', ['properties' => $properties]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'owner', 'company']), 403);

        $data = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string', 'max:5000'],
            'video_file'    => ['nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm,video/x-msvideo', 'max:204800'],
            'video_url'     => ['nullable', 'url'],
            'properties'    => ['nullable', 'array'],
            'properties.*'  => ['exists:properties,id'],
        ]);

        if (empty($data['video_file']) && $request->hasFile('video_file') === false && empty($data['video_url'])) {
            return back()->withErrors(['video_url' => 'Please upload a video file or provide a video URL.'])->withInput();
        }

        $url = $data['video_url'] ?? null;
        if ($request->hasFile('video_file')) {
            $url = $request->file('video_file')->store('videos', 'public');
        }

        $video = Video::create([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'url'         => $url,
            'uploaded_by' => $request->user()->id,
        ]);

        if (!empty($data['properties'])) {
            $video->properties()->sync($data['properties']);
        }

        return redirect()->route('videos.index')->with('ok', 'Video uploaded successfully.');
    }

    public function edit(Request $request, Video $video)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'owner', 'company']), 403);

        $properties = Property::orderBy('name')->get();
        $assignedPropertyIds = $video->properties->pluck('id')->toArray();

        return view('resources.videos.edit', [
            'video' => $video,
            'properties' => $properties,
            'assignedPropertyIds' => $assignedPropertyIds,
        ]);
    }

    public function update(Request $request, Video $video)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'owner', 'company']), 403);

        $data = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string', 'max:5000'],
            'video_file'    => ['nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm,video/x-msvideo', 'max:204800'],
            'video_url'     => ['nullable', 'url'],
            'properties'    => ['nullable', 'array'],
            'properties.*'  => ['exists:properties,id'],
        ]);

        $url = $video->getRawOriginal('url');
        if ($request->hasFile('video_file')) {
            $url = $request->file('video_file')->store('videos', 'public');
        } elseif (!empty($data['video_url'])) {
            $url = $data['video_url'];
        }

        $video->update([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'url'         => $url,
        ]);

        $video->properties()->sync($data['properties'] ?? []);

        return redirect()->route('videos.index')->with('ok', 'Video updated successfully.');
    }

    public function show(Request $request, Video $video)
    {
        $user = $request->user();

        // Housekeepers can only view videos for their assigned properties
        if ($user->hasRole('housekeeper')) {
            $propertyIds = $user->properties->pluck('id')->toArray();
            $sessionPropertyIds = \App\Models\CleaningSession::where('housekeeper_id', $user->id)
                ->distinct()
                ->pluck('property_id')
                ->toArray();
            $propertyIds = array_unique(array_merge($propertyIds, $sessionPropertyIds));

            $hasAccess = $video->properties->isEmpty() || $video->properties->pluck('id')->intersect($propertyIds)->isNotEmpty();
            abort_unless($hasAccess, 403);

            return view('resources.videos.show', ['video' => $video, 'isHousekeeper' => true]);
        }

        abort_unless($user->hasAnyRole(['admin', 'owner', 'company']), 403);

        return view('resources.videos.show', ['video' => $video, 'isHousekeeper' => false]);
    }

    public function destroy(Request $request, Video $video)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'owner', 'company']), 403);

        $video->properties()->detach();
        $video->delete();

        return redirect()->route('videos.index')->with('ok', 'Video deleted.');
    }
}
