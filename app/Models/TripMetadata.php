<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TripMetadata extends Model
{
    use HasUuids;

    protected $fillable = [
        'trip_id',
        'estimated_duration',
        'actual_duration',
        'distance_km',
        'cargo_type',
        'cargo_weight',
        'special_requirements',
        'customer_reference',
        'internal_notes',
        'external_notes',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
