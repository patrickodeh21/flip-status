<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhotoBlob extends Model
{
    protected $fillable = [
        'path',
        'mime_type',
        'content_base64',
        'size',
        'checksum',
    ];
}

