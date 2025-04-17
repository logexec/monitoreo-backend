<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TripUpdate;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TripUpdatesController extends Controller
{
    public function index(Request $request)
    {
        $query = TripUpdate::query();

        if ($request->filled('trip_id')) {
            $trip_id = $request->query('trip_id');
            $query = TripUpdate::where('trip_id', $trip_id);
        }

        $trips = $query->with('trip:id,system_trip_id,project,plate_number')
            ->orderBy('created_at', 'desc')->get();

        return response()->json($trips);
    }

    public function store(Request $request)
    {
        // Incluir 'driver_email' en la validación
        $data = Validator::make($request->all(), [
            'trip_id'  => 'required|exists:trips,id',
            'category' => [
                'required',
                'in:INICIO_RUTA,SEGUIMIENTO,ACCIDENTE,AVERIA,ROBO_ASALTO,PERDIDA_CONTACTO,VIAJE_CARGADO,VIAJE_FINALIZADO',
            ],
            'notes'    => 'required|string',
            'image_url'      => 'nullable|string',
        ])->validate();

        //Subir imagen si es que existe
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $path = 'trip-updates/' . uniqid() . '.' . $image->extension();

            $storage = new StorageClient([
                'keyFilePath' => config('filesystems.disks.gcs.key_file'),
            ]);

            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));
            $object = $bucket->upload(
                fopen($image->path(), 'r'),
                ['name' => $path]
            );

            $data['image_url'] = sprintf('https://storage.googleapis.com/%s/%s', config('filesystems.disks.gcs.bucket'), $path);
        }

        // Indica quien está realizando la actualización
        $update_issuer = $request->user();
        $data['updated_by'] = $update_issuer->id;

        // Crear el registro del trip
        $trip_update = TripUpdate::create($data);

        // Actualizar el estado del viaje de la tabla trips
        $trip = $trip_update->trip;
        switch ($data['category']) {
            case 'INICIO_RUTA':
            case 'SEGUIMIENTO':
                $trip->current_status = 'IN_TRANSIT';
                break;
            case 'VIAJE_FINALIZADO':
                $trip->current_status = 'DELIVERED';
                break;
            case 'ACCIDENTE':
            case 'AVERIA':
            case 'ROBO_ASALTO':
            case 'PERDIDA_CONTACTO':
                $trip->current_status = 'DELAYED';
                break;
            default:
                $trip->current_status = 'SCHEDULED';
                break;
        }
        $trip->current_status_update = $trip_update->trip;
        $trip->save();

        return response()->json($trip_update, 201);
    }
}
