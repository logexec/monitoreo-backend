<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardTripsSpecificInformation extends Controller
{
    public function getMonthlyTrips(Request $request)
    {
        // Define rango de fechas basado en el periodo
        $endDate = now()->endOfMonth();
        $startDate = match ($request->input('period')) {
            'last_3_months' => now()->subMonths(2)->startOfMonth(),
            'last_month'    => now()->subMonth()->startOfMonth(),
            'last_7_days'   => now()->subDays(6)->startOfDay(),
            default         => now()->startOfDay(),
        };

        // Base query
        $query = Trip::whereBetween('delivery_date', [$startDate, $endDate]);

        // Filtros múltiples con whereIn
        if ($request->filled('project')) {
            $projects = is_array($request->project) ? $request->project : [$request->project];
            $query->whereIn('project', $projects);
        }

        if ($request->filled('plate')) {
            $plates = is_array($request->plate) ? $request->plate : [$request->plate];
            $query->whereIn('plate_number', $plates);
        }

        if ($request->filled('vehicle_id')) {
            $vehicles = is_array($request->vehicle) ? $request->vehicle : [$request->vehicle];
            $query->whereIn('vehicle_id', $vehicles);
        }

        if ($request->filled('driver')) {
            $drivers = is_array($request->driver) ? $request->driver : [$request->driver];
            $query->whereIn('driver_name', $drivers);
        }

        if ($request->filled('destination')) {
            $destinations = is_array($request->destination) ? $request->destination : [$request->destination];
            $query->whereIn('destination', $destinations);
        }

        if ($request->filled('origin')) {
            $origins = is_array($request->origin) ? $request->origin : [$request->origin];
            $query->whereIn('origin', $origins);
        }


        // Agrupar por mes
        $trips = $query->get()->groupBy(function ($trip) {
            return \Illuminate\Support\Carbon::parse($trip->delivery_date)->format('Y-m');
        })->map(function ($group) use ($request) {
            return $request->has('count') ? $group->count() : $group;
        });

        // Formatear para respuesta
        $result = $trips->map(function ($value, $month) use ($request) {
            return $request->has('count')
                ? ['month' => $month, 'count' => $value]
                : ['month' => $month, 'trips' => $value];
        })->values();

        return response()->json($result);
    }
}
