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
        // Define date range based on period
        $endDate = now()->endOfDay();
        $startDate = match ($request->input('period')) {
            'last_3_months' => now()->subMonths(3)->startOfDay(),
            'last_30_days' => now()->subDays(30)->startOfDay(),
            'last_7_days' => now()->subDays(7)->startOfDay(),
            'today' => now()->startOfDay(),
            default => now()->subMonths(3)->startOfDay(),
        };

        // Base query
        $query = Trip::whereBetween('delivery_date', [$startDate, $endDate]);

        // Apply multiple filters with whereIn
        if ($request->filled('project')) {
            $projects = is_array($request->project) ? $request->project : [$request->project];
            $query->whereIn('project', $projects);
        }

        if ($request->filled('destination')) {
            $destinations = is_array($request->destination) ? $request->destination : [$request->destination];
            $query->whereIn('destination', $destinations);
        }

        // Handle action
        $action = $request->input('action');
        if ($action === 'status_counts') {
            $totalTrips = $query->count();
            $statusCounts = [
                'finalizados' => $query->clone()->where('current_status_update', 'VIAJE_FINALIZADO')->count(),
                'con_novedad' => $query->clone()->whereIn('current_status_update', [
                    'ACCIDENTE',
                    'AVERIA',
                    'ROBO_ASALTO',
                    'PERDIDA_CONTACTO',
                    'VIAJE_CARGADO'
                ])->count(),
                'en_seguimiento' => $query->clone()->where('current_status_update', 'SEGUIMIENTO')->count(),
                'preparados' => $query->clone()->whereIn('current_status_update', [
                    'VIAJE_CREADO',
                    'VIAJE_CARGADO'
                ])->count(),
            ];

            return response()->json([
                'total' => $totalTrips,
                'status_counts' => $statusCounts,
            ]);
        }

        // Handle groupBy
        $groupBy = $request->input('groupBy');
        if ($groupBy) {
            if ($groupBy === 'month_project') {
                $trips = $query->selectRaw("DATE_FORMAT(delivery_date, '%Y-%m') as month, project, COUNT(*) as trips")
                    ->groupBy('month', 'project')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'month' => $item->month,
                            'project' => $item->project,
                            'trips' => $item->trips,
                        ];
                    });
                return response()->json($trips);
            }

            $groupByField = match ($groupBy) {
                'month' => 'delivery_date',
                'destination' => 'destination',
                'origin' => 'origin',
                'project' => 'project',
                default => null,
            };

            if ($groupByField) {
                if ($groupBy === 'month') {
                    $trips = $query->selectRaw("DATE_FORMAT(delivery_date, '%Y-%m') as month, COUNT(*) as count")
                        ->groupBy('month')
                        ->get()
                        ->map(function ($item) {
                            return [
                                'month' => $item->month,
                                'trips' => $item->count,
                            ];
                        });
                } else {
                    $trips = $query->selectRaw("$groupByField as group_key, COUNT(*) as count, delivery_date")
                        ->groupBy($groupByField, 'delivery_date')
                        ->get()
                        ->groupBy('group_key')
                        ->map(function ($items, $key) use ($groupBy) {
                            return [
                                $groupBy => $key,
                                'trips' => $items->sum('count'),
                                'details' => $items->map(function ($item) {
                                    return [
                                        'delivery_date' => $item->delivery_date,
                                        'count' => $item->count,
                                    ];
                                })->values(),
                            ];
                        })->values();
                }
            } else {
                $trips = $query->select('id', 'project', 'destination', 'delivery_date')->get();
                $trips = [[
                    'trips' => $trips->count(),
                    'data' => $trips->map(function ($trip) {
                        return [
                            'id' => $trip->id,
                            'project' => $trip->project,
                            'destination' => $trip->destination,
                            'delivery_date' => $trip->delivery_date,
                        ];
                    })->values(),
                ]];
            }
        } else {
            $trips = $query->select('id', 'project', 'destination', 'delivery_date')->get();
            $trips = [[
                'trips' => $trips->count(),
                'data' => $trips->map(function ($trip) {
                    return [
                        'id' => $trip->id,
                        'project' => $trip->project,
                        'destination' => $trip->destination,
                        'delivery_date' => $trip->delivery_date,
                    ];
                })->values(),
            ]];
        }

        if ($action === 'count') {
            $trips = sizeof($trips);
        }

        return response()->json($trips);
    }

    public function getProjects()
    {
        $projects = Trip::distinct()->pluck('project')->values();
        return response()->json($projects);
    }

    public function getDestinations()
    {
        $destinations = Trip::distinct()->pluck('destination')->values();
        return response()->json($destinations);
    }
}
