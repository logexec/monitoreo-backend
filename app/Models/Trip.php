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
        'vehicle_id',
        'property_type',
        'shift',
        'current_status',
        'created_at',
        'updated_at',
    ];

    public function gpsDevices()
    {
        return $this->hasMany(GpsDevice::class);
    }
    public function updates()
    {
        return $this->hasMany(TripUpdate::class, 'trip_id', 'id');
    }
}
