<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TripPersonnel extends Model
{
    use HasUuids;

    protected $fillable = [
        'trip_id',
        'personnel_id',
        'role',
        'assignment_date',
        'status',
        'notes',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
