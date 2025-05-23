<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TripUpdate extends Model
{
    use HasUuids;

    protected $fillable = [
        'trip_id',
        'category',
        'notes',
        'image_url',
        'image_token',
        'image_type',
        'updated_by',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
    // TripUpdate.php
    public function user()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
