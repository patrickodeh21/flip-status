<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChecklistReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'reported_by',
        'type',
        'location',
        'description',
        'priority',
        'status',
        'resolution_notes',
        'resolved_by',
        'resolved_at'
    ];

    protected $casts = [
        'resolved_at' => 'datetime'
    ];

    public function session()
    {
        return $this->belongsTo(CleaningSession::class, 'session_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
