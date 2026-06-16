<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = ['property_id', 'room_id', 'name', 'is_default', 'is_sporadic', 'type', 'phase', 'instructions']; // type: 'room'|'inventory', phase: 'pre_cleaning'|'during_cleaning'|'post_cleaning'


    protected $casts = [
        'is_default' => 'boolean',
        'is_sporadic' => 'boolean',
        'type' => 'string', // 'room' or 'inventory'
        'phase' => 'string', // 'pre_cleaning', 'during_cleaning', 'post_cleaning', or null for room-level tasks
    ];



    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'room_task')
            ->withTimestamps()
            ->withPivot(['sort_order', 'instructions', 'visible_to_owner', 'visible_to_housekeeper']);
    }

    public function media(): HasMany
    {
        return $this->hasMany(TaskMedia::class);
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_tasks')
            ->withTimestamps()
            ->withPivot(['sort_order', 'instructions', 'visible_to_owner', 'visible_to_housekeeper'])
            ->orderBy('property_tasks.sort_order');
    }
}
