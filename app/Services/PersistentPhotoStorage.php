<?php

namespace App\Services;

use App\Models\PhotoBlob;
use Illuminate\Support\Facades\Storage;

class PersistentPhotoStorage
{
    public static function persistFromPublicDisk(string $path): void
    {
        $normalized = self::normalizePath($path);
        if ($normalized === null) {
            return;
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($normalized)) {
            return;
        }

        $content = $disk->get($normalized);
        if ($content === false) {
            return;
        }

        $mimeType = null;
        try {
            $mimeType = $disk->mimeType($normalized) ?: null;
        } catch (\Throwable) {
            $mimeType = null;
        }

        PhotoBlob::query()->updateOrCreate(
            ['path' => $normalized],
            [
                'mime_type' => $mimeType,
                'content_base64' => base64_encode($content),
                'size' => strlen($content),
                'checksum' => hash('sha256', $content),
            ]
        );
    }

    public static function exists(string $path): bool
    {
        $normalized = self::normalizePath($path);
        if ($normalized === null) {
            return false;
        }

        if (Storage::disk('public')->exists($normalized)) {
            return true;
        }

        return PhotoBlob::query()->where('path', $normalized)->exists();
    }

    public static function read(string $path): ?array
    {
        $normalized = self::normalizePath($path);
        if ($normalized === null) {
            return null;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($normalized)) {
            $content = $disk->get($normalized);
            if ($content === false) {
                return null;
            }

            $mimeType = null;
            try {
                $mimeType = $disk->mimeType($normalized) ?: null;
            } catch (\Throwable) {
                $mimeType = null;
            }

            return [
                'path' => $normalized,
                'content' => $content,
                'mime_type' => $mimeType ?: 'application/octet-stream',
            ];
        }

        $blob = PhotoBlob::query()->where('path', $normalized)->first();
        if (!$blob) {
            return null;
        }

        $decoded = base64_decode((string) $blob->content_base64, true);
        if ($decoded === false) {
            return null;
        }

        return [
            'path' => $normalized,
            'content' => $decoded,
            'mime_type' => $blob->mime_type ?: 'application/octet-stream',
        ];
    }

    public static function delete(string $path): void
    {
        $normalized = self::normalizePath($path);
        if ($normalized === null) {
            return;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($normalized)) {
            $disk->delete($normalized);
        }

        PhotoBlob::query()->where('path', $normalized)->delete();
    }

    private static function normalizePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $normalized = str_replace('\\', '/', trim($path));
        if ($normalized === '') {
            return null;
        }

        if (\Illuminate\Support\Str::startsWith($normalized, '/storage/')) {
            $normalized = substr($normalized, strlen('/storage/'));
        } elseif (\Illuminate\Support\Str::startsWith($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        $normalized = ltrim($normalized, '/');
        if ($normalized === '' || str_contains($normalized, '..') || str_contains($normalized, "\0")) {
            return null;
        }

        return $normalized;
    }
}

