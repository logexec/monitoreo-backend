<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GeoTabService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class GeoTabApiController extends Controller
{
    private GeoTabService $geoTabService;

    public function __construct(GeoTabService $geoTabService)
    {
        $this->geoTabService = $geoTabService;
    }

    /** Lista de viajes */
    public function indexTrips(Request $request): JsonResponse
    {
        $fromDate = Carbon::parse($request->input('from_date', now()->subDays(1)))->toISOString();
        $toDate = Carbon::parse($request->input('to_date', now()))->toISOString();

        $trips = $this->geoTabService->call('Get', [
            'typeName' => 'Trip',
            'search' => [
                'fromDate' => $fromDate,
                'toDate' => $toDate
            ]
        ]);

        return response()->json($trips);
    }

    /** Viajes por dispositivo */
    public function showTrips(Request $request, string $deviceId)
    {
        $fromDate = $request->input('from_date') ?? now()->subDays(7)->toISOString();
        $toDate = $request->input('to_date') ?? now()->toISOString();
        $allData = $request->boolean('allData', false);

        $search = [
            'deviceSearch' => ['id' => $deviceId],
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ];

        $trips = $this->geoTabService->call('Get', [
            'typeName' => 'Trip',
            'search' => $search,
        ]);

        if (!$allData) {
            $trips = collect($trips)->take(50);
        }

        return response()->json($trips->values());
    }

    /** Lista de FaultData (alertas) */
    public function indexFaults(Request $request)
    {
        $deviceId = $request->input('deviceId');
        $placa = $request->input('placa');
        $severity = $request->input('severity'); // high, medium, low
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $allData = $request->boolean('allData', false);

        if (!$deviceId && $placa) {
            $deviceId = $this->geoTabService->resolveDeviceIdByPlaca($placa);
        }

        $search = [];

        if ($deviceId) {
            $search['device'] = ['id' => $deviceId];
        }
        if ($fromDate) {
            $search['dateTime'] = ['from' => $fromDate];
        }

        $result = $this->geoTabService->call('Get', [
            'typeName' => 'FaultData',
            'search' => $search
        ]);

        $faults = collect($result)->map(function ($fault) {
            $code = $fault['diagnostic']['code'] ?? '';
            $fault['severity'] = str_contains($code, 'CRIT') ? 'high' : (str_contains($code, 'WARN') ? 'medium' : 'low');
            return $fault;
        });

        if (!$allData && $severity) {
            $faults = $faults->filter(fn($f) => $f['severity'] === $severity);
        }

        return response()->json($faults->values());
    }

    /** Odómetro actual */
    public function getOdometer(string $deviceId)
    {
        $result = $this->geoTabService->call('Get', [
            'typeName' => 'Device',
            'search' => ['id' => $deviceId]
        ]);

        return response()->json([
            'odometer' => $result[0]['lastOdometer'] ?? null
        ]);
    }

    public function getVehicleData(Request $request)
    {
        $placa = $request->input('placa');
        $deviceId = $this->geoTabService->resolveDeviceIdByPlaca($placa);

        if (!$deviceId) {
            return response()->json(['error' => 'Vehículo no encontrado'], 404);
        }

        $fromDate = $request->input('from_date') ?? now()->subDays(7)->toISOString();
        $toDate = $request->input('to_date') ?? now()->toISOString();
        $allData = $request->boolean('allData', false);

        // 1. Obtener viajes
        $trips = $this->geoTabService->call('Get', [
            'typeName' => 'Trip',
            'search' => [
                'deviceSearch' => ['id' => $deviceId],
                'fromDate' => $fromDate,
                'toDate' => $toDate,
            ]
        ]);

        // 2. Obtener fallas (FaultData)
        $faults = $this->geoTabService->call('Get', [
            'typeName' => 'FaultData',
            'search' => [
                'device' => ['id' => $deviceId],
            ]
        ]);

        $faults = collect($faults)->map(function ($fault) {
            $code = $fault['diagnostic']['code'] ?? '';
            $fault['severity'] = str_contains($code, 'CRIT') ? 'high' : (str_contains($code, 'WARN') ? 'medium' : 'low');
            return $fault;
        });

        // 3. Obtener odómetro
        $deviceInfo = $this->geoTabService->call('Get', [
            'typeName' => 'Device',
            'search' => ['id' => $deviceId]
        ]);

        $odometer = $deviceInfo[0]['lastOdometer'] ?? null;

        return response()->json([
            'placa' => $placa,
            'deviceId' => $deviceId,
            'odometer' => $odometer,
            'trips' => $allData ? $trips : collect($trips)->take(50),
            'faults' => $allData ? $faults : $faults->take(20),
        ]);
    }
}
