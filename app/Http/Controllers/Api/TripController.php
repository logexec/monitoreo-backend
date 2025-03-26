<?php

namespace App\Http\Controllers\Api;

use App\Exports\TripsTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\TripsImport;
use Illuminate\Http\Request;
use App\Models\Trip;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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

        // Aplicar filtro de fecha si existe
        if ($date) {
            $query->whereDate('delivery_date', $date);
        }

        // Aplicar filtro de proyectos si no es 'all'
        if ($projects !== 'all') {
            $projectList = explode(',', $projects);
            $query->whereIn('project', $projectList);
        }

        // Cargar la relación y obtener los resultados
        $trips = $query->with('updates')
            ->orderBy('delivery_date', 'desc')
            ->get();

        return response()->json($trips);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'trips' => 'required|array',
            'trips.*.delivery_date' => 'required|date',
            'trips.*.driver_name' => 'required|string',
            'trips.*.driver_email' => 'required|email',
            'trips.*.driver_document' => 'required|string',
            'trips.*.driver_phone' => 'required|string',
            'trips.*.origin' => 'required|string',
            'trips.*.destination' => 'required|string',
            'trips.*.project' => 'required|string',
            'trips.*.plate_number' => 'required|string',
            'trips.*.property_type' => 'required|string',
            'trips.*.shift' => 'nullable|in:Día,Noche',
            'trips.*.gps_provider' => 'nullable|string',
            'trips.*.uri_gps' => 'nullable|string',
            'trips.*.usuario' => 'nullable|string',
            'trips.*.clave' => 'nullable|string',
            'trips.*.current_status' => 'nullable|in:SCHEDULED,IN_TRANSIT,DELAYED,DELIVERED,CANCELLED',
        ]);

        $trips = [];

        foreach ($data['trips'] as $tripData) {
            // Validar el chofer
            $chofer = DB::connection('sistema_onix')
                ->table('onix_personal')
                ->where('email', $tripData['driver_email'])
                ->first();
            if (!$chofer) {
                return response()->json(['error' => 'El chofer no existe en la base de datos'], 400);
            }

            // Validar el vehículo
            $vehiculo = DB::connection('tms')
                ->table('vehiculos')
                ->where('plate_number', $tripData['plate_number'])
                ->first();
            if (!$vehiculo) {
                return response()->json(['error' => 'El vehículo no existe en la base de datos'], 400);
            }

            // Generar system_trip_id si no se envía uno
            if (empty($tripData['system_trip_id'])) {
                $tripData['system_trip_id'] = $tripData['project'] . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            }

            // Crear el viaje
            $trips[] = Trip::create($tripData);
        }

        $success_message = sizeof($trips) > 0 ? 'Viajes creados exitosamente' : 'Viaje creado exitosamente';

        return response()->json(['message' => $success_message, 'trips' => $trips], 201);
    }

    public function getDriverName(Request $request)
    {
        $driverEmail = $request->input('email');
        $driverDocument = $request->input('cedula');

        if (empty($driverEmail) && empty($driverDocument)) {
            return response()->json(['error' => 'Debes enviar al menos un parámetro.'], 400);
        }

        $name = null;
        if ($request->filled('cedula')) {
            try {
                $driver = DB::connection('tms1')
                    ->table('transportistas')
                    ->where('name', $driverDocument)
                    ->first();
                if (!$driver) {
                    return response()->json(['error' => 'La cédula ingresada no existe en la base de datos'], 400);
                }
            } catch (Exception $e) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
        }

        if ($request->filled('email')) {
            try {
                $driver = DB::connection('tms1')
                    ->table('transportistas')
                    ->where('email', $driverEmail)
                    ->first();
                if (!$driver) {
                    return response()->json(['error' => 'El email ingresado no existe en la base de datos'], 400);
                }
            } catch (Exception $e) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
        }

        return response()->json(['driver' => $driver], 200);
    }

    public function getPlateNumbers()
    {
        try {
            $plate_numbers = DB::connection('tms1')
                ->table('vehiculos')
                ->where('status', "ACTIVO")
                ->select('name', 'id')
                ->get();
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json(['data' => $plate_numbers], 200);
    }

    // Método para crear un trip (validando que el chofer exista, etc.)
    public function massStore(Request $request)
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
