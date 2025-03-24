<?php

namespace App\Http\Controllers\Api;

use App\Exports\TripsTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\TripsImport;
use Illuminate\Http\Request;
use App\Models\Trip;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class TripController extends Controller
{
    // Lista trips según fecha y proyectos
    public function index(Request $request)
    {
        $date = $request->query('date');
        $projects = $request->query('projects', 'all');

        $query = Trip::query();

        // if(!$date) {
        //     return response()->json(['error' => 'El parámetro date es requerido'], 400);
        // }

        if ($date) {
            $query = Trip::whereDate('delivery_date', $date);
            $trips = $query->orderBy('delivery_date', 'desc')->get();
        }

        if ($projects !== 'all') {
            $projectList = explode(',', $projects);
            $query->whereIn('project', $projectList);
        }

        $trips = $query->orderBy('delivery_date', 'desc')->get();

        return response()->json($trips);
    }

    // Método para crear un trip (validando que el chofer exista, etc.)
    public function store(Request $request)
    {
        // Incluir 'driver_email' en la validación
        $data = $request->validate([
            'system_trip_id'   => 'required|unique:trips,system_trip_id',
            'external_trip_id' => 'nullable|string',
            'delivery_date'    => 'required|date',
            'driver_name'      => 'required|string',
            'driver_email'     => 'required|email', // Validamos que se envíe y sea un email
            'driver_phone'     => 'nullable|string',
            'origin'           => 'nullable|string',
            'destination'      => 'required|string',
            'project'          => 'required|string',
            'plate_number'     => 'required|string',
            'property_type'    => 'required|string',
            'shift'            => 'required|string',
            'gps_provider'     => 'nullable|string',
        ]);

        // Validar que el chofer exista en la base de datos de sistema_onix.onix_personal
        $chofer = DB::connection('sistema_onix')
            ->table('onix_personal')
            ->where('email', $data['driver_email'])
            ->first();
        if (!$chofer) {
            return response()->json(['error' => 'El chofer no existe en la base de datos'], 400);
        }

        // Validar que el vehículo exista en tms.vehiculos
        $vehiculo = DB::connection('tms')
            ->table('vehiculos')
            ->where('plate_number', $data['plate_number'])
            ->first();
        if (!$vehiculo) {
            return response()->json(['error' => 'El vehículo no existe en la base de datos'], 400);
        }

        // Generar system_trip_id si no se envía uno (se puede ajustar según requerimiento)
        if (empty($data['system_trip_id'])) {
            $data['system_trip_id'] = $data['project'] . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        }

        // Crear el registro del trip
        $trip = Trip::create($data);

        return response()->json($trip, 201);
    }


    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new TripsImport, $request->file('file'));
            return response()->json(['message' => 'Importación completada'], 200);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            // Recopila los errores de validación para cada fila
            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $failure) {
                $errors[] = [
                    'row'    => $failure->row(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            }
            return response()->json(['errors' => $errors], 422);
        }
    }

    public function downloadTemplate()
    {
        return Excel::download(new TripsTemplateExport, 'plantilla_viajes.xlsx');
    }
}
