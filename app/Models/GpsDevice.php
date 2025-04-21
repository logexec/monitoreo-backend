<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GpsDevice extends Model
{
    protected $fillable = [
        'trip_id',
        'gps_provider',
        'uri_gps',
        'usuario',
        'clave',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
