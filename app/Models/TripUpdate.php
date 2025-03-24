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
        'updated_by',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
