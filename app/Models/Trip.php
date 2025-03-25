<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Trip extends Model
{
    use HasUuids;

    protected $fillable = [
        'system_trip_id',
        'external_trip_id',
        'delivery_date',
        'driver_name',
        'driver_document',
        'driver_phone',
        'origin',
        'destination',
        'project',
        'plate_number',
        'property_type',
        'shift',
        'gps_provider',
        'uri_gps',
        'usuario',
        'clave',
        'current_status',
    ];

    public function updates()
    {
        return $this->hasMany(TripUpdate::class, 'trip_id', 'id');
    }
}
