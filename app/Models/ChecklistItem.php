<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'room_id',
        'task_id',
        'user_id',
        'checked',
        'quantity',
        'note',
        'checked_at'
    ];
    protected $casts = ['checked' => 'bool', 'checked_at' => 'datetime'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CleaningSession::class, 'session_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ChecklistItemPhoto::class);
    }
}
