<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'address',
        'photo_path',
        'beds',
        'baths',
        'latitude',
        'longitude',
        'geo_radius_m',
        'ical_url',
        'airbnb_ical_url',
        'vrbo_ical_url',
        'timezone'
    ];

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'property_room')
            ->withTimestamps()
            ->withPivot(['sort_order'])
            ->orderBy('property_room.sort_order');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id', 'id');
    }

    public function propertyTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'property_tasks')
            ->withTimestamps()
            ->withPivot(['sort_order', 'instructions', 'visible_to_owner', 'visible_to_housekeeper'])
            ->orderBy('property_tasks.sort_order');
    }

    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'property_video')->withTimestamps();
    }

    // Users assigned to this property
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'property_user');
    }

    public function getPhotoUrlAttribute(): string
    {
        if (!$this->photo_path) {
            return asset('images/placeholders/property.png');
        }

        if (str_starts_with($this->photo_path, 'http')) {
            return $this->photo_path;
        }

        $path = str_replace('\\', '/', trim((string) $this->photo_path));
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return url('file/' . $path);
    }
}
