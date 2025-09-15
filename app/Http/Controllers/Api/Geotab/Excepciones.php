<?php

namespace App\Http\Controllers\Api\Geotab;

use App\Http\Controllers\Controller;
use App\Services\GeoTabService;
use Carbon\Carbon;
use Exception;
use Geotab\API;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Excepciones extends Controller
{
    private GeoTabService $geoTabService;

    public function __construct(GeoTabService $geoTabService)
    {
        $this->geoTabService = $geoTabService;
    }

    /** 
     * 
     * Lista todas las excepciones de los dispositivos
     * 
     */
    public function indexExceptions(Request $request): JsonResponse
    {
        $fromDate = Carbon::parse($request->input('from_date', now()->subDays(1)))->toISOString();
        $toDate = Carbon::parse($request->input('to_date', now()))->toISOString();

        $from = $request->filled('from_date') ? $request->from_date : $fromDate;
        $to = $request->filled('to_date') ? $request->to_date : $toDate;

        $exceptions = $this->geoTabService->call(
            'Get',
            [
                'typeName' => 'ExceptionEvent',
                'search' => [
                    'fromDate' => $from,
                    'toDate' => $to,
                    'includeDismissedEvents' => false,
                ],
            ],
            5000,
            null
        );

        return response()->json($exceptions);
    }

    /** 
     * 
     * Busca el gupo
     * 
     */
    public function getGroup(Request $request): JsonResponse
    {
        $resultsLimit = $request->filled('limit') ? $request->limit : 12;

        $group = $this->geoTabService->call(
            'Get',
            [
                "typeName" => "Group",
                "resultsLimit" => $resultsLimit
            ]
        );

        return response()->json($group);
    }

    /** 
     * 
     * Busca los dispositivos
     * 
     */
    public function getDevice(Request $request): JsonResponse
    {
        $resultsLimit = $request->filled('limit') ? $request->limit : 12;

        $device = $this->geoTabService->call(
            'Get',
            [
                "typeName" => "Device",
                "resultsLimit" => $resultsLimit
            ]
        );

        return response()->json($device);
    }

    /** 
     * 
     * Busca el odometro.
     * Param @var device es el id del dispositivo a buscar (ej. b3)
     * 
     */
    public function getOdometerReading(Request $request): JsonResponse
    {
        $resultsLimit = $request->filled('limit') ? (int) $request->limit : 100;
        $fromDate = Carbon::parse($request->input('from_date', now()->subDays(5)))->toISOString();
        $toDate   = Carbon::parse($request->input('to_date', now()))->toISOString();

        if (!$request->filled('id')) {
            throw new Exception('Debes indicar el id del dispositivo para obtener resultados.', 404);
        }

        // Choose ONE diagnostic id:
        $diagnosticId = 'DiagnosticOdometerId'; // or 'DiagnosticOdometerAdjustmentId'

        $odometerReading = $this->geoTabService->call(
            'Get',
            [
                'typeName' => 'StatusData',
                'search' => [
                    'deviceSearch' => ['id' => $request->id],  // <-- important!
                    'fromDate' => $fromDate,
                    'toDate'   => $toDate,
                    'diagnosticSearch' => ['id' => $diagnosticId],
                ],
            ],
            $resultsLimit
        );

        return response()->json($odometerReading);
    }

    /** 
     * 
     * Busca la informacion de fallas.
     * 
     */
    public function getFaultData(Request $request): JsonResponse
    {
        $resultsLimit = $request->filled('limit') ? (int) $request->limit : 100;

        $faultData = $this->geoTabService->call(
            'Get',
            [
                'typeName' => 'faultdata',
                'resultsLimit' => $resultsLimit
            ]
        );

        return response()->json($faultData);
    }

    /** 
     * 
     * Trae el log por vehiculo.
     * 
     */
    public function getVehicleLogRecord(Request $request): JsonResponse
    {
        $resultsLimit = $request->filled('limit') ? (int) $request->limit : 100;
        $fromDate = Carbon::parse($request->input('from_date', now()->subDays(5)))->toISOString();
        $toDate   = Carbon::parse($request->input('to_date', now()))->toISOString();

        if (!$request->filled('id')) {
            throw new Exception('Debes indicar el id del dispositivo para obtener resultados.', 404);
        }

        $vehicleLogRecord = $this->geoTabService->call(
            'Get',
            [
                'typeName' => 'logrecord',
                'resultsLimit' => $resultsLimit,
                'search' => [
                    'deviceSearch' => ['id' => $request->id],
                    'fromDate' => $fromDate,
                    'toDate'   => $toDate,
                ]
            ]
        );

        return response()->json($vehicleLogRecord);
    }

    /** 
     * 
     * Trae los viajes.
     * 
     */
    public function getGeotabTrips(Request $request): JsonResponse
    {
        $resultsLimit = $request->filled('limit') ? (int) $request->limit : 100;
        $fromDate = Carbon::parse($request->input('from_date', now()->subDays(5)))->toISOString();

        $trips = $this->geoTabService->call(
            'Get',
            [
                'typeName' => 'Trip',
                'resultsLimit' => $resultsLimit,
                'search' => [
                    'fromDate' => $fromDate,
                    'includeDismissedEvents' => false,
                ]
            ]
        );

        return response()->json($trips);
    }
}
