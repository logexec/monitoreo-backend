<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TripCost extends Model
{
    use HasUuids;

    protected $fillable = [
        'trip_id',
        'fuel_cost',
        'toll_cost',
        'personnel_cost',
        'other_costs',
        'total_cost',
        'revenue',
        'margin',
        'currency',
        'notes',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
