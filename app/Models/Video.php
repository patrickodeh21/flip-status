<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

class Video extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'url', 'thumbnail', 'uploaded_by'];

    protected $appends = ['url', 'thumbnail'];

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_video')->withTimestamps();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected function url(): Attribute
    {
        return Attribute::get(function ($value) {
            if (!$value) return $value;
            if (Str::startsWith($value, ['http://', 'https://'])) {
                return $value;
            }
            $path = str_replace('\\', '/', trim((string) $value));
            $path = ltrim($path, '/');
            if (Str::startsWith($path, 'storage/')) {
                $path = substr($path, strlen('storage/'));
            }
            return url('file/' . $path);
        });
    }

    protected function thumbnail(): Attribute
    {
        return Attribute::get(function ($value) {
            if (!$value) return $value;
            if (Str::startsWith($value, ['http://', 'https://'])) {
                return $value;
            }
            $path = str_replace('\\', '/', trim((string) $value));
            $path = ltrim($path, '/');
            if (Str::startsWith($path, 'storage/')) {
                $path = substr($path, strlen('storage/'));
            }
            return url('file/' . $path);
        });
    }
}
