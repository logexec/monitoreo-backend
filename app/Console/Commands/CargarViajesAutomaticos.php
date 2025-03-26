<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Trip;

class CargarViajesAutomaticos extends Command
{
    protected $signature = 'viajes:cargar-automaticos';
    protected $description = 'Carga automática de viajes pendientes desde la API de tms1';

    public function handle()
    {
        // Utiliza las variables de entorno para mayor flexibilidad
        $apiUrl   = env('TMS1_API_URL', 'https://tms1.logex.com.ec/api/get-trips');
        $apiToken = env('TMS1_API_TOKEN', '');

        $this->info('Consultando la API de tms1...');

        $response = Http::withToken($apiToken)->get($apiUrl);

        if (!$response->successful()) {
            $this->error('Error al consultar la API de tms1. Código: ' . $response->status());
            return 1;
        }

        $data = $response->json();

        if (!isset($data['viajes'])) {
            $this->error('La respuesta de la API no contiene la clave "viajes".');
            return 1;
        }

        $countImported = 0;
        foreach ($data['viajes'] as $viaje) {
            // Filtrar solo los viajes en estado "Pendiente"
            if (!isset($viaje['estado_viaje']) || $viaje['estado_viaje'] !== 'Pendiente') {
                continue;
            }

            // Evitar duplicados: comprobamos que no exista un viaje con el id_viaje en external_trip_id
            if (Trip::where('external_trip_id', $viaje['id_viaje'])->exists()) {
                continue;
            }

            // Mapear los campos del API a los del modelo Trip.
            $tripData = [
                // Generar system_trip_id con el formato "PROYECTO-xxxxx"
                'system_trip_id'   => $viaje['proyecto'] . '-' . str_pad($viaje['id_viaje'], 5, '0', STR_PAD_LEFT),
                'external_trip_id' => $viaje['id_viaje'] ?? '',
                'delivery_date'    => $viaje['fecha_viaje'],
                'driver_name'      => $viaje['driver_name'] ?? 'Sin asignar',
                'driver_phone'     => $viaje['driver_phone'] ?? null,
                'origin'           => $viaje['origen'],
                'destination'      => $viaje['destino'],
                'project'          => $viaje['proyecto'],
                'plate_number'     => $viaje['placa'],
                'property_type'    => $viaje['property_type'] ?? 'Desconocido',
                'shift'            => $viaje['shift'] ?? 'Día',
                'gps_provider'     => $viaje['gps_provider'] ?? null,
                'current_status'   => 'SCHEDULED',
            ];

            Trip::create($tripData);
            $countImported++;
        }

        $this->info("Carga automática completada. Se importaron {$countImported} viajes.");
        return 0;
    }
}
