<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TripUpdate;
use App\Models\User;
use Carbon\Carbon;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TripUpdatesController extends Controller
{
    public function index(Request $request)
    {
        $query = TripUpdate::query();

        if ($request->filled('trip_id')) {
            $trip_id = $request->query('trip_id');
            $query = TripUpdate::where('trip_id', $trip_id);
        }

        $trips = $query->with(['trip:id,system_trip_id,project,plate_number,driver_name,driver_phone', 'user:id,name'])
            ->orderBy('created_at', 'desc')->get();


        return response()->json($trips);
    }

    public function store(Request $request)
    {
        // Validación
        $data = Validator::make($request->all(), [
            'trip_id'  => 'required|exists:trips,id',
            'category' => [
                'required',
                'in:INICIO_RUTA,SEGUIMIENTO,ACCIDENTE,AVERIA,ROBO_ASALTO,PERDIDA_CONTACTO,VIAJE_CARGADO,VIAJE_FINALIZADO',
            ],
            'notes'    => 'required|string',
            'image'    => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120', // Permitir PDFs
        ])->validate();

        // Subir imagen si existe
        if ($request->hasFile('image')) {
            try {
                $image = $request->file('image');
                $path = 'trip-updates/' . $image->getClientOriginalName();

                // Decodificar la clave Base64
                $credentials = base64_decode(env('GOOGLE_CREDENTIALS_BASE64'));
                if ($credentials === false) {
                    throw new \Exception('No se pudo decodificar las credenciales de Google Cloud');
                }

                // Crear el cliente de Storage
                $storage = new StorageClient([
                    'keyFile' => json_decode($credentials, true),
                ]);

                $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));
                $object = $bucket->upload(
                    fopen($image->path(), 'r'),
                    ['name' => $path]
                );

                // Generar un token único y guardar el tipo de archivo
                $data['image_token'] = Str::uuid()->toString();
                $data['image_url'] = $path;
                $data['image_type'] = $image->getClientMimeType(); // Ejemplo: image/png, application/pdf
            } catch (\Exception $e) {
                Log::error('Error al subir imagen a GCS: ' . $e->getMessage());
                return response()->json([
                    'message' => 'Error al subir la imagen a Google Cloud Storage',
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        // Indica quién está realizando la actualización
        $update_issuer = $request->user();
        $data['updated_by'] = $update_issuer->id;

        // Crear el registro de TripUpdate
        $trip_update = TripUpdate::create($data);

        // Actualizar el estado del viaje en la tabla trips
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

        $trip->current_status_update = $trip_update->category;
        $trip->save();

        return response()->json($trip_update, 201);
    }
    /**
     * Servir la imagen desde Google Cloud Storage.
     *
     * @param string $token
     * @return \Illuminate\Http\Response
     */
    public function serveImage($token)
    {
        try {
            $tripUpdate = TripUpdate::where('image_token', $token)->first();
            if (!$tripUpdate || !$tripUpdate->image_url) {
                return response()->json(['message' => 'Imagen no encontrada'], 404);
            }

            // Usar caché para almacenar la URL firmada por 30 minutos
            $cacheKey = "signed_url:{$token}";
            $url = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($tripUpdate) {
                $credentials = base64_decode(env('GOOGLE_CREDENTIALS_BASE64'));
                if ($credentials === false) {
                    throw new \Exception('No se pudo decodificar las credenciales de Google Cloud');
                }

                $storage = new StorageClient([
                    'keyFile' => json_decode($credentials, true),
                ]);

                $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));
                $object = $bucket->object($tripUpdate->image_url);

                if (!$object->exists()) {
                    throw new \Exception('Imagen no encontrada en GCS');
                }

                return $object->signedUrl(
                    Carbon::now()->addHour(),
                    ['version' => 'v4']
                );
            });

            return redirect()->to($url);
        } catch (\Exception $e) {
            Log::error('Error al servir imagen desde GCS: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al servir la imagen',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
