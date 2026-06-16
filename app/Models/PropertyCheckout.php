<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyCheckout extends Model
{
    protected $fillable = [
        'property_id',
        'uid',
        'checkout_date',
        'guest_name',
        'source',
        'first_seen_at'
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
